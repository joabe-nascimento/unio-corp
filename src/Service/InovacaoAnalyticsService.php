<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\InovIdeia;
use App\Entity\User;
use App\Repository\DevMetaRepository;
use App\Repository\InovConexaoRepository;
use App\Repository\InovDecisaoRepository;
use App\Repository\InovIdeiaRepository;
use App\Repository\InovImpactEntryRepository;
use App\Repository\InovNovidadeRepository;
use App\Repository\InovTendenciaRepository;

/**
 * Métricas do Hub Inovação calculadas a partir dos dados persistidos.
 */
final class InovacaoAnalyticsService
{
    private const STAGE_ORDER = [
        InovIdeia::STAGE_IDEIA,
        InovIdeia::STAGE_HIPOTESE,
        InovIdeia::STAGE_POC,
        InovIdeia::STAGE_PILOTO,
        InovIdeia::STAGE_ESCALA,
    ];

    private const STAGE_LABELS = [
        InovIdeia::STAGE_IDEIA => 'Ideia',
        InovIdeia::STAGE_HIPOTESE => 'Hipótese',
        InovIdeia::STAGE_POC => 'POC',
        InovIdeia::STAGE_PILOTO => 'Piloto',
        InovIdeia::STAGE_ESCALA => 'Escala',
    ];

    public function __construct(
        private InovIdeiaRepository $ideiaRepo,
        private InovDecisaoRepository $decisaoRepo,
        private InovConexaoRepository $conexaoRepo,
        private InovImpactEntryRepository $impactRepo,
        private InovTendenciaRepository $tendenciaRepo,
        private InovNovidadeRepository $novidadeRepo,
        private DevMetaRepository $metaRepo,
        private InovacaoImpactService $impactService,
    ) {}

    /** @return list<array<string, mixed>> */
    public function getMaturityRadar(Empresa $empresa, User $user): array
    {
        $ideias = $this->ideiaRepo->findByEmpresa($empresa, true);
        $totalIdeias = max(1, \count($ideias));
        $decisoes = $this->decisaoRepo->findByEmpresa($empresa);
        $tendencias = $this->tendenciaRepo->findByEmpresa($empresa);
        $impactEntries = $this->impactRepo->findByEmpresa($empresa);

        $metaEmpresa = $user->getEmpresa() ?? $empresa;
        $metas = $this->metaRepo->findByEmpresa($metaEmpresa);

        if ($metas !== []) {
            $estrategia = (int) round(array_sum(array_map(
                static fn ($m) => $m->getProgressoPercent(),
                $metas
            )) / \count($metas));
        } else {
            $comHub = \count(array_filter($ideias, static fn (InovIdeia $i) => $i->getHubRelacionado() !== null));
            $estrategia = (int) round($comHub / $totalIdeias * 100);
        }

        $totalVotes = array_sum(array_map(static fn (InovIdeia $i) => $i->getVotos(), $ideias));
        $cultura = min(100, (int) round($totalVotes / $totalIdeias * 6 + min(\count($ideias), 20) * 2));

        $activeStages = 0;
        foreach (self::STAGE_ORDER as $stage) {
            if ($this->ideiaRepo->countByEmpresaAndEstagio($empresa, $stage) > 0) {
                ++$activeStages;
            }
        }
        $processo = min(100, (int) round(\count($decisoes) * 10 + $activeStages * 14));

        $tecnologia = 50;
        if ($tendencias !== []) {
            $tecnologia = (int) round(array_sum(array_map(
                static fn ($t) => $t->getValor(),
                $tendencias
            )) / \count($tendencias));
        }

        $owners = array_unique(array_filter(array_map(
            static fn (InovIdeia $i) => $i->getOwnerNome(),
            $ideias
        )));
        $pessoas = min(100, \count($owners) * 16);

        $withValue = \count(array_filter(
            $impactEntries,
            static fn ($e) => $e->getValorCapturado() && $e->getValorCapturado() !== '—'
        ));
        $impacto = $impactEntries === []
            ? min(100, (int) round(\count(array_filter(
                $ideias,
                static fn (InovIdeia $i) => $i->getEstagio() === InovIdeia::STAGE_ESCALA
            )) / $totalIdeias * 100))
            : (int) round($withValue / \count($impactEntries) * 100);

        return [
            ['id' => 'estrategia', 'label' => 'Estratégia', 'value' => $estrategia, 'hint' => 'Alinhamento com OKRs e visão de produto'],
            ['id' => 'cultura', 'label' => 'Cultura', 'value' => $cultura, 'hint' => 'Engajamento e participação nas ideias'],
            ['id' => 'processo', 'label' => 'Processo', 'value' => $processo, 'hint' => 'Pipeline estruturado e decisões registradas'],
            ['id' => 'tecnologia', 'label' => 'Tecnologia', 'value' => $tecnologia, 'hint' => 'Relevância das tendências monitoradas'],
            ['id' => 'pessoas', 'label' => 'Pessoas', 'value' => $pessoas, 'hint' => 'Capacidade e owners envolvidos'],
            ['id' => 'impacto', 'label' => 'Impacto mensurado', 'value' => $impacto, 'hint' => 'ROI e valor capturado nos experimentos'],
        ];
    }

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @param list<array<string,mixed>> $radar
     * @return array<string, mixed>
     */
    public function getPulse(Empresa $empresa, array $pipeline, array $radar): array
    {
        $score = (int) round(array_sum(array_column($radar, 'value')) / max(1, \count($radar)));
        $active = \count($pipeline['hipotese'] ?? [])
            + \count($pipeline['poc'] ?? [])
            + \count($pipeline['piloto'] ?? []);
        $totals = $this->impactService->computeTotals($this->impactRepo->findByEmpresa($empresa));

        return [
            'score' => $score,
            'boldness_label' => $score >= 75 ? 'Bold' : ($score >= 55 ? 'Exploratory' : 'Early'),
            'experiments_active' => $active,
            'experiments_all_time' => $this->ideiaRepo->countByEmpresa($empresa),
            'avg_cycle_days' => $this->ideiaRepo->averageCycleDays($empresa),
            'roi_total' => $totals['captured'],
        ];
    }

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return array<string, mixed>
     */
    public function getPipelineVelocity(Empresa $empresa, array $pipeline): array
    {
        $now = new \DateTimeImmutable();
        $thisWeek = $this->ideiaRepo->countUpdatedSince($empresa, $now->modify('-7 days'));
        $lastWeek = $this->ideiaRepo->countUpdatedBetween(
            $empresa,
            $now->modify('-14 days'),
            $now->modify('-7 days')
        );

        $delta = $thisWeek - $lastWeek;
        $throughput = $lastWeek > 0
            ? ($delta >= 0 ? '↑ ' : '↓ ') . abs((int) round($delta / $lastWeek * 100)) . ' %'
            : ($thisWeek > 0 ? '↑ 100 %' : '—');

        $conversion = [];
        $stageCounts = [];
        foreach (self::STAGE_ORDER as $stage) {
            $stageCounts[$stage] = \count($pipeline[$stage] ?? []);
        }

        for ($i = 0; $i < \count(self::STAGE_ORDER) - 1; ++$i) {
            $from = self::STAGE_ORDER[$i];
            $to = self::STAGE_ORDER[$i + 1];
            $fromCount = max(1, $stageCounts[$from] + $stageCounts[$to]);
            $rate = (int) round($stageCounts[$to] / $fromCount * 100);
            $conversion[] = [
                'from' => self::STAGE_LABELS[$from],
                'to' => self::STAGE_LABELS[$to],
                'rate' => min(100, $rate),
            ];
        }

        return [
            'this_week' => $thisWeek,
            'last_week' => $lastWeek,
            'avg_cycle' => $this->ideiaRepo->averageCycleDays($empresa),
            'throughput' => $throughput,
            'conversion' => $conversion,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function getImpactTimeline(Empresa $empresa): array
    {
        return $this->impactService->buildTimeline($this->impactRepo->findByEmpresa($empresa), $this->ideiaRepo->findByEmpresa($empresa));
    }

    /** @return list<array<string, mixed>> */
    public function getSerendipity(Empresa $empresa): array
    {
        $conexoes = $this->conexaoRepo->findByEmpresa($empresa);
        usort($conexoes, static fn ($a, $b) => $b->getSinergia() <=> $a->getSinergia());

        $items = [];
        foreach (array_slice($conexoes, 0, 4) as $conexao) {
            $items[] = [
                'title' => 'Hub ' . $conexao->getHub(),
                'combo' => $conexao->getHub() . ' × Inovação',
                'icon' => $conexao->getIcon(),
                'color' => $this->hubColor($conexao->getHub()),
                'prompt' => $conexao->getOportunidade(),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @param array<string, mixed> $pulse
     * @param list<array<string,mixed>> $radar
     * @return list<array<string, mixed>>
     */
    public function getInsights(Empresa $empresa, array $pipeline, array $pulse, array $radar): array
    {
        $insights = [];
        $backlogCount = \count($pipeline['ideia'] ?? []);

        if ($backlogCount > 2) {
            $oldest = $this->oldestBacklogDays($pipeline['ideia'] ?? []);
            $insights[] = [
                'title' => 'Backlog de ideias crescendo',
                'summary' => sprintf(
                    '%d ideias aguardam priorização%s.',
                    $backlogCount,
                    $oldest > 2 ? ' — a mais antiga há ' . $oldest . ' dias' : ''
                ),
                'category' => 'Backlog',
                'icon' => 'fa-triangle-exclamation',
                'priority' => $oldest > 5 ? 'high' : 'normal',
                'route' => 'app_inovacao_backlog',
            ];
        }

        foreach ($pipeline['piloto'] ?? [] as $item) {
            if (($item['progress'] ?? 0) >= 70) {
                $insights[] = [
                    'title' => 'Piloto pronto para decisão',
                    'summary' => sprintf('"%s" atingiu %d%% — considere Scale.', $item['title'], $item['progress']),
                    'category' => 'Decisão',
                    'icon' => 'fa-rocket',
                    'priority' => 'high',
                    'route' => 'app_inovacao_experimentos',
                ];
                break;
            }
        }

        $score = (int) ($pulse['score'] ?? 0);
        if ($score < 70) {
            $weak = array_filter($radar, static fn ($d) => $d['value'] < 60);
            if ($weak !== []) {
                $labels = implode('", "', array_column(array_slice($weak, 0, 2), 'label'));
                $insights[] = [
                    'title' => 'Maturidade abaixo da meta',
                    'summary' => sprintf('Dimensões "%s" puxam o índice para %d%%.', $labels, $score),
                    'category' => 'Maturidade',
                    'icon' => 'fa-chart-pie',
                    'priority' => 'normal',
                    'route' => 'app_inovacao_analytics',
                ];
            }
        }

        $topConexao = $this->conexaoRepo->findByEmpresa($empresa)[0] ?? null;
        if ($topConexao !== null && $topConexao->getSinergia() >= 70) {
            $insights[] = [
                'title' => 'Conexão estratégica detectada',
                'summary' => sprintf(
                    'Hub %s com %d%% de sinergia — %s',
                    $topConexao->getHub(),
                    $topConexao->getSinergia(),
                    $topConexao->getAcao()
                ),
                'category' => 'Serendipity',
                'icon' => 'fa-share-nodes',
                'priority' => 'normal',
                'route' => 'app_inovacao_conexoes',
            ];
        }

        return $insights;
    }

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @param array<string, mixed> $pulse
     * @return list<array<string, mixed>>
     */
    public function getLiveTicker(Empresa $empresa, array $pipeline, array $pulse): array
    {
        $items = [];

        foreach (array_slice($this->ideiaRepo->findRecentlyUpdated($empresa, 3), 0, 3) as $ideia) {
            $items[] = [
                'icon' => 'fa-arrow-trend-up',
                'text' => sprintf('"%s" atualizada — %d%% de progresso', $ideia->getTitulo(), $ideia->getProgresso()),
                'tone' => $ideia->getProgresso() >= 50 ? 'up' : 'neutral',
            ];
        }

        foreach (array_slice($this->novidadeRepo->findByEmpresa($empresa), 0, 2) as $novidade) {
            $items[] = [
                'icon' => $novidade->getIcon(),
                'text' => $novidade->getTitulo(),
                'tone' => $novidade->getVariant() === 'warning' ? 'warn' : 'neutral',
            ];
        }

        if (($pulse['roi_total'] ?? '—') !== '—') {
            $items[] = [
                'icon' => 'fa-coins',
                'text' => 'ROI capturado: ' . $pulse['roi_total'],
                'tone' => 'up',
            ];
        }

        $connCount = \count($this->conexaoRepo->findByEmpresa($empresa));
        if ($connCount > 0) {
            $items[] = [
                'icon' => 'fa-share-nodes',
                'text' => $connCount . ' conexão(ões) estratégica(s) mapeada(s)',
                'tone' => 'neutral',
            ];
        }

        if (\count($pipeline['escala'] ?? []) > 0) {
            $scaled = $pipeline['escala'][0];
            $items[] = [
                'icon' => 'fa-check-circle',
                'text' => sprintf('"%s" em escala no portfólio', $scaled['title']),
                'tone' => 'up',
            ];
        }

        return array_slice($items, 0, 8);
    }

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return array<string, list<array<string,mixed>>>
     */
    public function getDecisionBoard(Empresa $empresa, array $pipeline): array
    {
        $kill = array_map(
            fn (InovIdeia $i) => $this->ideiaToBoardItem($i),
            $this->ideiaRepo->findByEstagios($empresa, [InovIdeia::STAGE_KILL])
        );

        return [
            'kill' => $kill,
            'pivot' => $pipeline['hipotese'] ?? [],
            'scale' => $pipeline['piloto'] ?? [],
        ];
    }

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return list<array<string, mixed>>
     */
    public function getPortfolio(Empresa $empresa, array $pipeline): array
    {
        $impactByIdeia = [];
        foreach ($this->impactRepo->findByEmpresa($empresa) as $entry) {
            $ideia = $entry->getIdeia();
            if ($ideia !== null) {
                $impactByIdeia[$ideia->getId()] = $entry;
            }
        }

        $items = [];
        foreach ($pipeline['escala'] ?? [] as $item) {
            $impact = isset($item['db_id']) ? ($impactByIdeia[$item['db_id']] ?? null) : null;
            $since = '—';
            if (isset($item['db_id'])) {
                $entity = $this->ideiaRepo->findOneForEmpresa($empresa, $item['db_id']);
                if ($entity !== null) {
                    $since = $this->formatMonthYear($entity->getCriadoEm());
                }
            }

            $items[] = [
                'db_id' => $item['db_id'] ?? null,
                'id' => $item['id'],
                'title' => $item['title'],
                'summary' => $item['summary'],
                'owner' => $item['owner'],
                'tags' => $item['tags'],
                'metric' => $item['metric'] ?? '—',
                'roi' => $impact?->getRoi() ?? '—',
                'value' => $impact?->getValorCapturado() ?? '—',
                'since' => $since,
                'status' => $impact?->getStatus() ?? 'active',
            ];
        }

        return $items;
    }

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return list<array<string, mixed>>
     */
    public function getLabCanvas(array $pipeline): array
    {
        $canvases = [];
        $allActive = array_merge($pipeline['hipotese'] ?? [], $pipeline['poc'] ?? []);

        foreach ($allActive as $item) {
            $canvases[] = [
                'id' => $item['id'],
                'db_id' => $item['db_id'] ?? null,
                'title' => $item['title'],
                'stage' => $item['stage'],
                'problem' => $item['problem'] ?? 'Problema não documentado.',
                'hypothesis' => $item['hypothesis'] ?? 'Hipótese ainda não formulada.',
                'test' => $item['test'] ?? ($item['metric'] ?? 'Validação qualitativa'),
                'outcome' => ($item['progress'] ?? 0) >= 50 ? 'Validando positivamente' : 'Em coleta de dados',
                'progress' => $item['progress'],
                'owner' => $item['owner'],
                'tags' => $item['tags'],
                'days' => $item['days'],
                'rigor' => $item['rigor'] ?? ($item['stage'] === 'poc' ? 85 : 60),
            ];
        }

        return $canvases;
    }

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @param array<string, mixed> $pulse
     * @return array<string, mixed>
     */
    public function getLabMetrics(array $pipeline, array $pulse): array
    {
        $active = \count($pipeline['hipotese'] ?? []) + \count($pipeline['poc'] ?? []);
        $total = max(1, $this->countPipeline($pipeline));
        $scaled = \count($pipeline['escala'] ?? []);

        $rigors = [];
        foreach (array_merge($pipeline['hipotese'] ?? [], $pipeline['poc'] ?? []) as $item) {
            if (isset($item['rigor'])) {
                $rigors[] = (int) $item['rigor'];
            }
        }

        return [
            'active' => $active,
            'pocs' => \count($pipeline['poc'] ?? []),
            'success_rate' => (int) round($scaled / $total * 100),
            'avg_days' => (int) $pulse['avg_cycle_days'],
            'rigor_index' => $rigors !== [] ? (int) round(array_sum($rigors) / \count($rigors)) : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function ideiaToBoardItem(InovIdeia $ideia): array
    {
        return [
            'db_id' => $ideia->getId(),
            'id' => $ideia->getCodigo(),
            'title' => $ideia->getTitulo(),
            'summary' => $ideia->getResumo() ?? '',
            'owner' => $ideia->getOwnerNome() ?? '—',
            'progress' => $ideia->getProgresso(),
            'metric' => $ideia->getMetrica(),
        ];
    }

    /** @param list<array<string,mixed>> $ideas */
    private function oldestBacklogDays(array $ideas): int
    {
        if ($ideas === []) {
            return 0;
        }

        return max(array_map(static fn ($i) => (int) ($i['days'] ?? 0), $ideas));
    }

    private function hubColor(string $hub): string
    {
        return match (strtolower($hub)) {
            'cortex' => '#6366F1',
            'rh' => '#0EA5E9',
            'talentos' => '#F59E0B',
            'academy' => '#10B981',
            'esg' => '#14B8A6',
            'analytics' => '#EC4899',
            default => '#6366F1',
        };
    }

    private function formatMonthYear(\DateTimeImmutable $date): string
    {
        $months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        return $months[(int) $date->format('n') - 1] . '/' . $date->format('Y');
    }

    /** @param array<string, list<array<string,mixed>>> $pipeline */
    private function countPipeline(array $pipeline): int
    {
        return array_sum(array_map('count', $pipeline));
    }
}
