<?php

namespace App\Service\Ti;

use App\Config\TiModuleRegistry;
use App\Entity\Empresa;
use App\Platform\AiAssistant;
use App\Entity\TiChamado;
use App\Entity\User;
use App\Service\WorkspaceService;

final class TiService
{
    public function __construct(
        private WorkspaceService $workspace,
        private TiChamadoService $chamados,
        private TiHeliaService $helia,
        private TiInfraService $infra,
        private TiNovidadeService $novidades,
        private TiKbService $kb,
        private TiProblemaService $problemas,
        private TiPlaybookService $playbooks,
        private TiNotificationService $notifications,
        private TiIntegrationHealthService $integrationHealth,
        private TiExportService $export,
        private TiCatalogoService $catalogo,
    ) {}

    /** @return array<string, mixed> */
    public function getDashboard(User $user): array
    {
        $empresa = $this->requireEmpresa($user);
        $this->infra->ensureInitialized($empresa);
        $this->novidades->ensureInitialized($empresa);
        $this->kb->ensureInitialized($empresa);
        $this->playbooks->ensureInitialized($empresa);
        $this->integrationHealth->runChecks($empresa);
        $board = $this->chamados->board($empresa);
        $open = $this->chamados->countOpen($empresa);
        $pulse = $this->buildPulse($empresa, $board, $open);

        return [
            'ti_section' => 'overview',
            'ti_active_module' => null,
            'kpis' => $this->buildKpis($empresa, $pulse, $open, $board),
            'pulse' => $pulse,
            'noc_systems' => $this->buildNocSystems($empresa),
            'live_ops' => $this->chamados->liveOpsFeed($empresa),
            'ticket_board' => $board,
            'priority_queue' => $this->priorityQueue($board),
            'module_cards' => $this->moduleCards($empresa, $pulse, $open),
            'novidades' => $this->novidades->feedSlice($empresa, 3),
            'cortex_insights' => $this->cortexInsights($empresa),
            'noc_alerts' => $this->chamados->slaAlerts($empresa),
            'asset_stats' => $this->infra->assetStats($empresa),
            'license_alerts' => $this->infra->licenseAlerts($empresa),
            'license_renewal_alerts' => $this->infra->licenseRenewalAlerts($empresa),
            'integration_alerts' => $this->integrationHealth->alerts($empresa),
            'ti_notifications' => $this->notifications->unread($empresa, $user, 8),
            'ti_notifications_count' => $this->notifications->unreadCount($empresa, $user),
            'recurring_problems' => $this->problemas->detectRecurring($empresa),
            'integ_health_real' => true,
            ...$this->chamadoFormData(),
        ];
    }

    /** @param list<array<string, mixed>> $tickets */
    private function enrichTickets(array $tickets): array
    {
        return array_map(function (array $ticket): array {
            $ticket['helia_insight'] = $this->helia->ticketHasInsight($ticket);

            return $ticket;
        }, $tickets);
    }

    /** @return array<string, mixed> */
    public function ticketDetailContext(array $ticket, User $user): array
    {
        $empresa = $this->requireEmpresa($user);
        $slaRule = null;
        foreach (TiReferenceData::slaRules() as $rule) {
            if ($rule['priority'] === ($ticket['priority'] ?? '')) {
                $slaRule = $rule;
                break;
            }
        }

        return [
            'helia' => $this->helia->insightForTicket($ticket, $empresa),
            'playbook' => $this->playbooks->matchForTicket($ticket, $empresa),
            'suggested_technicians' => $this->chamados->suggestTechniciansRanked($empresa, (string) ($ticket['category'] ?? '')),
            'sla_rule' => $slaRule,
            'related_tickets' => $this->helia->relatedTickets($ticket, $empresa, $this->chamados),
            'technicians' => array_map(static fn (User $u) => [
                'id' => $u->getId(),
                'name' => $u->getNome() ?: $u->getEmail() ?: 'Usuário',
            ], $this->chamados->technicians($empresa)),
            'status_labels' => TiReferenceData::statusLabels(),
            'solicitante_status_options' => TiReferenceData::solicitanteReplyStatusOptions($ticket['status'] ?? null),
            'categories' => TiReferenceData::categories(),
            'category_label' => $this->categoryLabel((string) ($ticket['category'] ?? '')),
            'problemas' => $this->problemas->list($empresa),
            'similar_tickets' => $this->problemas->detectSimilarToTicket($empresa, $ticket),
        ];
    }

    private function categoryLabel(string $id): string
    {
        foreach (TiReferenceData::categories() as $cat) {
            if ($cat['id'] === $id) {
                return $cat['label'];
            }
        }

        return $id !== '' ? ucfirst($id) : '—';
    }

    /** @return array<string, mixed> */
    private function chamadoFormData(): array
    {
        return [
            'categories' => TiReferenceData::categories(),
            'priorities' => TiReferenceData::priorities(),
            'catalog' => TiReferenceData::catalog(),
            'sla_rules' => TiReferenceData::slaRules(),
            'impact_levels' => TiReferenceData::impactLevels(),
            'locations' => TiReferenceData::locations(),
            'contact_channels' => TiReferenceData::contactChannels(),
            'status_labels' => TiReferenceData::statusLabels(),
        ];
    }

    /** @return array<string, mixed> */
    public function getSection(string $section, User $user): array
    {
        $base = $this->getDashboard($user);
        $base['ti_section'] = $section;
        $empresa = $this->requireEmpresa($user);

        foreach (TiModuleRegistry::all() as $m) {
            if ($m['id'] === $section) {
                $base['ti_active_module'] = $m;
                break;
            }
        }

        return match ($section) {
            'chamados' => array_merge($base, [
                'ticket_board' => $this->chamados->board($empresa),
                'tickets' => $this->enrichTickets($this->chamados->allSorted($empresa)),
                'ticket_stats' => $this->chamados->stats($empresa),
                'priority_queue' => $this->priorityQueue($this->chamados->board($empresa)),
                'technicians' => array_map(static fn (User $u) => [
                    'id' => $u->getId(),
                    'name' => $u->getNome() ?: $u->getEmail() ?: 'Usuário',
                ], $this->chamados->technicians($empresa)),
            ]),
            'ativos' => array_merge($base, [
                'assets' => array_map(function (array $asset) use ($empresa): array {
                    $tickets = $this->chamados->ticketsForAsset($empresa, (int) ($asset['db_id'] ?? 0));

                    return array_merge($asset, [
                        'ticket_count' => \count($tickets),
                        'recent_tickets' => \array_slice($tickets, 0, 3),
                    ]);
                }, $this->infra->assets($empresa)),
                'asset_stats' => $this->infra->assetStats($empresa),
                'asset_dna_stages' => $this->infra->assetDnaStages($empresa),
            ]),
            'licencas' => array_merge($base, [
                'licenses' => $this->infra->licenses($empresa),
                'license_kpis' => $this->infra->licenseKpis($empresa),
                'license_renewal_alerts' => $this->infra->licenseRenewalAlerts($empresa),
            ]),
            'sla' => array_merge($base, [
                'sla_rules' => TiReferenceData::slaRules(),
                'sla_heatmap' => $this->chamados->slaHeatmap($empresa),
                'ticket_stats' => $this->chamados->stats($empresa),
            ]),
            'manutencoes' => array_merge($base, [
                'maintenances' => $this->infra->maintenances($empresa),
            ]),
            'catalogo' => array_merge($base, ['catalog' => $this->catalogo->list($empresa), 'kb_articles' => $this->kb->list($empresa)]),
            'kb' => array_merge($base, ['kb_articles' => $this->kb->list($empresa)]),
            'problemas' => array_merge($base, [
                'problemas' => $this->problemas->list($empresa),
                'recurring' => $this->problemas->detectRecurring($empresa),
            ]),
            'meus_chamados' => array_merge($base, [
                'my_tickets' => $this->chamados->myTickets($empresa, $user),
            ]),
            'integracoes' => array_merge($base, [
                'integrations' => $this->infra->integrations($empresa),
                'integration_logs' => $this->infra->integrationLogs($empresa),
            ]),
            'cortex' => array_merge($base, [
                'cortex_queue' => $this->chamados->cortexQueue($empresa, 12),
                'helia_suggestions' => $this->chamados->heliaSuggestions($empresa),
                'cortex_insights' => $this->cortexInsights($empresa),
                'playbooks' => $this->playbooks->list($empresa),
            ]),
            'analytics' => array_merge($base, [
                'analytics_volume' => $this->chamados->analyticsVolume($empresa),
                'sla_rules' => TiReferenceData::slaRules(),
                'workload_by_technician' => $this->chamados->workloadByTechnician($empresa),
                'mttr_by_category' => $this->chamados->mttrByCategory($empresa),
                'sla_heatmap_hour' => $this->chamados->slaHeatmapByHour($empresa),
                'p1_trend' => $this->chamados->p1Trend($empresa),
                'csat_metrics' => $this->chamados->csatMetrics($empresa),
            ]),
            'novidades' => array_merge($base, [
                'novidades_feed' => $this->novidades->feed($empresa),
            ]),
            default => $base,
        };
    }

    private function requireEmpresa(User $user): Empresa
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw new \RuntimeException('Selecione uma área de trabalho para acessar o Núcleo TI.');
        }

        return $empresa;
    }

    public function requireEmpresaForUser(User $user): Empresa
    {
        return $this->requireEmpresa($user);
    }

    public function exportAnalytics(Empresa $empresa): string
    {
        return $this->export->analyticsCsv($empresa);
    }

    /** @return array<string, mixed> */
    private function buildPulse(Empresa $empresa, array $board, int $open): array
    {
        $flat = [];
        foreach ($board as $items) {
            $flat = array_merge($flat, $items);
        }
        $p1 = \count(array_filter(
            $flat,
            static fn ($t) => ($t['priority'] ?? '') === 'P1' && ($t['status'] ?? '') !== TiChamado::STATUS_RESOLVIDO,
        ));

        $noc = $this->buildNocSystems($empresa);
        $systemsUp = \count(array_filter($noc, static fn ($s) => $s['status'] === 'operational'));

        return [
            'sla_compliance' => $this->chamados->slaCompliance($empresa),
            'open_tickets' => $open,
            'p1_active' => $p1,
            'mttr_hours' => $this->chamados->mttrHours($empresa),
            'systems_up' => $systemsUp,
            'systems_total' => \count($noc),
            'cortex_auto_rate' => $this->chamados->cortexAutoRate($empresa),
        ];
    }

    /**
     * @param array<string, mixed> $pulse
     * @param array<string, list<array<string, mixed>>> $board
     * @return list<array<string, mixed>>
     */
    private function buildKpis(Empresa $empresa, array $pulse, int $open, array $board): array
    {
        $mttr = $pulse['mttr_hours'] > 0 ? $pulse['mttr_hours'] . 'h' : '—';

        return [
            ['value' => $open, 'label' => 'Chamados abertos', 'sub' => $pulse['p1_active'] . ' críticos (P1)', 'icon' => 'fa-headset'],
            ['value' => $pulse['sla_compliance'] . '%', 'label' => 'SLA cumprido', 'sub' => 'Últimos 7 dias', 'icon' => 'fa-gauge-high', 'trend' => 'up'],
            ['value' => $mttr, 'label' => 'MTTR médio', 'sub' => 'Tempo até resolução', 'icon' => 'fa-clock'],
            ['value' => $pulse['systems_up'] . '/' . $pulse['systems_total'], 'label' => 'Sistemas OK', 'sub' => 'NOC monitor', 'icon' => 'fa-tower-broadcast'],
        ];
    }

    /** Public accessor for NOC systems and other consumers */
    public function getNocSystems(Empresa $empresa): array
    {
        return $this->buildNocSystems($empresa);
    }

    /** @return list<array<string, mixed>> */
    private function buildNocSystems(Empresa $empresa): array
    {
        $integrations = $this->infra->integrations($empresa);
        if ($integrations !== []) {
            return array_map(static function (array $int): array {
                $status = match ($int['status'] ?? 'healthy') {
                    'down' => 'incident',
                    'degraded' => 'degraded',
                    default => 'operational',
                };

                return [
                    'name' => $int['name'],
                    'status' => $status,
                    'uptime' => ($int['uptime'] ?? 99) . '%',
                    'latency' => $int['latency'] ?? '—',
                    'icon' => 'fa-plug',
                    'integ_conector_id' => $int['db_id'] ?? null,
                    'observatorio_url' => '/integracoes/observatorio',
                ];
            }, \array_slice($integrations, 0, 6));
        }

        // Fallback: try IntegConector from Núcleo Integrações
        $fromIntegConectores = $this->integrationHealth->buildNocFromIntegConectores($empresa);
        if ($fromIntegConectores !== []) {
            return $fromIntegConectores;
        }

        $openTickets = array_filter(
            $this->chamados->all($empresa),
            static fn ($t) => ($t['status'] ?? '') !== TiChamado::STATUS_RESOLVIDO,
        );

        $systems = [
            ['name' => 'ERP Unio', 'icon' => 'fa-database', 'categories' => ['sistema', 'integracao']],
            ['name' => 'Active Directory', 'icon' => 'fa-sitemap', 'categories' => ['acesso', 'seguranca']],
            ['name' => 'VPN Corporativa', 'icon' => 'fa-shield-halved', 'categories' => ['rede']],
            ['name' => 'E-mail Exchange', 'icon' => 'fa-envelope', 'categories' => ['email']],
            ['name' => 'Backup Center', 'icon' => 'fa-cloud-arrow-up', 'categories' => ['infra']],
            ['name' => 'API Gateway', 'icon' => 'fa-plug', 'categories' => ['integracao']],
        ];

        return array_map(function (array $sys) use ($openTickets): array {
            $p1 = false;
            $p2 = false;
            foreach ($openTickets as $t) {
                if (!\in_array($t['category'] ?? '', $sys['categories'], true)) {
                    continue;
                }
                if (($t['priority'] ?? '') === 'P1') {
                    $p1 = true;
                }
                if (($t['priority'] ?? '') === 'P2') {
                    $p2 = true;
                }
            }

            $status = $p1 ? 'incident' : ($p2 ? 'degraded' : 'operational');
            $uptime = $status === 'operational' ? '99.9%' : ($status === 'degraded' ? '98.5%' : '96.0%');

            return [
                'name' => $sys['name'],
                'status' => $status,
                'uptime' => $uptime,
                'latency' => $status === 'operational' ? '45ms' : ($status === 'degraded' ? '180ms' : '—'),
                'icon' => $sys['icon'],
                'integ_conector_id' => null,
                'observatorio_url' => null,
            ];
        }, $systems);
    }

    /** @return list<array<string, mixed>> */
    private function cortexInsights(Empresa $empresa): array
    {
        $stats = $this->chamados->stats($empresa);
        $auto = $this->chamados->cortexAutoRate($empresa);
        $alerts = $this->chamados->slaAlerts($empresa, 20);
        $critical = \count(array_filter($alerts, static fn ($a) => ($a['tone'] ?? '') === 'critical'));
        $unassigned = \count(array_filter($alerts, static fn ($a) => ($a['kind'] ?? '') === 'unassigned'));
        $pendingReview = \count($this->chamados->heliaSuggestions($empresa, 50));

        return [
            [
                'icon' => 'fa-brain',
                'title' => 'Triagem ' . AiAssistant::NAME,
                'summary' => $auto . '% dos chamados receberam pré-triagem automática.',
                'confidence' => min(98, max(60, $auto)),
                'action' => 'Ver Cortex',
            ],
            [
                'icon' => 'fa-triangle-exclamation',
                'title' => 'Alertas operacionais',
                'summary' => $critical . ' SLA crítico · ' . $unassigned . ' sem responsável · ' . $pendingReview . ' aguardando ' . AiAssistant::NAME . '.',
                'confidence' => $critical > 0 ? 95 : 78,
                'action' => 'NOC Center',
            ],
            [
                'icon' => 'fa-chart-line',
                'title' => 'Volume operacional',
                'summary' => $stats['total'] . ' chamados registrados · ' . $stats['resolvido'] . ' resolvidos.',
                'confidence' => 85,
                'action' => 'Analytics',
            ],
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $board
     * @return list<array<string, mixed>>
     */
    private function priorityQueue(array $board): array
    {
        $all = [];
        foreach ($board as $items) {
            $all = array_merge($all, $items);
        }
        usort($all, static function ($a, $b) {
            $pa = (int) substr((string) ($a['priority'] ?? 'P4'), 1);
            $pb = (int) substr((string) ($b['priority'] ?? 'P4'), 1);

            return $pa <=> $pb;
        });

        return array_slice(array_filter($all, static fn ($t) => ($t['status'] ?? '') !== TiChamado::STATUS_RESOLVIDO), 0, 5);
    }

    /** @return list<array<string, mixed>> */
    private function moduleCards(Empresa $empresa, array $pulse, int $open): array
    {
        $volume = $this->chamados->analyticsVolume($empresa);
        $lastMonth = $volume !== [] ? end($volume) : ['opened' => 0];
        $assetStats = $this->infra->assetStats($empresa);
        $licenseKpis = $this->infra->licenseKpis($empresa);

        return [
            ['id' => 'chamados', 'title' => 'Chamados', 'subtitle' => 'Service desk', 'icon' => 'fa-headset', 'metric' => $open . ' abertos', 'route' => 'app_ti_chamados', 'tone' => '#06B6D4'],
            ['id' => 'sla', 'title' => 'SLA', 'subtitle' => 'Metas operacionais', 'icon' => 'fa-gauge-high', 'metric' => $pulse['sla_compliance'] . '% OK', 'route' => 'app_ti_sla', 'tone' => '#8B5CF6'],
            ['id' => 'ativos', 'title' => 'Ativos', 'subtitle' => 'Inventário', 'icon' => 'fa-laptop', 'metric' => $assetStats['total'] . ' itens', 'route' => 'app_ti_ativos', 'tone' => '#6366F1'],
            ['id' => 'licencas', 'title' => 'Licenças', 'subtitle' => 'Software', 'icon' => 'fa-key', 'metric' => $licenseKpis['count'] . ' contratos', 'route' => 'app_ti_licencas', 'tone' => '#F59E0B'],
            ['id' => 'cortex', 'title' => 'Cortex Ops', 'subtitle' => 'Triagem IA', 'icon' => 'fa-brain', 'metric' => $pulse['cortex_auto_rate'] . '% auto', 'route' => 'app_ti_cortex', 'tone' => '#EC4899'],
            ['id' => 'integracoes', 'title' => 'Integrações', 'subtitle' => 'APIs & webhooks', 'icon' => 'fa-plug', 'metric' => $this->infra->integrationCount($empresa) . ' conectores', 'route' => 'app_ti_integracoes', 'tone' => '#10B981'],
            ['id' => 'manutencoes', 'title' => 'Manutenções', 'subtitle' => 'Janelas', 'icon' => 'fa-screwdriver-wrench', 'metric' => $this->infra->scheduledMaintenanceCount($empresa) . ' agendadas', 'route' => 'app_ti_manutencoes', 'tone' => '#64748B'],
            ['id' => 'analytics', 'title' => 'Analytics', 'subtitle' => 'Volume & tendências', 'icon' => 'fa-chart-line', 'metric' => ($lastMonth['opened'] ?? 0) . ' este mês', 'route' => 'app_ti_analytics', 'tone' => '#0EA5E9'],
        ];
    }
}
