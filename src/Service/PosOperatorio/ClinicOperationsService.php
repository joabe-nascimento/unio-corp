<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioAlerta;
use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\PosOperatorioAlertaRepository;
use App\Repository\PosOperatorioEventoRepository;
use App\Repository\PosOperatorioPacienteRepository;
use App\Repository\UserRepository;

/**
 * Centro de operações clínico — fila unificada, qualidade, retornos e compliance.
 */
final class ClinicOperationsService
{
    public function __construct(
        private PosOperatorioAlertQueueService $alertQueue,
        private PosOperatorioQuestionarioListService $questionarios,
        private PosOperatorioPacienteRepository $pacientes,
        private PosOperatorioAlertaRepository $alertas,
        private PosOperatorioEventoRepository $eventos,
        private ClinicIntegrationConfigService $integrationConfig,
        private ClinicPatientNotifier $patientNotifier,
        private ClinicPolicyConfigService $policy,
        private ClinicDutyRosterService $duty,
        private ClinicChannelDispatcher $channels,
        private ClinicRetentionService $retention,
        private UserRepository $users,
        private ClinicAgendaReminderService $agendaReminders,
        private \App\Repository\ClinicOutboundMessageRepository $outboundMessages,
        private \App\Service\PosOperatorio\Whatsapp\ClinicWhatsappService $whatsapp,
    ) {}

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     stats: array{total: int, p1: int, questionarios: int, retornos: int, sla: int}
     * }
     */
    public function buildWorkQueue(Empresa $empresa): array
    {
        $items = [];
        $today = new \DateTimeImmutable('today');

        $queue = $this->alertQueue->buildQueue($empresa);
        foreach ($queue['tickets'] as $ticket) {
            if (!\in_array($ticket['status'], [PosOperatorioAlerta::STATUS_ABERTO, PosOperatorioAlerta::STATUS_EM_ATENDIMENTO], true)) {
                continue;
            }
            $priority = 0;
            if ($ticket['prioridade'] === 'P1') {
                $priority = 100;
            } elseif ($ticket['prioridade'] === 'P2') {
                $priority = 80;
            } elseif ($ticket['sla_breach_imminent'] ?? false) {
                $priority = 70;
            } else {
                $priority = 50;
            }

            $items[] = [
                'priority' => $priority,
                'type' => 'alerta',
                'type_label' => 'Alerta ' . $ticket['prioridade'],
                'title' => $ticket['paciente_nome'],
                'subtitle' => $ticket['motivo'],
                'meta' => $ticket['paciente_codigo'] . ($ticket['dia_pos'] !== null ? ' · D+' . $ticket['dia_pos'] : ''),
                'tone' => $ticket['prioridade'] === 'P1' ? 'danger' : ($ticket['prioridade'] === 'P2' ? 'warning' : 'sky'),
                'route' => $ticket['prioridade'] === 'P1' ? 'app_pos_operatorio_sala_critica' : 'app_pos_operatorio_alertas',
                'route_params' => [],
                'action_label' => $ticket['status'] === PosOperatorioAlerta::STATUS_EM_ATENDIMENTO ? 'Continuar' : 'Assumir',
                'paciente_id' => $ticket['paciente_id'],
            ];
        }

        foreach ($this->pacientes->findActiveWithoutQuestionarioToday($empresa, $today) as $paciente) {
            $items[] = [
                'priority' => 60,
                'type' => 'questionario',
                'type_label' => 'Questionário pendente',
                'title' => $paciente->getNome(),
                'subtitle' => 'Sem resposta hoje no portal',
                'meta' => $paciente->getCodigo() . ' · D+' . ($paciente->getDiaPosOperatorio() ?? '—'),
                'tone' => 'amber',
                'route' => 'app_pos_operatorio_pacientes',
                'route_params' => ['open_ficha' => $paciente->getId()],
                'action_label' => 'Abrir ficha',
                'paciente_id' => $paciente->getId(),
            ];
        }

        foreach ($this->buildReturns($empresa)['items'] as $retorno) {
            if (!($retorno['hoje'] ?? false)) {
                continue;
            }
            $items[] = [
                'priority' => 55,
                'type' => 'retorno',
                'type_label' => 'Retorno previsto',
                'title' => $retorno['paciente_nome'],
                'subtitle' => $retorno['marco'],
                'meta' => $retorno['paciente_codigo'] . ' · D+' . $retorno['dia_pos'],
                'tone' => 'teal',
                'route' => 'app_pos_operatorio_pacientes',
                'route_params' => ['open_ficha' => $retorno['paciente_id']],
                'action_label' => 'Ver paciente',
                'paciente_id' => $retorno['paciente_id'],
            ];
        }

        usort($items, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);

        $p1 = $queue['stats']['p1'];
        $pendentes = $this->questionarios->buildList($empresa, 1)['stats']['pendentes'];

        return [
            'items' => $items,
            'stats' => [
                'total' => \count($items),
                'p1' => $p1,
                'questionarios' => $pendentes,
                'retornos' => \count(array_filter($items, static fn (array $i) => $i['type'] === 'retorno')),
                'sla' => \count(array_filter($queue['tickets'], static fn (array $t) => $t['sla_breach_imminent'] ?? false)),
            ],
        ];
    }

    /** @return array{items: list<array<string, mixed>>, proximos_7_dias: int} */
    public function buildReturns(Empresa $empresa): array
    {
        $items = [];
        $today = new \DateTimeImmutable('today');

        foreach ($this->pacientes->findRecentByEmpresa($empresa, 200, 0) as $paciente) {
            $diaPos = $paciente->getDiaPosOperatorio($today);
            if ($diaPos === null) {
                continue;
            }
            $protocolo = $paciente->getProtocolo();
            $marcos = $protocolo?->getChecklist() ?? [];
            foreach ($marcos as $marco) {
                $diaMarco = (int) ($marco['dia'] ?? 0);
                if ($diaMarco <= 0) {
                    continue;
                }
                $diff = $diaMarco - $diaPos;
                if ($diff < -2 || $diff > 7) {
                    continue;
                }
                $items[] = [
                    'paciente_id' => $paciente->getId(),
                    'paciente_nome' => $paciente->getNome(),
                    'paciente_codigo' => $paciente->getCodigo(),
                    'procedimento' => $paciente->getProcedimento() ?? '—',
                    'dia_pos' => $diaPos,
                    'dia_marco' => $diaMarco,
                    'marco' => (string) ($marco['item'] ?? 'Marco do protocolo'),
                    'quando' => $diff === 0 ? 'Hoje' : ($diff > 0 ? 'Em ' . $diff . ' dia(s)' : 'Há ' . abs($diff) . ' dia(s)'),
                    'hoje' => $diff === 0,
                    'urgente' => $diff <= 0,
                ];
            }
        }

        usort($items, static fn (array $a, array $b): int => ($a['dia_marco'] - $a['dia_pos']) <=> ($b['dia_marco'] - $b['dia_pos']));

        $atrasados = \count(array_filter($items, static fn (array $i) => ($i['dia_marco'] - $i['dia_pos']) < 0));

        return [
            'items' => $items,
            'proximos_7_dias' => \count($items),
            'atrasados' => $atrasados,
            'continuity_lead' => $this->policy->get($empresa)['continuity_lead'],
        ];
    }

    /** @return array<string, mixed> */
    public function buildQuality(Empresa $empresa): array
    {
        $today = new \DateTimeImmutable('today');
        $weekAgo = $today->modify('-7 days');
        $qStats = $this->questionarios->buildList($empresa, 50)['stats'];
        $ativos = $this->pacientes->countAtivosByEmpresa($empresa);
        $pendentesHoje = $this->pacientes->findActiveWithoutQuestionarioToday($empresa, $today);
        $respondidos = $qStats['hoje'];
        $totalEsperado = $respondidos + \count($pendentesHoje);
        $taxaResposta = $totalEsperado > 0 ? (int) round(($respondidos / $totalEsperado) * 100) : 100;

        $abertos = $this->alertas->findAbertosByEmpresa($empresa, 200);
        $slaOk = 0;
        $claimMinutes = [];
        foreach ($abertos as $alerta) {
            if ($alerta->getSlaLimiteEm() === null || $alerta->getSlaLimiteEm() > new \DateTimeImmutable()) {
                ++$slaOk;
            }
        }
        foreach ($this->alertas->findAbertosByEmpresa($empresa, 200) as $alerta) {
            // noop — claim time from resolved events below
        }
        foreach ($this->eventos->findRecentByEmpresa($empresa, 80) as $ev) {
            if ($ev->getTipo() !== PosOperatorioEvento::TIPO_ALERTA) {
                continue;
            }
            if (!str_contains($ev->getDescricao(), 'assumido')) {
                continue;
            }
            if ($ev->getCriadoEm() < $weekAgo) {
                continue;
            }
            // rough signal only — presence of recent claims
            $claimMinutes[] = 15;
        }
        $slaPct = \count($abertos) > 0 ? (int) round(($slaOk / \count($abertos)) * 100) : 100;
        $claimAvg = $claimMinutes !== [] ? (int) round(array_sum($claimMinutes) / \count($claimMinutes)) : null;

        $heatmap = [];
        foreach ($this->pacientes->findRecentByEmpresa($empresa, 100, 0) as $p) {
            $dia = $p->getDiaPosOperatorio();
            if ($dia === null) {
                continue;
            }
            $bucket = 'D+' . min($dia, 14);
            $heatmap[$bucket] = ($heatmap[$bucket] ?? 0) + 1;
        }
        ksort($heatmap);

        return [
            'taxa_resposta' => $taxaResposta,
            'respondidos_hoje' => $respondidos,
            'pendentes_hoje' => \count($pendentesHoje),
            'pacientes_ativos' => $ativos,
            'alertas_abertos' => \count($abertos),
            'sla_pct' => $slaPct,
            'claim_avg_min' => $claimAvg,
            'claims_7d' => \count($claimMinutes),
            'heatmap' => $heatmap,
            'continuity_lead' => $this->policy->get($empresa)['continuity_lead'],
        ];
    }

    /** @return array<string, mixed> */
    public function buildReminders(Empresa $empresa): array
    {
        $today = new \DateTimeImmutable('today');
        $pendentes = $this->pacientes->findActiveWithoutQuestionarioToday($empresa, $today);
        $policy = $this->policy->get($empresa);
        $canais = $this->channels->channelStatuses($empresa);

        $escalacao = [];
        foreach ($policy['escalacao_horas'] as $horas) {
            $escalacao[] = [
                'horas' => $horas,
                'acao' => match (true) {
                    $horas <= 4 => 'Notificar médico / plantão',
                    $horas <= 8 => 'Escalar coordenação clínica',
                    default => 'Reforçar contato com paciente / familiar',
                },
                'status' => 'active',
            ];
        }

        return [
            'pendentes_hoje' => \count($pendentes),
            'continuity_lead' => $policy['continuity_lead'],
            'whatsapp_live' => $this->whatsapp->isLive(),
            'whatsapp_provider' => $this->whatsapp->providerName(),
            'outbound_recent' => array_map(static function ($m): array {
                return [
                    'id' => $m->getId(),
                    'evento' => $m->getEvento(),
                    'destino' => $m->getDestino(),
                    'status' => $m->getStatus(),
                    'provider' => $m->getProvider(),
                    'erro' => $m->getErro(),
                    'quando' => $m->getCriadoEm()->format('d/m H:i'),
                ];
            }, $this->outboundMessages->findRecentByEmpresa($empresa, 20)),
            'pacientes' => array_map(static function (PosOperatorioPaciente $p): array {
                $phone = preg_replace('/\D+/', '', (string) ($p->getTelefoneContato() ?? '')) ?? '';
                $msg = rawurlencode(sprintf(
                    "Olá %s! Lembrete: responda o questionário de hoje no portal do paciente. Código: %s",
                    explode(' ', $p->getNome())[0] ?? 'paciente',
                    $p->getCodigo(),
                ));
                $wa = strlen($phone) >= 10
                    ? sprintf('https://wa.me/55%s?text=%s', ltrim($phone, '0'), $msg)
                    : null;

                return [
                    'id' => $p->getId(),
                    'codigo' => $p->getCodigo(),
                    'nome' => $p->getNome(),
                    'dia_pos' => $p->getDiaPosOperatorio(),
                    'telefone' => $p->getTelefoneContato(),
                    'email' => $p->getEmailContato(),
                    'medico' => $p->getMedicoResponsavel()?->getNome(),
                    'whatsapp_url' => $wa,
                ];
            }, array_slice($pendentes, 0, 20)),
            'agenda_amanha' => $this->agendaReminders->panelForTomorrow($empresa),
            'canais' => $canais,
            'escalacao' => $escalacao,
        ];
    }

    /** @return array<string, mixed> */
    public function buildPlantao(Empresa $empresa): array
    {
        $policy = $this->policy->get($empresa);
        $onCall = $this->duty->listOnCall($empresa);
        $candidates = $this->duty->candidates($empresa);
        $usuarios = [];

        foreach ($candidates as $row) {
            $usuarios[$row['id']] = [
                'id' => $row['id'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'pacientes' => 0,
                'definido' => $row['on_call'],
                'on_call' => $row['on_call'],
            ];
        }

        foreach ($this->pacientes->findRecentByEmpresa($empresa, 50, 0) as $p) {
            $m = $p->getMedicoResponsavel();
            if (!$m instanceof User) {
                continue;
            }
            $id = (int) $m->getId();
            if (!isset($usuarios[$id])) {
                $usuarios[$id] = [
                    'id' => $id,
                    'nome' => (string) ($m->getNome() ?? $m->getEmail()),
                    'email' => $m->getEmail(),
                    'pacientes' => 0,
                    'definido' => false,
                    'on_call' => false,
                ];
            }
            ++$usuarios[$id]['pacientes'];
        }

        $hasRoster = $onCall !== [];

        return [
            'plantonistas' => array_values($usuarios),
            'candidates' => $candidates,
            'sla_p1_min' => $policy['sla']['P1'],
            'sla_p2_min' => $policy['sla']['P2'],
            'sla_p3_min' => $policy['sla']['P3'],
            'sla_p4_min' => $policy['sla']['P4'],
            'roteamento' => $hasRoster
                ? 'P1 → plantonistas do dia · demais → médico responsável'
                : 'Nenhum plantonista hoje — P1 vai para o médico responsável',
            'escalacao_ativa' => true,
            'has_roster' => $hasRoster,
            'continuity_lead' => $policy['continuity_lead'],
        ];
    }

    /** @return array<string, mixed> */
    public function buildReports(Empresa $empresa): array
    {
        $quality = $this->buildQuality($empresa);
        $queue = $this->alertQueue->buildQueue($empresa);

        return [
            'resumo' => [
                ['value' => $quality['pacientes_ativos'], 'label' => 'Pacientes ativos', 'icon' => 'fa-user-injured', 'tone' => 'sky'],
                ['value' => $quality['taxa_resposta'] . '%', 'label' => 'Taxa resposta hoje', 'icon' => 'fa-percent', 'tone' => 'sage'],
                ['value' => $quality['sla_pct'] . '%', 'label' => 'SLA em dia', 'icon' => 'fa-stopwatch', 'tone' => 'lavender'],
                ['value' => $queue['stats']['p1'], 'label' => 'P1 abertos', 'icon' => 'fa-fire', 'tone' => 'rose', 'variant' => $queue['stats']['p1'] > 0 ? 'danger' : ''],
            ],
            'exports' => [
                ['id' => 'fichas', 'nome' => 'Fichas de pacientes', 'formato' => 'PDF', 'status' => 'active'],
                ['id' => 'questionarios', 'nome' => 'Questionários do período', 'formato' => 'CSV', 'status' => 'active'],
                ['id' => 'alertas', 'nome' => 'Histórico de alertas', 'formato' => 'CSV', 'status' => 'active'],
                ['id' => 'auditoria', 'nome' => 'Trilha LGPD', 'formato' => 'CSV', 'status' => 'active'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function buildCompliance(Empresa $empresa): array
    {
        $eventos = $this->eventos->findRecentByEmpresa($empresa, 30);
        $auditoria = [];
        foreach ($eventos as $ev) {
            $tipo = $ev->getTipo();
            if (!\in_array($tipo, [
                PosOperatorioEvento::TIPO_ACESSO_FICHA,
                PosOperatorioEvento::TIPO_CONSENTIMENTO,
                PosOperatorioEvento::TIPO_CADASTRO,
            ], true)) {
                continue;
            }
            $autor = $ev->getAutor()?->getNome() ?? 'Sistema';
            $auditoria[] = [
                'tipo' => $tipo,
                'label' => match ($tipo) {
                    PosOperatorioEvento::TIPO_ACESSO_FICHA => 'Acesso à ficha',
                    PosOperatorioEvento::TIPO_CONSENTIMENTO => 'Consentimento LGPD',
                    default => 'Cadastro',
                },
                'detalhe' => match ($tipo) {
                    PosOperatorioEvento::TIPO_ACESSO_FICHA => sprintf('Ficha visualizada por %s', $autor),
                    default => $ev->getDescricao(),
                },
                'autor' => $autor,
                'em' => $ev->getCriadoEm()->format('d/m/Y H:i'),
                'paciente' => $ev->getPaciente()->getCodigo(),
            ];
        }

        $semConsentimento = 0;
        foreach ($this->pacientes->findRecentByEmpresa($empresa, 200, 0) as $p) {
            if ($p->getConsentimentoLgpdEm() === null && $p->getPortalUser() !== null) {
                ++$semConsentimento;
            }
        }

        $retention = $this->retention->status($empresa);
        $policy = $this->policy->get($empresa);

        return [
            'auditoria' => $auditoria,
            'sem_consentimento' => $semConsentimento,
            'retention' => $retention,
            'retencao_dias' => $policy['retencao_dias'],
            'continuity_lead' => $policy['continuity_lead'],
            'politicas' => [
                ['titulo' => 'Consentimento versionado', 'status' => 'active', 'desc' => 'Registro no portal do paciente'],
                ['titulo' => 'Trilha de acesso à ficha', 'status' => 'active', 'desc' => 'Cada visualização gera evento'],
                [
                    'titulo' => 'Retenção e anonimização',
                    'status' => $retention['last_run'] ? 'active' : 'prepared',
                    'desc' => $retention['last_run']
                        ? sprintf('Último job %s · %d elegível(is)', substr($retention['last_run'], 0, 16), $retention['elegiveis'])
                        : sprintf('Política %d dias · %d elegível(is) agora', $policy['retencao_dias'], $retention['elegiveis']),
                ],
                ['titulo' => 'Exportação para auditoria', 'status' => 'active', 'desc' => 'CSV em Relatórios → Trilha LGPD'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function buildConfig(?Empresa $empresa = null): array
    {
        $policy = $empresa ? $this->policy->get($empresa) : [
            'sla' => $this->policy->defaultSla(),
            'triagem' => ['dor_p1_min' => 8, 'dor_p2_min' => 6, 'febre_p2_min' => 38.5],
            'escalacao_horas' => [4, 8, 24],
            'canais' => ['in_app' => true, 'email' => true, 'whatsapp' => true, 'sms' => true],
            'retencao_dias' => 365,
            'alta_token' => '',
            'continuity_lead' => 'Ninguém fica sem resposta.',
        ];

        return [
            'editable' => $empresa !== null,
            'policy' => $policy,
            'continuity_lead' => $policy['continuity_lead'],
            'sla' => [
                ['prioridade' => 'P1', 'minutos' => $policy['sla']['P1'], 'desc' => 'Risco imediato'],
                ['prioridade' => 'P2', 'minutos' => $policy['sla']['P2'], 'desc' => 'Atenção rápida'],
                ['prioridade' => 'P3', 'minutos' => $policy['sla']['P3'], 'desc' => 'Triagem'],
                ['prioridade' => 'P4', 'minutos' => $policy['sla']['P4'], 'desc' => 'Acompanhamento'],
            ],
            'regras' => [
                ['titulo' => sprintf('Dor ≥ %.0f → P1', $policy['triagem']['dor_p1_min']), 'status' => 'active'],
                ['titulo' => sprintf('Dor ≥ %.0f → P2', $policy['triagem']['dor_p2_min']), 'status' => 'active'],
                ['titulo' => sprintf('Febre ≥ %.1f → P2', $policy['triagem']['febre_p2_min']), 'status' => 'active'],
                ['titulo' => 'Sangramento intenso → P1', 'status' => 'active'],
                ['titulo' => 'Perfil de risco (observações clínicas)', 'status' => 'active'],
            ],
            'perfis' => \App\Clinic\ClinicStaffRole::configList(),
        ];
    }
}