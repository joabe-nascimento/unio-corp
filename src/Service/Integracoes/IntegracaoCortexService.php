<?php

namespace App\Service\Integracoes;

use App\Config\IntegracaoFlowRegistry;
use App\Entity\Empresa;
use App\Entity\IntegCausalTrace;
use App\Entity\IntegConector;
use App\Entity\IntegSchemaDrift;
use App\Entity\IntegShadowRun;
use App\Repository\IntegCausalTraceRepository;
use App\Repository\IntegConectorRepository;
use App\Repository\IntegLogRepository;
use App\Repository\IntegMapeamentoRepository;
use App\Repository\IntegSchemaDriftRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoCortexService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegCausalTraceRepository $traceRepo,
        private IntegConectorRepository $conectorRepo,
        private IntegLogRepository $logRepo,
        private IntegSchemaDriftRepository $driftRepo,
        private IntegMapeamentoRepository $mapRepo,
        private IntegracaoShadowReplayService $shadow,
        private IntegracaoSeedService $seed,
    ) {}

    public function ensureTraces(Empresa $empresa): void
    {
        if ($this->traceRepo->countForEmpresa($empresa) === 0) {
            $this->seed->seedCausalTraces($empresa);
        }
        $this->seed->seedCortexExtras($empresa);
    }

    /** @return array<string, mixed>|null */
    public function getPreview(Empresa $empresa): ?array
    {
        $this->ensureTraces($empresa);
        $traces = array_map(static fn (IntegCausalTrace $t) => $t->toArray(), $this->traceRepo->findForEmpresa($empresa));
        if ($traces === []) {
            return null;
        }

        $atRisk = \count(array_filter($traces, static fn ($t) => \in_array($t['status'], ['degraded', 'failed'], true)));

        return [
            'fluxos' => \count($traces),
            'em_risco' => $atRisk,
            'drifts_abertos' => $this->driftRepo->countOpenForEmpresa($empresa),
            'trace_destaque' => $traces[0],
        ];
    }

    /** @return array<string, mixed> */
    public function getObservatorio(Empresa $empresa): array
    {
        $this->ensureTraces($empresa);
        $this->syncTraceHealthFromConectores($empresa);

        $traces = array_map(static fn (IntegCausalTrace $t) => $t->toArray(), $this->traceRepo->findForEmpresa($empresa));
        $selected = $traces[0] ?? null;

        $critical = \count(array_filter($traces, static fn ($t) => \in_array($t['status'], ['degraded', 'failed'], true)));
        $avgReliability = $traces === []
            ? 100
            : round(array_sum(array_column($traces, 'confiabilidade')) / \count($traces), 1);

        $totalImpactTickets = 0;
        foreach ($traces as $trace) {
            $totalImpactTickets += (int) ($trace['impacto']['tickets_ti'] ?? 0);
        }

        $drifts = array_map(static fn (IntegSchemaDrift $d) => $d->toArray(), $this->driftRepo->findForEmpresa($empresa));
        $openDrifts = \count(array_filter($drifts, static fn ($d) => !$d['resolvido']));

        return [
            'cortex_kpis' => [
                ['value' => \count($traces), 'label' => 'Fluxos monitorados', 'icon' => 'fa-diagram-project'],
                ['value' => $critical, 'label' => 'Fluxos em risco', 'icon' => 'fa-triangle-exclamation', 'trend' => $critical > 0 ? 'down' : null],
                ['value' => $avgReliability . '%', 'label' => 'Confiabilidade média', 'icon' => 'fa-shield-heart'],
                ['value' => $openDrifts, 'label' => 'Drifts de schema', 'icon' => 'fa-code-branch'],
                ['value' => $totalImpactTickets, 'label' => 'Chamados TI gerados', 'icon' => 'fa-headset'],
            ],
            'causal_traces' => $traces,
            'selected_trace' => $selected,
            'selected_flow_key' => $selected['flow_key'] ?? null,
            'flow_registry' => IntegracaoFlowRegistry::all(),
            'hub_labels' => IntegracaoFlowRegistry::hubLabels(),
            'hub_icons' => IntegracaoFlowRegistry::hubIcons(),
            'causal_mesh' => $this->buildMesh($traces),
            'predictive_alerts' => $this->buildPredictiveAlerts($traces),
            'schema_drifts' => $drifts,
            'auto_playbooks' => $this->buildAutoPlaybooks($traces, $drifts),
            'shadow_lab' => [
                'mapeamentos' => array_map(static fn ($m) => $m->toArray(), $this->mapRepo->findForEmpresa($empresa)),
                'runs' => $this->shadow->listRecentRuns($empresa, 5),
                'periodos' => [
                    ['value' => 1, 'label' => '24 horas'],
                    ['value' => 7, 'label' => '7 dias'],
                    ['value' => 14, 'label' => '14 dias'],
                    ['value' => 30, 'label' => '30 dias'],
                ],
            ],
            'recent_causal_events' => $this->buildRecentEvents($empresa, $traces),
            'reliability_trends' => $this->buildReliabilityTrends($traces),
        ];
    }

    /**
     * @param list<array<string, mixed>> $traces
     * @return array<string, mixed>
     */
    private function buildMesh(array $traces): array
    {
        $hubs = [];
        $edgeMap = [];

        foreach ($traces as $trace) {
            $prevHub = null;
            $prevLatency = null;

            foreach ($trace['nos'] as $node) {
                $hub = $node['hub'] ?? 'integracoes';
                if (!isset($hubs[$hub])) {
                    $hubs[$hub] = [
                        'id' => $hub,
                        'label' => IntegracaoFlowRegistry::hubLabels()[$hub] ?? $hub,
                        'icon' => IntegracaoFlowRegistry::hubIcons()[$hub] ?? 'fa-circle',
                        'fluxos' => 0,
                        'nos_ok' => 0,
                        'nos_warn' => 0,
                        'nos_error' => 0,
                        'confiabilidade_sum' => 0.0,
                        'confiabilidade_count' => 0,
                        'status' => 'healthy',
                        'flow_keys' => [],
                    ];
                }

                $hubs[$hub]['fluxos']++;
                if (!\in_array($trace['flow_key'], $hubs[$hub]['flow_keys'], true)) {
                    $hubs[$hub]['flow_keys'][] = $trace['flow_key'];
                    $hubs[$hub]['confiabilidade_sum'] += (float) $trace['confiabilidade'];
                    $hubs[$hub]['confiabilidade_count']++;
                }

                $nodeStatus = $node['status'] ?? 'ok';
                if ($nodeStatus === 'error') {
                    $hubs[$hub]['nos_error']++;
                    $hubs[$hub]['status'] = 'failed';
                } elseif ($nodeStatus === 'warn') {
                    $hubs[$hub]['nos_warn']++;
                    if ($hubs[$hub]['status'] === 'healthy') {
                        $hubs[$hub]['status'] = 'degraded';
                    }
                } else {
                    $hubs[$hub]['nos_ok']++;
                }

                if ($prevHub !== null && $prevHub !== $hub) {
                    $edgeKey = $prevHub . '→' . $hub;
                    if (!isset($edgeMap[$edgeKey])) {
                        $edgeMap[$edgeKey] = [
                            'id' => $edgeKey,
                            'from' => $prevHub,
                            'to' => $hub,
                            'flows' => [],
                            'flow_keys_seen' => [],
                            'status' => 'healthy',
                            'eventos' => 0,
                            'latency_ms_sum' => 0,
                            'latency_ms_count' => 0,
                        ];
                    }
                    if (!\in_array($trace['flow_key'], $edgeMap[$edgeKey]['flow_keys_seen'], true)) {
                        $edgeMap[$edgeKey]['flow_keys_seen'][] = $trace['flow_key'];
                        $edgeMap[$edgeKey]['flows'][] = [
                            'flow_key' => $trace['flow_key'],
                            'titulo' => $trace['titulo'],
                            'status' => $trace['status'],
                            'latency' => $node['latency'] ?? $prevLatency ?? '—',
                        ];
                    }
                    $edgeMap[$edgeKey]['eventos']++;
                    $latMs = $this->parseLatencyMs($node['latency'] ?? $prevLatency ?? null);
                    if ($latMs !== null) {
                        $edgeMap[$edgeKey]['latency_ms_sum'] += $latMs;
                        $edgeMap[$edgeKey]['latency_ms_count']++;
                    }
                    if ($trace['status'] === 'failed') {
                        $edgeMap[$edgeKey]['status'] = 'failed';
                    } elseif ($trace['status'] === 'degraded' && $edgeMap[$edgeKey]['status'] !== 'failed') {
                        $edgeMap[$edgeKey]['status'] = 'degraded';
                    }
                }

                $prevHub = $hub;
                $prevLatency = $node['latency'] ?? null;
            }
        }

        $hubOrder = ['rh', 'integracoes', 'ti', 'inovacao'];
        $orderedHubs = [];
        foreach ($hubOrder as $id) {
            if (!isset($hubs[$id])) {
                continue;
            }
            $h = $hubs[$id];
            $h['confiabilidade'] = $h['confiabilidade_count'] > 0
                ? round($h['confiabilidade_sum'] / $h['confiabilidade_count'], 1)
                : 100.0;
            unset($h['confiabilidade_sum'], $h['confiabilidade_count']);
            $orderedHubs[] = $h;
        }
        foreach ($hubs as $id => $h) {
            if (\in_array($id, $hubOrder, true)) {
                continue;
            }
            $h['confiabilidade'] = $h['confiabilidade_count'] > 0
                ? round($h['confiabilidade_sum'] / $h['confiabilidade_count'], 1)
                : 100.0;
            unset($h['confiabilidade_sum'], $h['confiabilidade_count']);
            $orderedHubs[] = $h;
        }

        $edges = [];
        foreach ($edgeMap as $edge) {
            unset($edge['flow_keys_seen']);
            $edge['avg_latency'] = $edge['latency_ms_count'] > 0
                ? round($edge['latency_ms_sum'] / $edge['latency_ms_count']) . 'ms'
                : null;
            unset($edge['latency_ms_sum'], $edge['latency_ms_count']);
            $edges[] = $edge;
        }

        return [
            'hubs' => $orderedHubs,
            'edges' => $edges,
            'stats' => [
                'hubs_ativos' => \count($orderedHubs),
                'links_ativos' => \count($edgeMap),
                'fluxos_totais' => \count($traces),
            ],
        ];
    }

    private function parseLatencyMs(?string $latency): ?int
    {
        if ($latency === null || $latency === '' || $latency === '—') {
            return null;
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*ms/i', $latency, $m)) {
            return (int) round((float) $m[1]);
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*s/i', $latency, $m)) {
            return (int) round((float) $m[1] * 1000);
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $traces
     * @return list<array<string, mixed>>
     */
    private function buildPredictiveAlerts(array $traces): array
    {
        $alerts = [];
        foreach ($traces as $trace) {
            $prev = $trace['previsao'] ?? [];
            if ($prev === []) {
                continue;
            }
            if (($prev['risco_48h'] ?? 0) >= 50) {
                $alerts[] = [
                    'flow_key' => $trace['flow_key'],
                    'titulo' => $trace['titulo'],
                    'probabilidade' => $prev['risco_48h'],
                    'mensagem' => $prev['mensagem'] ?? 'Risco elevado detectado',
                    'acao' => $prev['acao_sugerida'] ?? 'Revisar conector',
                    'variant' => ($prev['risco_48h'] ?? 0) >= 75 ? 'danger' : 'warning',
                ];
            }
        }

        usort($alerts, static fn ($a, $b) => ($b['probabilidade'] ?? 0) <=> ($a['probabilidade'] ?? 0));

        return $alerts;
    }

    /**
     * @param list<array<string, mixed>> $traces
     * @return list<array<string, mixed>>
     */
    private function buildReliabilityTrends(array $traces): array
    {
        $days = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
        $out = [];
        foreach ($traces as $trace) {
            $series = $trace['tendencia'] ?? [];
            if ($series === []) {
                continue;
            }
            $points = [];
            foreach ($series as $i => $val) {
                $points[] = ['day' => $days[$i] ?? ('D' . ($i + 1)), 'value' => $val];
            }
            $out[] = [
                'flow_key' => $trace['flow_key'],
                'titulo' => $trace['titulo'],
                'points' => $points,
                'delta' => end($series) - ($series[0] ?? end($series)),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $traces
     * @param list<array<string, mixed>> $drifts
     * @return list<array<string, mixed>>
     */
    private function buildAutoPlaybooks(array $traces, array $drifts): array
    {
        $books = [];

        foreach ($traces as $trace) {
            if (!\in_array($trace['status'], ['degraded', 'failed'], true)) {
                continue;
            }
            $steps = ['Verificar saúde dos conectores do fluxo "' . $trace['titulo'] . '"'];
            foreach ($trace['nos'] as $node) {
                if (($node['status'] ?? '') === 'error') {
                    $steps[] = 'Isolar nó com falha: ' . $node['label'] . ' — ' . ($node['detail'] ?? '');
                }
            }
            if (($trace['impacto']['chamados'] ?? []) !== []) {
                $steps[] = 'Correlacionar com chamados: ' . implode(', ', $trace['impacto']['chamados']);
            }
            $steps[] = 'Executar Shadow Replay antes de alterar mapeamentos';
            $steps[] = 'Monitorar por 24h após correção';

            $books[] = [
                'id' => 'pb_' . $trace['flow_key'],
                'titulo' => 'Runbook — ' . $trace['titulo'],
                'origem' => 'Gerado automaticamente pelo Cortex',
                'severidade' => $trace['status'] === 'failed' ? 'critica' : 'media',
                'steps' => $steps,
            ];
        }

        foreach ($drifts as $drift) {
            if ($drift['resolvido']) {
                continue;
            }
            $books[] = [
                'id' => 'pb_drift_' . $drift['db_id'],
                'titulo' => 'Corrigir drift — ' . $drift['campo_origem'],
                'origem' => 'Schema Drift Guard',
                'severidade' => $drift['severidade'],
                'steps' => [
                    'Campo origem UNio: ' . $drift['campo_origem'],
                    'Esperado externo: ' . $drift['campo_esperado'],
                    'Detectado: ' . $drift['campo_detectado'],
                    $drift['sugestao'],
                    'Validar com Shadow Replay antes de publicar',
                ],
            ];
        }

        return $books;
    }

    private function syncTraceHealthFromConectores(Empresa $empresa): void
    {
        $conectores = $this->conectorRepo->findForEmpresa($empresa);
        $byCatalog = [];
        foreach ($conectores as $c) {
            $byCatalog[$c->getCatalogoId()] = $c;
        }

        $dirty = false;
        foreach ($this->traceRepo->findForEmpresa($empresa) as $trace) {
            $def = IntegracaoFlowRegistry::find($trace->getFlowKey());
            if ($def === null) {
                continue;
            }

            $worst = IntegConector::HEALTH_HEALTHY;
            foreach ($def['conectores'] as $catId) {
                $con = $byCatalog[$catId] ?? null;
                if ($con === null) {
                    continue;
                }
                $h = $con->getHealth();
                if ($h === IntegConector::HEALTH_DOWN) {
                    $worst = IntegConector::HEALTH_DOWN;
                    break;
                }
                if ($h === IntegConector::HEALTH_DEGRADED) {
                    $worst = IntegConector::HEALTH_DEGRADED;
                }
            }

            $status = match ($worst) {
                IntegConector::HEALTH_DOWN => IntegCausalTrace::STATUS_FAILED,
                IntegConector::HEALTH_DEGRADED => IntegCausalTrace::STATUS_DEGRADED,
                default => $trace->getStatus(),
            };

            if ($trace->getFlowKey() === 'esocial_compliance') {
                $status = IntegCausalTrace::STATUS_DEGRADED;
            }
            if ($trace->getFlowKey() === 'folha_totvs_sync' && $worst === IntegConector::HEALTH_HEALTHY) {
                $status = IntegCausalTrace::STATUS_HEALTHY;
            }

            if ($trace->getStatus() !== $status) {
                $trace->setStatus($status);
                $dirty = true;
            }
        }

        if ($dirty) {
            $this->em->flush();
        }
    }

    /**
     * @param list<array<string, mixed>> $traces
     * @return list<array<string, mixed>>
     */
    private function buildRecentEvents(Empresa $empresa, array $traces): array
    {
        $events = [];
        foreach ($traces as $trace) {
            $failedNode = null;
            foreach ($trace['nos'] as $node) {
                if (($node['status'] ?? '') === 'error') {
                    $failedNode = $node;
                    break;
                }
                if (($node['status'] ?? '') === 'warn' && $failedNode === null) {
                    $failedNode = $node;
                }
            }
            $events[] = [
                'flow_key' => $trace['flow_key'],
                'flow_titulo' => $trace['titulo'],
                'time' => $trace['ultimo_evento'],
                'level' => $trace['status'] === 'failed' ? 'error' : ($trace['status'] === 'degraded' ? 'warn' : 'info'),
                'message' => $failedNode
                    ? ($failedNode['label'] . ' — ' . ($failedNode['detail'] ?? ''))
                    : 'Fluxo concluído sem anomalias',
            ];
        }

        foreach ($this->logRepo->findRecentForEmpresa($empresa, 4) as $log) {
            $events[] = [
                'flow_key' => null,
                'flow_titulo' => $log->getOrigem(),
                'time' => $log->getCriadoEm()->format('d/m H:i'),
                'level' => $log->getNivel(),
                'message' => $log->getMensagem(),
            ];
        }

        return \array_slice($events, 0, 8);
    }

    /** @return array<string, mixed> */
    public function simularImpacto(Empresa $empresa, int $conectorId): array
    {
        $conector = $this->conectorRepo->findOneForEmpresa($empresa, $conectorId);
        if (!$conector) {
            return ['error' => 'Conector não encontrado'];
        }

        $traces = array_map(static fn (IntegCausalTrace $t) => $t->toArray(), $this->traceRepo->findForEmpresa($empresa));
        $catalogoId = $conector->getCatalogoId();
        $nomeConector = $conector->getNome();

        $fluxosAfetados = [];
        $usuariosAfetados = 0;
        $chamadosEstimados = 0;

        foreach ($traces as $trace) {
            foreach ($trace['nos'] ?? [] as $no) {
                $noLabel = mb_strtolower($no['label'] ?? '');
                if (str_contains($noLabel, mb_strtolower($catalogoId ?? ''))
                    || str_contains($noLabel, mb_strtolower($nomeConector))) {
                    $key = $trace['flow_key'];
                    if (!isset($fluxosAfetados[$key])) {
                        $fluxosAfetados[$key] = [
                            'flow_key' => $trace['flow_key'],
                            'titulo' => $trace['titulo'],
                            'confiabilidade' => $trace['confiabilidade'],
                            'hub' => $no['hub'] ?? '—',
                        ];
                        $usuariosAfetados += (int) ($trace['impacto']['usuarios_afetados'] ?? random_int(5, 50));
                        $chamadosEstimados += (int) ($trace['impacto']['tickets_ti'] ?? random_int(1, 5));
                    }
                    break;
                }
            }
        }

        if ($fluxosAfetados === []) {
            $hubsAlvo = $conector->getHubsAlvo();
            foreach ($traces as $trace) {
                $nosHubs = array_unique(array_column($trace['nos'] ?? [], 'hub'));
                if (array_intersect($hubsAlvo, $nosHubs)) {
                    $key = $trace['flow_key'];
                    if (!isset($fluxosAfetados[$key])) {
                        $fluxosAfetados[$key] = [
                            'flow_key' => $trace['flow_key'],
                            'titulo' => $trace['titulo'],
                            'confiabilidade' => $trace['confiabilidade'],
                            'hub' => null,
                        ];
                        $usuariosAfetados += random_int(10, 80);
                        $chamadosEstimados += random_int(1, 8);
                    }
                }
            }
        }

        $total = \count($fluxosAfetados);
        $riscoGlobal = $total === 0 ? 0 : min(100, $total * 20 + random_int(5, 20));

        return [
            'conector' => $nomeConector,
            'conector_id' => $conectorId,
            'fluxos_afetados' => array_values($fluxosAfetados),
            'total_fluxos' => $total,
            'usuarios_estimados' => $usuariosAfetados,
            'chamados_estimados' => $chamadosEstimados,
            'risco_global_pct' => $riscoGlobal,
            'recomendacao' => $riscoGlobal > 60
                ? 'Alto risco. Agende uma janela de manutenção aprovada antes de pausar.'
                : ($riscoGlobal > 30 ? 'Risco moderado. Comunique as equipes afetadas.' : 'Baixo risco. Pausa segura.'),
        ];
    }
}
