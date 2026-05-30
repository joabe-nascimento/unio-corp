<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\DevMetaRepository;
use App\Repository\DevProjetoRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\InovDecisaoRepository;
use App\Repository\InovIdeiaRepository;

/**
 * Hub Inovação — orquestração de dados (DB + sinais reais de RH, Projetos e OKRs).
 */
final class InovacaoService
{
    public function __construct(
        private FuncionarioRepository $funcionarioRepo,
        private DevProjetoRepository $projetoRepo,
        private DevMetaRepository $metaRepo,
        private InovIdeiaRepository $ideiaRepo,
        private InovDecisaoRepository $decisaoRepo,
        private InovacaoIdeiaService $ideiaService,
        private InovacaoSeedService $seedService,
        private InovacaoConexaoService $conexaoService,
        private InovacaoImpactService $impactService,
        private InovacaoTendenciaService $tendenciaService,
        private InovacaoNovidadeService $novidadeService,
        private InovacaoAnalyticsService $analytics,
        private WorkspaceService $workspace,
    ) {}

    /** @return array<string, mixed> */
    public function getDashboard(User $user): array
    {
        $empresa = $this->requireEmpresa($user);
        $pipeline = $this->getPipeline($user);
        $radar    = $this->analytics->getMaturityRadar($empresa, $user);
        $funnel   = $this->getFunnel($pipeline);
        $pulse    = $this->analytics->getPulse($empresa, $pipeline, $radar);

        return [
            'inov_section'        => 'overview',
            'inov_modules'        => $this->getModules(),
            'inov_active_module'  => null,
            'kpis'                => $this->getKpis($pipeline, $pulse),
            'pulse'               => $pulse,
            'radar'               => $radar,
            'funnel'              => $funnel,
            'pipeline'            => $pipeline,
            'pipeline_total'      => $this->countPipeline($pipeline),
            'stage_chips'         => $this->getStageChips($pipeline),
            'flow_stages'         => $this->getFlowStages($pipeline),
            'module_cards'        => $this->getModuleCards($pipeline, $user, $pulse),
            'experiment_week'     => $this->getExperimentWeek($pipeline),
            'serendipity'         => $this->analytics->getSerendipity($empresa),
            'insights'            => $this->analytics->getInsights($empresa, $pipeline, $pulse, $radar),
            'live_ticker'         => $this->analytics->getLiveTicker($empresa, $pipeline, $pulse),
            'real_signals'        => $this->getRealSignals($user),
            'okr_alignment'       => $this->getOkrAlignment($user),
            'novidades'           => $this->getNovidades($user),
        ];
    }

    /** @return array<string, mixed> */
    public function getSection(string $section, User $user): array
    {
        $base = $this->getDashboard($user);
        $base['inov_section'] = $section;

        $modules = $this->getModules();
        foreach ($modules as $m) {
            if ($m['id'] === $section) {
                $base['inov_active_module'] = $m;
                break;
            }
        }

        if ($section === 'impact') {
            $base['module_payload']  = $this->getImpactPayload($user);
            $base['impact_timeline'] = $this->analytics->getImpactTimeline($this->requireEmpresa($user));
        }

        if ($section === 'laboratorio') {
            $base['lab_canvas']  = $this->analytics->getLabCanvas($base['pipeline']);
            $base['lab_metrics'] = $this->analytics->getLabMetrics($base['pipeline'], $base['pulse']);
        }

        if ($section === 'backlog') {
            $base['backlog_matrix']     = $this->getBacklogMatrix($base['pipeline']);
            $base['backlog_categories'] = $this->getBacklogCategories($base['pipeline']);
        }

        if ($section === 'tendencias') {
            $base['trend_radar'] = $this->getTrendRadar($user);
        }

        if ($section === 'portfolio') {
            $empresa = $this->requireEmpresa($user);
            $base['portfolio_items'] = $this->analytics->getPortfolio($empresa, $base['pipeline']);
            $scaledIds = array_values(array_filter(array_column($base['portfolio_items'], 'db_id')));
            $entries = array_values(array_filter(
                $this->impactService->listForEmpresa($empresa),
                static fn ($e) => $e->getIdeia() !== null && \in_array($e->getIdeia()->getId(), $scaledIds, true),
            ));
            $base['portfolio_totals'] = $this->impactService->computeTotals($entries);
        }

        if ($section === 'novidades') {
            $base['novidades_feed'] = $this->getNovidades($user);
        }

        if ($section === 'experimentos') {
            $base['decision_board']   = $this->analytics->getDecisionBoard($this->requireEmpresa($user), $base['pipeline']);
            $base['decision_history'] = $this->getDecisionHistory($user);
        }

        if ($section === 'conexoes') {
            $base['network_nodes'] = $this->getNetworkNodes($user);
        }

        if ($section === 'pipeline') {
            $base['pipeline_velocity'] = $this->analytics->getPipelineVelocity($this->requireEmpresa($user), $base['pipeline']);
            $base['pipeline_blocked']  = $this->getPipelineBlocked($base['pipeline']);
        }

        if ($section === 'nova_ideia') {
            $base['inov_active_module'] = ['id' => 'nova_ideia', 'label' => 'Nova Ideia', 'icon' => 'fa-plus-circle', 'route' => 'app_inovacao_nova_ideia', 'subtitle' => 'Registrar nova ideia no pipeline'];
            $base['idea_categories']    = $this->getIdeaCategories();
            $base['idea_hubs']          = $this->getIdeaHubs();
        }

        return $base;
    }

    // ── Pipeline (DB) ────────────────────────────────────────────────────────

    private function requireEmpresa(User $user): Empresa
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw new \RuntimeException('Selecione uma área de trabalho para acessar o Hub Inovação.');
        }
        $this->seedService->seedDemoData($empresa, $user);

        return $empresa;
    }

    /** @return array<string, list<array<string,mixed>>> */
    private function getPipeline(User $user): array
    {
        $empresa = $this->requireEmpresa($user);
        $entities = $this->ideiaRepo->findActivePipeline($empresa);

        return $this->ideiaService->pipelineFromEntities($entities);
    }

    // ── Funil ────────────────────────────────────────────────────────────────

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return list<array<string, mixed>>
     */
    private function getFunnel(array $pipeline): array
    {
        $map = ['ideia' => 'Ideias', 'hipotese' => 'Hipóteses', 'poc' => 'POC', 'piloto' => 'Piloto', 'escala' => 'Escala'];
        $total = max(1, $this->countPipeline($pipeline));
        $result = [];
        foreach ($map as $key => $label) {
            $count = \count($pipeline[$key] ?? []);
            $result[] = ['stage' => $label, 'count' => $count, 'pct' => (int) round($count / $total * 100)];
        }
        return $result;
    }

    // ── KPIs ─────────────────────────────────────────────────────────────────

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @param array<string, mixed> $pulse
     * @return list<array<string, mixed>>
     */
    private function getKpis(array $pipeline, array $pulse): array
    {
        return [
            ['value' => $pulse['experiments_active'],    'label' => 'Experimentos',   'sub' => 'Em andamento'],
            ['value' => \count($pipeline['escala'] ?? []), 'label' => 'Escalados',    'sub' => 'Convertidos'],
            ['value' => $pulse['score'] . '%',           'label' => 'Maturidade',     'sub' => $pulse['boldness_label']],
            ['value' => $pulse['roi_total'],              'label' => 'ROI capturado', 'sub' => 'Últimos 90 dias'],
        ];
    }

    // ── Flow stages ──────────────────────────────────────────────────────────

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return list<array<string, mixed>>
     */
    private function getFlowStages(array $pipeline): array
    {
        return [
            ['id' => 'ideia',    'label' => 'Ideia',     'icon' => 'fa-lightbulb',     'value' => \count($pipeline['ideia'] ?? []),    'color' => '#6366F1'],
            ['id' => 'hipotese', 'label' => 'Hipótese',  'icon' => 'fa-flask',         'value' => \count($pipeline['hipotese'] ?? []), 'color' => '#8B5CF6'],
            ['id' => 'poc',      'label' => 'POC',       'icon' => 'fa-microscope',    'value' => \count($pipeline['poc'] ?? []),      'color' => '#0EA5E9'],
            ['id' => 'piloto',   'label' => 'Piloto',    'icon' => 'fa-rocket',        'value' => \count($pipeline['piloto'] ?? []),   'color' => '#10B981'],
            ['id' => 'escala',   'label' => 'Escala',    'icon' => 'fa-arrow-trend-up','value' => \count($pipeline['escala'] ?? []),   'color' => '#F59E0B'],
        ];
    }

    // ── Stage chips ──────────────────────────────────────────────────────────

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return list<array<string, mixed>>
     */
    private function getStageChips(array $pipeline): array
    {
        $stages = $this->getFlowStages($pipeline);
        return array_map(fn ($s) => [...$s], $stages);
    }

    // ── Module cards ─────────────────────────────────────────────────────────

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return list<array<string, mixed>>
     */
    private function getModuleCards(array $pipeline, User $user, array $pulse): array
    {
        $empresa = $this->requireEmpresa($user);
        $total = $this->countPipeline($pipeline);
        $tendCount = \count($this->tendenciaService->listForEmpresa($empresa));
        $connCount = \count($this->conexaoService->listForEmpresa($empresa));
        $novCount = \count($this->novidadeService->listForEmpresa($empresa));
        $impactTotals = $this->impactService->computeTotals($this->impactService->listForEmpresa($empresa));

        return [
            ['id' => 'pipeline',     'title' => 'Pipeline',     'subtitle' => 'Kanban de experimentos',       'icon' => 'fa-layer-group',   'metric' => $total . ' itens',   'route' => 'app_inovacao_pipeline',     'tone' => '#6366F1'],
            ['id' => 'laboratorio',  'title' => 'Laboratório',  'subtitle' => 'Hipóteses e POCs ativas',      'icon' => 'fa-flask',         'metric' => \count($pipeline['poc'] ?? []) . ' POCs',     'route' => 'app_inovacao_laboratorio',  'tone' => '#8B5CF6'],
            ['id' => 'experimentos', 'title' => 'Experimentos', 'subtitle' => 'Kill · Pivot · Scale',          'icon' => 'fa-vial',          'metric' => \count($pipeline['piloto'] ?? []) . ' pilotos', 'route' => 'app_inovacao_experimentos', 'tone' => '#0EA5E9'],
            ['id' => 'backlog',      'title' => 'Backlog',      'subtitle' => 'Ideias aguardando priorização','icon' => 'fa-lightbulb',     'metric' => \count($pipeline['ideia'] ?? []) . ' ideias', 'route' => 'app_inovacao_backlog',      'tone' => '#10B981'],
            ['id' => 'tendencias',   'title' => 'Tendências',   'subtitle' => 'Radar de tecnologias',         'icon' => 'fa-satellite-dish','metric' => $tendCount . ' sinais',          'route' => 'app_inovacao_tendencias',   'tone' => '#A855F7'],
            ['id' => 'portfolio',    'title' => 'Portfólio',    'subtitle' => 'Inovações escaladas',          'icon' => 'fa-trophy',        'metric' => \count($pipeline['escala'] ?? []) . ' ativos', 'route' => 'app_inovacao_portfolio',    'tone' => '#F97316'],
            ['id' => 'analytics',    'title' => 'Analytics',    'subtitle' => 'Maturidade e funil',           'icon' => 'fa-chart-pie',   'metric' => 'Score ' . ($pulse['score'] ?? 0) . ' %',        'route' => 'app_inovacao_analytics',    'tone' => '#F59E0B'],
            ['id' => 'conexoes',     'title' => 'Conexões',     'subtitle' => 'Oportunidades entre hubs',     'icon' => 'fa-share-nodes',   'metric' => $connCount . ' sinergias',       'route' => 'app_inovacao_conexoes',     'tone' => '#EC4899'],
            ['id' => 'impact',       'title' => 'Impacto',      'subtitle' => 'ROI e ledger de valor',        'icon' => 'fa-coins',         'metric' => $impactTotals['captured'],          'route' => 'app_inovacao_impact',       'tone' => '#14B8A6'],
            ['id' => 'novidades',    'title' => 'Novidades',    'subtitle' => 'Feed de inovação',             'icon' => 'fa-newspaper',     'metric' => $novCount . ' updates',         'route' => 'app_inovacao_novidades',    'tone' => '#3B82F6'],
        ];
    }

    // ── Modules nav ──────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function getModules(): array
    {
        return [
            ['id' => 'overview',     'label' => 'Visão Geral',  'icon' => 'fa-compass',       'route' => 'app_inovacao',              'subtitle' => 'Command center'],
            ['id' => 'pipeline',     'label' => 'Pipeline',     'icon' => 'fa-layer-group',   'route' => 'app_inovacao_pipeline',     'subtitle' => 'Kanban de experimentos'],
            ['id' => 'laboratorio',  'label' => 'Laboratório',  'icon' => 'fa-flask',         'route' => 'app_inovacao_laboratorio',  'subtitle' => 'Hipóteses e POCs ativas'],
            ['id' => 'experimentos', 'label' => 'Experimentos', 'icon' => 'fa-vial',          'route' => 'app_inovacao_experimentos', 'subtitle' => 'Fila Kill · Pivot · Scale'],
            ['id' => 'backlog',      'label' => 'Backlog',      'icon' => 'fa-lightbulb',     'route' => 'app_inovacao_backlog',      'subtitle' => 'Ideias e hipóteses'],
            ['id' => 'analytics',    'label' => 'Analytics',    'icon' => 'fa-chart-pie',     'route' => 'app_inovacao_analytics',   'subtitle' => 'Maturidade e funil'],
            ['id' => 'conexoes',     'label' => 'Conexões',     'icon' => 'fa-share-nodes',   'route' => 'app_inovacao_conexoes',     'subtitle' => 'Sinergias entre hubs'],
            ['id' => 'impact',       'label' => 'Impacto',      'icon' => 'fa-coins',         'route' => 'app_inovacao_impact',       'subtitle' => 'ROI e valor capturado'],
            ['id' => 'tendencias',   'label' => 'Tendências',   'icon' => 'fa-satellite-dish','route' => 'app_inovacao_tendencias',   'subtitle' => 'Radar de tecnologias'],
            ['id' => 'portfolio',    'label' => 'Portfólio',    'icon' => 'fa-trophy',        'route' => 'app_inovacao_portfolio',    'subtitle' => 'Inovações escaladas'],
            ['id' => 'novidades',    'label' => 'Novidades',    'icon' => 'fa-newspaper',     'route' => 'app_inovacao_novidades',    'subtitle' => 'Feed de inovação'],
        ];
    }

    // ── Experiment of the week ───────────────────────────────────────────────

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return array<string, mixed>
     */
    private function getExperimentWeek(array $pipeline): array
    {
        $poc = $pipeline['poc'][0] ?? null;
        if ($poc !== null) {
            return ['title' => $poc['title'], 'stage' => 'POC', 'metric' => $poc['metric'] ?? '—'];
        }
        return ['title' => 'Nenhum experimento ativo', 'stage' => '—', 'metric' => '—'];
    }

    // ── Backlog matrix ───────────────────────────────────────────────────────

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return list<array<string, mixed>>
     */
    private function getBacklogMatrix(array $pipeline): array
    {
        return $pipeline['ideia'] ?? [];
    }

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return list<array<string, mixed>>
     */
    private function getBacklogCategories(array $pipeline): array
    {
        $all = [];
        foreach ($pipeline['ideia'] ?? [] as $item) {
            foreach ($item['tags'] ?? [] as $tag) {
                $all[$tag] = ($all[$tag] ?? 0) + 1;
            }
        }
        arsort($all);
        $colors = ['#6366F1', '#8B5CF6', '#0EA5E9', '#10B981', '#F59E0B', '#EC4899', '#14B8A6'];
        $result = [];
        $i = 0;
        foreach ($all as $tag => $count) {
            $result[] = ['tag' => $tag, 'count' => $count, 'color' => $colors[$i % \count($colors)]];
            $i++;
        }
        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function getDecisionHistory(User $user): array
    {
        $empresa = $this->requireEmpresa($user);

        return array_map(static fn ($d) => [
            'title' => $d->getTitulo(),
            'decision' => $d->getTipo(),
            'date' => $d->getDecididoEm()->format('d/m'),
            'reason' => $d->getMotivo(),
            'owner' => $d->getOwnerNome() ?? '—',
        ], $this->decisaoRepo->findByEmpresa($empresa));
    }

    // ── Real signals from existing hubs ─────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function getRealSignals(User $user): array
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        $signals = [];

        if ($empresa !== null) {
            $totalFunc = $this->funcionarioRepo->countByEmpresa($empresa);
            if ($totalFunc > 0) {
                $signals[] = [
                    'source'   => 'Hub RH',
                    'icon'     => 'fa-users',
                    'color'    => '#0EA5E9',
                    'title'    => "{$totalFunc} colaboradores cadastrados",
                    'summary'  => 'Base de talentos disponível para labs internos, hackathons e co-criação.',
                    'priority' => $totalFunc > 20 ? 'high' : 'normal',
                    'link'     => 'app_rh',
                ];
            }

            $projAtivos = $this->projetoRepo->countEmAndamento($empresa);
            if ($projAtivos > 0) {
                $signals[] = [
                    'source'   => 'Core Projetos',
                    'icon'     => 'fa-diagram-project',
                    'color'    => '#10B981',
                    'title'    => "{$projAtivos} projeto(s) em andamento",
                    'summary'  => 'Projetos ativos são candidatos a receber experimentos de automação ou melhoria.',
                    'priority' => 'normal',
                    'link'     => 'app_core_projetos',
                ];
            }

            $metas = $this->metaRepo->findByEmpresa($empresa);
            $metasBaixo = array_filter($metas, fn ($m) => $m->getProgressoPercent() < 30 && $m->getStatus() !== 'ATINGIDA');
            $countBaixo = \count($metasBaixo);
            if ($countBaixo > 0) {
                $signals[] = [
                    'source'   => 'OKRs',
                    'icon'     => 'fa-bullseye',
                    'color'    => '#F59E0B',
                    'title'    => "{$countBaixo} OKR(s) com progresso abaixo de 30%",
                    'summary'  => 'Metas com baixo avanço são oportunidades de automação, ferramenta ou processo novo.',
                    'priority' => 'high',
                    'link'     => 'app_core_projetos',
                ];
            }
        }

        if (empty($signals)) {
            $signals = $this->getFallbackSignals();
        }

        return $signals;
    }

    /** @return list<array<string, mixed>> */
    private function getFallbackSignals(): array
    {
        return [
            ['source' => 'Hub RH',        'icon' => 'fa-users',           'color' => '#0EA5E9', 'title' => 'Conecte o Hub RH',        'summary' => 'Dados de colaboradores habilitarão sinais de inovação baseados em talentos reais.', 'priority' => 'normal', 'link' => 'app_rh'],
            ['source' => 'Core Projetos', 'icon' => 'fa-diagram-project', 'color' => '#10B981', 'title' => 'Conecte Core Projetos',   'summary' => 'Projetos ativos alimentarão o pipeline de inovação com candidatos reais.', 'priority' => 'normal', 'link' => 'app_core_projetos'],
            ['source' => 'OKRs',          'icon' => 'fa-bullseye',        'color' => '#F59E0B', 'title' => 'Conecte Metas (OKRs)',    'summary' => 'Metas com progresso baixo são oportunidades priorizadas de inovação.', 'priority' => 'normal', 'link' => 'app_core_projetos'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function getOkrAlignment(User $user): array
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            return [];
        }

        $metas = $this->metaRepo->findByEmpresa($empresa);
        if ($metas === []) {
            return [];
        }

        $result = [];
        foreach (array_slice($metas, 0, 5) as $meta) {
            $result[] = [
                'title'    => $meta->getTitulo(),
                'progress' => $meta->getProgressoPercent(),
                'status'   => $meta->getStatus(),
                'priority' => $meta->getPrioridade(),
                'alinhado' => $meta->getProgressoPercent() < 50,
            ];
        }

        return $result;
    }

    // ── Network nodes ────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function getNetworkNodes(User $user): array
    {
        $empresa = $this->requireEmpresa($user);

        return array_map(
            fn ($c) => $this->conexaoService->toArray($c),
            $this->conexaoService->listForEmpresa($empresa)
        );
    }

    /**
     * @param array<string, list<array<string,mixed>>> $pipeline
     * @return list<array<string, mixed>>
     */
    private function getPipelineBlocked(array $pipeline): array
    {
        $blocked = [];
        foreach ($pipeline as $stageId => $items) {
            foreach ($items as $item) {
                if (($item['days'] ?? 0) > 20 && ($item['progress'] ?? 0) < 70) {
                    $blocked[] = array_merge($item, [
                        'stage'       => $stageId,
                        'stage_label' => ucfirst($stageId),
                    ]);
                }
            }
        }
        return $blocked;
    }

    // ── Impact payload ───────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function getImpactPayload(User $user): array
    {
        $empresa = $this->requireEmpresa($user);
        $entries = $this->impactService->listForEmpresa($empresa);
        $ledger = array_map(fn ($e) => $this->impactService->toArray($e), $entries);

        return [
            'totals' => $this->impactService->computeTotals($entries),
            'ledger' => $ledger,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function getNovidades(User $user): array
    {
        $empresa = $this->requireEmpresa($user);

        return array_map(
            fn ($n) => $this->novidadeService->toArray($n),
            $this->novidadeService->listForEmpresa($empresa)
        );
    }

    /** @return list<array<string, mixed>> */
    private function getTrendRadar(User $user): array
    {
        $empresa = $this->requireEmpresa($user);

        return array_map(
            fn ($t) => $this->tendenciaService->toArray($t),
            $this->tendenciaService->listForEmpresa($empresa)
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function getIdeaCategories(): array
    {
        return [
            ['id' => 'automacao',    'label' => 'Automação',           'icon' => 'fa-robot',          'color' => '#6366F1'],
            ['id' => 'ia_ml',        'label' => 'IA / ML',             'icon' => 'fa-brain',          'color' => '#8B5CF6'],
            ['id' => 'produto',      'label' => 'Produto',             'icon' => 'fa-box',            'color' => '#0EA5E9'],
            ['id' => 'processo',     'label' => 'Processo',            'icon' => 'fa-gears',          'color' => '#10B981'],
            ['id' => 'experiencia',  'label' => 'Experiência (UX)',     'icon' => 'fa-star',           'color' => '#F59E0B'],
            ['id' => 'integracao',   'label' => 'Integração',          'icon' => 'fa-plug',           'color' => '#EC4899'],
            ['id' => 'dados',        'label' => 'Dados & Analytics',   'icon' => 'fa-chart-bar',      'color' => '#14B8A6'],
            ['id' => 'cultura',      'label' => 'Cultura & Pessoas',   'icon' => 'fa-people-group',   'color' => '#EF4444'],
        ];
    }

    /** @return list<string> */
    private function getIdeaHubs(): array
    {
        return ['Hub RH', 'Hub Talentos', 'Hub Maturidade', 'Hub Analytics', 'Hub ESG', 'Hub TI', 'Hub Projetos', 'Hub Cortex', 'Transversal'];
    }

    /** @param array<string, list<array<string,mixed>>> $pipeline */
    private function countPipeline(array $pipeline): int
    {
        return array_sum(array_map('count', $pipeline));
    }
}
