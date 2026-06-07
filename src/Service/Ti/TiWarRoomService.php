<?php



namespace App\Service\Ti;



use App\Entity\Empresa;

use App\Entity\TiChamado;

use App\Platform\AiAssistant;

use App\Repository\TiChamadoRepository;

use App\Repository\TiManutencaoRepository;



/** Centro de comando para incidentes críticos (P1). */

final class TiWarRoomService

{

    /** @var array<string, list<string>> */

    private const CATEGORY_BLAST = [

        'rede' => ['VPN Corporativa', 'Wi-Fi Campus', 'Firewall Edge'],

        'integracao' => ['API Gateway', 'Núcleo Integrações', 'Webhooks'],

        'acesso' => ['Active Directory', 'SSO / MFA', 'Portal RH'],

        'sistema' => ['ERP Unio', 'E-mail Exchange', 'Backup Center'],

        'infra' => ['Datacenter Core', 'Storage SAN', 'Hypervisor'],

        'seguranca' => ['SIEM', 'Endpoint Protection', 'Certificados TLS'],

        'hardware' => ['Estação de trabalho', 'Periféricos', 'Datacenter físico'],

        'licenca' => ['Adobe CC', 'Microsoft 365', 'Ferramentas SaaS'],

    ];



    public function __construct(

        private TiChamadoRepository $chamados,

        private TiChamadoService $chamadoService,

        private TiService $tiService,

        private TiHeliaService $helia,

        private TiPlaybookService $playbooks,

        private TiManutencaoRepository $manutencaoRepo,

    ) {}



    /** @return array<string, mixed> */

    public function build(Empresa $empresa): array

    {

        $p1Tickets = $this->collectP1Incidents($empresa);

        $nocSystems = $this->tiService->getNocSystems($empresa);

        $metrics = $this->buildMetrics($empresa, $p1Tickets, $nocSystems);

        $blastRadius = $this->buildBlastRadius($p1Tickets, $nocSystems);

        $timeline = $this->buildUnifiedTimeline($empresa, $p1Tickets, $nocSystems);

        $teamBoard = $this->buildTeamBoard($empresa, $p1Tickets);

        $maintenanceAlerts = $this->buildMaintenanceAlerts($empresa);

        $commandBrief = $this->buildCommandBrief($p1Tickets, $nocSystems, $metrics, $blastRadius);

        $statusComms = $this->buildStatusComms($p1Tickets, $metrics, $blastRadius);



        return [

            'p1_incidents' => $p1Tickets,

            'p1_count' => \count($p1Tickets),

            'noc_systems' => $nocSystems,

            'metrics' => $metrics,

            'blast_radius' => $blastRadius,

            'unified_timeline' => $timeline,

            'team_board' => $teamBoard,

            'maintenance_alerts' => $maintenanceAlerts,

            'command_brief' => $commandBrief,

            'status_comms' => $statusComms,

            'integration_chain' => $this->buildIntegrationChain($nocSystems),

            'severity_score' => $metrics['severity_score'],

            'severity_level' => $metrics['severity_level'],

            'live_ops' => $this->chamadoService->liveOpsFeed($empresa, 8),

        ];

    }



    /** @return list<array<string, mixed>> */

    private function collectP1Incidents(Empresa $empresa): array

    {

        $incidents = [];

        foreach ($this->chamados->findByEmpresa($empresa) as $chamado) {

            if ($chamado->getPrioridade() !== 'P1' || $chamado->getStatus() === TiChamado::STATUS_RESOLVIDO) {

                continue;

            }



            $ticket = $this->chamadoService->mapTicketForDisplay($chamado);

            $playbook = $this->playbooks->matchForTicket($ticket, $empresa);

            $heliaInsight = $this->helia->insightForTicket($ticket, $empresa);

            $slaRemaining = $this->computeSlaRemaining($chamado);



            $steps = $chamado->getPlaybookSteps();

            if ($steps === [] && $playbook !== null) {

                $steps = $this->playbooks->initPlaybookSteps($ticket, $empresa);

            }



            $incidents[] = array_merge($ticket, [

                'db_id' => $chamado->getId(),

                'sla_remaining_sec' => $slaRemaining['seconds'],

                'sla_remaining_label' => $slaRemaining['label'],

                'sla_breach_imminent' => $slaRemaining['imminent'],

                'elapsed_label' => $slaRemaining['elapsed_label'],

                'timeline' => $chamado->getTimeline(),

                'playbook' => $playbook,

                'playbook_steps' => $steps,

                'helia_insight' => $heliaInsight,

                'affected_users' => $chamado->getUsuariosAfetados() ?: $this->estimateAffectedUsers($chamado),

            ]);

        }



        usort($incidents, static fn ($a, $b) => ($a['sla_pct'] ?? 100) <=> ($b['sla_pct'] ?? 100));



        return $incidents;

    }



    /** @return array{seconds: int, label: string, imminent: bool, elapsed_label: string} */

    private function computeSlaRemaining(TiChamado $chamado): array

    {

        $limitHours = TiReferenceData::resolutionHours($chamado->getPrioridade());

        $limitSec = (int) ($limitHours * 3600);

        $now = new \DateTimeImmutable();

        $elapsed = $now->getTimestamp() - $chamado->getAbertoEm()->getTimestamp();

        if ($chamado->getSlaPausadoEm() !== null) {

            $elapsed -= ($now->getTimestamp() - $chamado->getSlaPausadoEm()->getTimestamp());

        }

        $elapsed -= $chamado->getSlaPausadoAcumuladoSeg();

        $elapsed = max(0, $elapsed);

        $remaining = max(0, $limitSec - $elapsed);



        $hours = intdiv($remaining, 3600);

        $mins = intdiv($remaining % 3600, 60);

        $secs = $remaining % 60;

        $label = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);



        $elapsedHours = intdiv($elapsed, 3600);

        $elapsedMins = intdiv($elapsed % 3600, 60);



        return [

            'seconds' => $remaining,

            'label' => $label,

            'imminent' => $remaining < 900,

            'elapsed_label' => $elapsedHours > 0

                ? sprintf('%dh %02dm aberto', $elapsedHours, $elapsedMins)

                : sprintf('%dm aberto', max(1, $elapsedMins)),

        ];

    }



    private function estimateAffectedUsers(TiChamado $chamado): int

    {

        $base = match ($chamado->getImpacto()) {

            'critico' => 180,

            'alto' => 55,

            'medio' => 18,

            default => 6,

        };



        return $base + ($chamado->getId() ?? 0) % 35;

    }



    /** @param list<array<string, mixed>> $p1Tickets @param list<array<string, mixed>> $nocSystems @return array<string, mixed> */

    private function buildMetrics(Empresa $empresa, array $p1Tickets, array $nocSystems): array

    {

        $openAll = array_filter(

            $this->chamadoService->all($empresa),

            static fn ($t) => ($t['status'] ?? '') !== TiChamado::STATUS_RESOLVIDO,

        );

        $p2Count = \count(array_filter($openAll, static fn ($t) => ($t['priority'] ?? '') === 'P2'));

        $degraded = \count(array_filter($nocSystems, static fn ($s) => ($s['status'] ?? '') !== 'operational'));

        $avgSla = $p1Tickets === []

            ? 100

            : (int) round(array_sum(array_column($p1Tickets, 'sla_pct')) / \count($p1Tickets));



        $severity = min(100, \count($p1Tickets) * 28 + $degraded * 12 + ($avgSla < 40 ? 20 : ($avgSla < 60 ? 10 : 0)));



        return [

            'open_total' => \count($openAll),

            'p1_active' => \count($p1Tickets),

            'p2_active' => $p2Count,

            'systems_up' => \count($nocSystems) - $degraded,

            'systems_down' => $degraded,

            'systems_total' => \count($nocSystems),

            'sla_compliance' => $this->chamadoService->slaCompliance($empresa),

            'mttr_hours' => $this->chamadoService->mttrHours($empresa),

            'avg_p1_sla_pct' => $avgSla,

            'severity_score' => $severity,

            'severity_level' => match (true) {

                $severity >= 75 => 'critical',

                $severity >= 45 => 'elevated',

                $severity >= 20 => 'watch',

                default => 'stable',

            },

        ];

    }



    /** @param list<array<string, mixed>> $p1Tickets @param list<array<string, mixed>> $nocSystems @return list<array<string, mixed>> */

    private function buildBlastRadius(array $p1Tickets, array $nocSystems): array

    {

        $nodes = [];

        $seen = [];



        foreach ($p1Tickets as $ticket) {

            $category = $ticket['category'] ?? 'sistema';

            foreach (self::CATEGORY_BLAST[$category] ?? ['Serviços corporativos'] as $name) {

                if (isset($seen[$name])) {

                    continue;

                }

                $seen[$name] = true;

                $nodes[] = [

                    'name' => $name,

                    'source' => $ticket['id'],

                    'severity' => ($ticket['sla_pct'] ?? 100) < 30 ? 'critical' : 'high',

                    'users' => $ticket['affected_users'] ?? 0,

                ];

            }

        }



        foreach ($nocSystems as $sys) {

            if (($sys['status'] ?? '') === 'operational') {

                continue;

            }

            $name = $sys['name'];

            if (isset($seen[$name])) {

                continue;

            }

            $seen[$name] = true;

            $nodes[] = [

                'name' => $name,

                'source' => 'NOC',

                'severity' => ($sys['status'] ?? '') === 'incident' ? 'critical' : 'high',

                'users' => 30,

            ];

        }



        if ($nodes === []) {

            $nodes[] = ['name' => 'Operação estável', 'source' => '—', 'severity' => 'ok', 'users' => 0];

        }



        return $nodes;

    }



    /** @param list<array<string, mixed>> $p1Tickets @param list<array<string, mixed>> $nocSystems @return list<array<string, mixed>> */

    private function buildUnifiedTimeline(Empresa $empresa, array $p1Tickets, array $nocSystems): array

    {

        $events = [];



        foreach ($p1Tickets as $ticket) {

            foreach ($ticket['timeline'] ?? [] as $entry) {

                $events[] = [

                    'at' => $entry['at'] ?? '—',

                    'ts' => $this->parseTimelineTs($entry['at'] ?? ''),

                    'event' => ($ticket['id'] ?? '') . ' · ' . ($entry['event'] ?? ''),

                    'actor' => $entry['actor'] ?? '—',

                    'tone' => 'critical',

                    'icon' => 'fa-fire',

                ];

            }

            $events[] = [

                'at' => $ticket['opened_at'] ?? '—',

                'ts' => strtotime($ticket['opened_at'] ?? 'now') ?: time(),

                'event' => 'Incidente P1 declarado: ' . ($ticket['title'] ?? ''),

                'actor' => $ticket['requester'] ?? '—',

                'tone' => 'critical',

                'icon' => 'fa-bolt',

            ];

        }



        foreach ($nocSystems as $sys) {

            if (($sys['status'] ?? '') === 'operational') {

                continue;

            }

            $events[] = [

                'at' => date('H:i'),

                'ts' => time(),

                'event' => 'Degradação detectada: ' . ($sys['name'] ?? 'Sistema'),

                'actor' => 'NOC Monitor',

                'tone' => ($sys['status'] ?? '') === 'incident' ? 'critical' : 'warn',

                'icon' => 'fa-tower-broadcast',

            ];

        }



        foreach ($this->chamadoService->liveOpsFeed($empresa, 5) as $i => $ops) {

            $events[] = [

                'at' => date('H:i'),

                'ts' => time() - ($i + 1) * 120,

                'event' => $ops['text'] ?? '',

                'actor' => 'Live Ops',

                'tone' => $ops['tone'] ?? 'neutral',

                'icon' => $ops['icon'] ?? 'fa-circle',

            ];

        }



        usort($events, static fn ($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));



        return \array_slice($events, 0, 20);

    }



    private function parseTimelineTs(string $at): int

    {

        if ($at === '') {

            return time();

        }

        $parsed = \DateTimeImmutable::createFromFormat('d/m H:i', $at)

            ?: \DateTimeImmutable::createFromFormat('d/m/Y H:i', $at);



        return $parsed ? $parsed->getTimestamp() : time();

    }



    /** @param list<array<string, mixed>> $p1Tickets @return list<array<string, mixed>> */

    private function buildTeamBoard(Empresa $empresa, array $p1Tickets): array

    {

        $workload = $this->chamadoService->workloadByTechnician($empresa);

        $p1Assignees = [];

        foreach ($p1Tickets as $t) {

            $name = $t['assignee'] ?? 'Sem responsável';

            if ($name !== '—' && $name !== 'Sem responsável') {

                $p1Assignees[$name] = ($p1Assignees[$name] ?? 0) + 1;

            }

        }



        $board = [];

        foreach ($workload as $row) {

            $name = $row['name'];

            $board[] = [

                'name' => $name,

                'open_tickets' => $row['count'],

                'p1_assigned' => $p1Assignees[$name] ?? 0,

                'load_pct' => min(100, $row['count'] * 12),

                'on_p1' => ($p1Assignees[$name] ?? 0) > 0,

            ];

        }



        if ($board === []) {

            foreach ($this->chamadoService->technicians($empresa) as $user) {

                $name = $user->getNome() ?: $user->getEmail() ?: 'Técnico';

                $board[] = [

                    'name' => $name,

                    'open_tickets' => 0,

                    'p1_assigned' => 0,

                    'load_pct' => 0,

                    'on_p1' => false,

                ];

            }

        }



        usort($board, static fn ($a, $b) => ($b['on_p1'] <=> $a['on_p1']) ?: ($b['open_tickets'] <=> $a['open_tickets']));



        return \array_slice($board, 0, 8);

    }



    /** @return list<array<string, mixed>> */

    private function buildMaintenanceAlerts(Empresa $empresa): array

    {

        $alerts = [];

        foreach ($this->manutencaoRepo->findByEmpresa($empresa) as $m) {

            if (!\in_array($m->getStatus(), ['scheduled', 'approved'], true)) {

                continue;

            }

            $alerts[] = [

                'titulo' => $m->getTitulo(),

                'janela' => $m->getJanela(),

                'impacto' => $m->getImpacto(),

                'aprovada' => $m->isAprovada(),

                'servicos' => $m->getServicosAfetados(),

                'owner' => $m->getOwner(),

            ];

            if (\count($alerts) >= 3) {

                break;

            }

        }



        return $alerts;

    }



    /** @param list<array<string, mixed>> $p1Tickets @param list<array<string, mixed>> $nocSystems @param array<string, mixed> $metrics @param list<array<string, mixed>> $blastRadius @return array<string, mixed> */

    private function buildCommandBrief(array $p1Tickets, array $nocSystems, array $metrics, array $blastRadius): array

    {

        if ($p1Tickets === []) {

            return [

                'headline' => 'Operação estável — nenhum incidente P1 ativo',

                'summary' => 'Todos os sistemas monitorados estão dentro dos parâmetros normais. War Room em modo observação.',

                'actions' => ['Manter monitoramento NOC', 'Revisar fila P2/P3', 'Validar janelas de manutenção'],

                'confidence' => 96,

                'tone' => 'ok',

            ];

        }



        $worst = $p1Tickets[0];

        $degradedNames = array_map(

            static fn ($s) => $s['name'],

            array_filter($nocSystems, static fn ($s) => ($s['status'] ?? '') !== 'operational'),

        );

        $totalUsers = array_sum(array_column($blastRadius, 'users'));

        $actions = [

            'Acionar bridge call com stakeholders',

            'Isolar causa raiz em ' . ($worst['category'] ?? 'infra'),

            'Atualizar status page externa',

        ];

        if (($worst['assignee'] ?? '—') === '—') {

            $actions[] = 'Atribuir incident commander imediatamente';

        }

        if ($worst['playbook'] ?? null) {

            $actions[] = 'Executar runbook: ' . ($worst['playbook']['titulo'] ?? 'P1');

        }



        $headline = \count($p1Tickets) === 1

            ? 'Incidente P1 ativo — ' . ($worst['title'] ?? '')

            : \count($p1Tickets) . ' incidentes P1 simultâneos — situação crítica';



        $degradedText = $degradedNames !== [] ? implode(', ', \array_slice($degradedNames, 0, 3)) : 'sem degradação NOC adicional';



        return [

            'headline' => $headline,

            'summary' => sprintf(

                AiAssistant::NAME . ' detectou %d incidente(s) P1 com SLA médio em %d%%. Sistemas afetados: %s. Estimativa de ~%d usuários impactados. %s',

                \count($p1Tickets),

                $metrics['avg_p1_sla_pct'],

                $degradedText,

                $totalUsers,

                ($worst['helia_analysis'] ?? '') !== '' ? 'Análise: ' . mb_substr((string) $worst['helia_analysis'], 0, 120) . '…' : '',

            ),

            'actions' => $actions,

            'confidence' => (int) min(98, 70 + \count($p1Tickets) * 4 + ($worst['helia_confidence'] ?? 0) / 5),

            'tone' => ($metrics['severity_score'] ?? 0) >= 60 ? 'critical' : 'warn',

        ];

    }



    /** @param list<array<string, mixed>> $p1Tickets @param array<string, mixed> $metrics @param list<array<string, mixed>> $blastRadius @return array<string, string> */

    private function buildStatusComms(array $p1Tickets, array $metrics, array $blastRadius): array

    {

        if ($p1Tickets === []) {

            return [

                'internal' => '[TI · STATUS] Todos os serviços operacionais. Nenhuma ação necessária.',

                'external' => 'Todos os sistemas estão operacionais. Nenhum impacto reportado.',

                'executive' => 'Operação TI estável. SLA ' . $metrics['sla_compliance'] . '% nos últimos 7 dias.',

            ];

        }



        $primary = $p1Tickets[0];

        $services = implode(', ', array_slice(array_column($blastRadius, 'name'), 0, 4));



        return [

            'internal' => sprintf(

                '[WAR ROOM · P1] %s — %s. Commander: %s. SLA restante: %s. Serviços: %s. Bridge aberta.',

                $primary['id'] ?? 'TK-???',

                $primary['title'] ?? 'Incidente',

                ($primary['assignee'] ?? '') !== '—' ? $primary['assignee'] : 'A DESIGNAR',

                $primary['sla_remaining_label'] ?? '—',

                $services,

            ),

            'external' => sprintf(

                'Estamos investigando uma indisponibilidade que pode afetar: %s. Nossa equipe técnica foi acionada. Atualizaremos em 15 minutos.',

                $services,

            ),

            'executive' => sprintf(

                'Incidente P1 (%d ativo(s)) — impacto estimado em %d usuários. MTTR histórico: %sh. Severidade: %d/100.',

                $metrics['p1_active'],

                array_sum(array_column($blastRadius, 'users')),

                $metrics['mttr_hours'],

                $metrics['severity_score'],

            ),

        ];

    }



    /** @param list<array<string, mixed>> $nocSystems @return list<array<string, mixed>> */

    private function buildIntegrationChain(array $nocSystems): array

    {

        return array_map(static fn (array $sys) => [

            'name' => $sys['name'],

            'status' => $sys['status'] ?? 'operational',

            'latency' => $sys['latency'] ?? '—',

            'icon' => $sys['icon'] ?? 'fa-plug',

        ], $nocSystems);

    }

}


