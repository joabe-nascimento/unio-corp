<?php

namespace App\Service;

use App\Chart\ChartConfig;
use App\Entity\DevProjeto;
use App\Entity\DevTarefa;
use App\Entity\Empresa;
use App\Entity\User;
use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;
use App\Repository\EmpresaRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\RhFeriasRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Agrega dados registrados no banco para gráficos da tela de boas-vindas.
 */
final class WelcomeAnalyticsService
{
    private const TZ = 'America/Sao_Paulo';

    private const PERFIL_LABELS = [
        'TENANT' => 'Tenant',
        'ADMIN' => 'Admin',
        'GESTOR' => 'Gestor',
        'GESTOR_EQUIPE' => 'Gestor de equipe',
        'SUPERVISOR' => 'Supervisor',
        'SUPERVISOR_EQUIPE' => 'Supervisor de equipe',
        'MEMBRO' => 'Membro',
    ];

    private const FUNC_STATUS_LABELS = [
        'ATIVO' => 'Ativos',
        'INATIVO' => 'Inativos',
        'FERIAS' => 'Férias',
        'AFASTADO' => 'Afastados',
    ];

    private const RH_STATUS_LABELS = [
        RhOnboardingProcess::STATUS_RASCUNHO => 'Rascunho',
        RhOnboardingProcess::STATUS_EM_ANDAMENTO => 'Em andamento',
        RhOnboardingProcess::STATUS_CONCLUIDO => 'Concluído',
        RhOnboardingProcess::STATUS_CANCELADO => 'Cancelado',
    ];

    public function __construct(
        private UserRepository $userRepo,
        private EmpresaRepository $empresaRepo,
        private FuncionarioRepository $funcionarioRepo,
        private DevTarefaRepository $tarefaRepo,
        private DevProjetoRepository $projetoRepo,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhOffboardingProcessRepository $offboardingRepo,
        private RhFeriasRepository $feriasRepo,
        private NavigationService $navigation,
    ) {}

    /**
     * @return list<array{
     *     id: string,
     *     title: string,
     *     subtitle: string,
     *     icon: string,
     *     charts: list<array<string, mixed>>
     * }>
     */
    public function getChartSections(User $user, ?Empresa $empresa): array
    {
        $isTenant = $this->navigation->isTenant($user);
        $sections = [];

        if ($empresa !== null && ($this->navigation->showModuloRh($user) || $this->navigation->showModuloPessoas($user))) {
            $workforce = array_values(array_filter([
                $this->buildPeopleSankey($empresa),
                $this->buildPeopleHeatmap($empresa),
                $this->buildWorkforceStackedBar($empresa),
                $this->buildWorkforceTreemap($empresa),
                $this->buildHeadcountByDeptBar($empresa),
                $this->buildWorkforceStatusRing($empresa),
                $this->buildOperationalRadar($user, $empresa),
            ]));
            if ($workforce !== []) {
                $sections[] = $this->makeSection(
                    'workforce-intelligence',
                    'Workforce Intelligence',
                    'Composição, distribuição e saúde do capital humano em tempo real',
                    'fa-users-viewfinder',
                    'executive',
                    'People Analytics',
                    $workforce,
                );
            }
        }

        if ($empresa !== null && $this->navigation->showModuloRh($user)) {
            $rh = array_values(array_filter([
                $this->buildRhSankey($empresa),
                $this->buildRhFunnel($empresa),
                $this->buildRhHealthGauge($empresa),
                $this->buildFeriasPipeline($empresa),
                $this->buildRhThroughputBar($empresa),
            ]));
            if ($rh !== []) {
                $sections[] = $this->makeSection(
                    'rh-operations',
                    'RH Operations',
                    'Pipeline de admissões, desligamentos e ciclo de férias',
                    'fa-user-tie',
                    'operational',
                    'Human Resources',
                    $rh,
                );
            }
        }

        if ($empresa !== null && $this->navigation->showProjetosMetas($user)) {
            $delivery = array_values(array_filter([
                $this->buildDeliveryGauge($empresa),
                $this->buildKanbanPipelineBar($empresa),
                $this->buildKanbanStackedByProject($empresa),
                $this->buildProjectsTreemap($empresa),
                $this->buildProjectsBubble($empresa),
                $this->buildProjectStatusRing($empresa),
            ]));
            if ($delivery !== []) {
                $sections[] = $this->makeSection(
                    'project-delivery',
                    'Project Delivery',
                    'Portfólio, throughput de tarefas e maturidade de entregas',
                    'fa-rocket',
                    'operational',
                    'Delivery Office',
                    $delivery,
                );
            }
        }

        $governance = array_values(array_filter([
            !($empresa !== null && ($this->navigation->showModuloRh($user) || $this->navigation->showModuloPessoas($user)))
                ? $this->buildAccessSankey($user, $empresa, $isTenant)
                : null,
            $empresa !== null ? $this->buildProfileRing($empresa) : null,
            $empresa !== null ? $this->buildUserActivityGauge($empresa) : null,
            ($isTenant && $empresa === null) ? $this->buildTenantSankey() : null,
            ($isTenant && $empresa === null) ? $this->buildTenantEmpresaTreemap() : null,
        ]));
        if ($governance !== []) {
            $sections[] = $this->makeSection(
                'platform-governance',
                'Platform Governance',
                'Acessos, perfis e governança multi-empresa',
                'fa-shield-halved',
                'governance',
                'Identity & Access',
                $governance,
            );
        }

        $growthCharts = array_values(array_filter([
            $this->buildEvolutionChart($user, $empresa, $isTenant),
            $empresa !== null ? $this->buildTaskVelocityChart($empresa) : null,
            $empresa !== null ? $this->buildRhActivityChart($empresa) : null,
        ]));
        if ($growthCharts !== []) {
            $sections[] = $this->makeSection(
                'growth-trends',
                'Growth & Trends',
                'Séries temporais de cadastros, entregas e movimentações RH',
                'fa-chart-line',
                'executive',
                'Executive Trends',
                $growthCharts,
            );
        }

        return $sections;
    }

    /**
     * @return array{
     *     sections: list<array<string, mixed>>,
     *     meta: array{chart_count: int, section_count: int, generated_at: string}
     * }
     */
    public function getChartPayload(User $user, ?Empresa $empresa): array
    {
        $sections = $this->getChartSections($user, $empresa);
        $chartCount = 0;
        foreach ($sections as $section) {
            $chartCount += \count($section['charts'] ?? []);
        }

        return [
            'sections' => $sections,
            'executive' => $this->buildExecutiveSummary($user, $empresa),
            'meta' => [
                'chart_count' => $chartCount,
                'section_count' => \count($sections),
                'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $charts
     *
     * @return array<string, mixed>
     */
    private function makeSection(
        string $id,
        string $title,
        string $subtitle,
        string $icon,
        string $tier,
        string $badge,
        array $charts,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'icon' => $icon,
            'tier' => $tier,
            'badge' => $badge,
            'charts' => $charts,
        ];
    }

    /**
     * @return array{kpis: list<array<string, mixed>>}
     */
    public function buildExecutiveSummary(User $user, ?Empresa $empresa): array
    {
        $isTenant = $this->navigation->isTenant($user);
        $kpis = [];

        if ($empresa !== null) {
            $headcount = (int) $this->funcionarioRepo->count(['empresa' => $empresa]);
            $ativos = (int) $this->funcionarioRepo->count(['empresa' => $empresa, 'status' => 'ATIVO']);
            $users = (int) $this->userRepo->count(['empresa' => $empresa]);
            $projects = (int) $this->projetoRepo->count(['empresa' => $empresa]);
            $projectsActive = (int) $this->projetoRepo->count([
                'empresa' => $empresa,
                'status' => DevProjeto::STATUS_EM_ANDAMENTO,
            ]);
            $tasksTotal = (int) $this->tarefaRepo->count(['empresa' => $empresa]);
            $tasksDone = (int) $this->tarefaRepo->count([
                'empresa' => $empresa,
                'status' => DevTarefa::STATUS_CONCLUIDO,
            ]);
            $rhOpen = $this->countOpenRhProcesses($empresa);
            $deliveryRate = $tasksTotal > 0 ? (int) round(($tasksDone / $tasksTotal) * 100) : 0;

            $kpis = array_values(array_filter([
                $headcount > 0 ? $this->executiveKpi('headcount', 'Colaboradores', $headcount, 'fa-users', 'Cadastro total') : null,
                $ativos > 0 ? $this->executiveKpi('active-people', 'Pessoas ativas', $ativos, 'fa-user-check', 'Status operacional') : null,
                $users > 0 ? $this->executiveKpi('users', 'Usuários', $users, 'fa-id-badge', 'Contas na plataforma') : null,
                $projects > 0 ? $this->executiveKpi('projects', 'Projetos', $projects, 'fa-folder-tree', $projectsActive . ' em andamento') : null,
                $tasksTotal > 0 ? $this->executiveKpi('delivery', 'Entrega', $deliveryRate, 'fa-gauge-high', $tasksDone . ' de ' . $tasksTotal . ' tarefas', '%') : null,
                $rhOpen > 0 ? $this->executiveKpi('rh-open', 'RH em aberto', $rhOpen, 'fa-user-clock', 'Admissões e desligamentos') : null,
            ]));
        } elseif ($isTenant) {
            $empresas = $this->countEmpresasAtivas();
            $users = (int) $this->userRepo->count([]);
            $kpis = [
                $this->executiveKpi('companies', 'Empresas', $empresas['values'][0], 'fa-building', 'Ativas na plataforma'),
                $this->executiveKpi('users-global', 'Usuários', $users, 'fa-globe', 'Contas globais'),
            ];
        }

        return ['kpis' => $kpis];
    }

    /** @return array<string, mixed> */
    private function executiveKpi(
        string $id,
        string $label,
        int|float $value,
        string $icon,
        string $hint,
        ?string $suffix = null,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'value' => $value,
            'icon' => $icon,
            'hint' => $hint,
            'suffix' => $suffix,
        ];
    }

    /** @param array<string, mixed> $chart @return array<string, mixed> */
    private function withKpi(array $chart, string $label, int|float $value): array
    {
        $chart['kpi'] = ['label' => $label, 'value' => $value];

        return $chart;
    }

    /** @return ?array<string, mixed> */
    private function buildPeopleSankey(Empresa $empresa): ?array
    {
        $rows = $this->funcionarioRepo->createQueryBuilder('f')
            ->select('COALESCE(d.nome, :sem) AS dept, f.status AS status, COUNT(f.id) AS total')
            ->leftJoin('f.departamento', 'd')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->setParameter('sem', 'Sem departamento')
            ->groupBy('dept, status')
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return null;
        }

        $root = 'Colaboradores';
        $nodes = [['name' => $root]];
        $links = [];
        $deptTotals = [];

        foreach ($rows as $row) {
            $dept = (string) $row['dept'];
            $status = self::FUNC_STATUS_LABELS[(string) $row['status']] ?? (string) $row['status'];
            $total = (int) $row['total'];
            if ($total <= 0) {
                continue;
            }

            $deptNode = 'Dept · ' . $dept;
            $statusNode = $dept . ' · ' . $status;

            $this->ensureSankeyNode($nodes, $deptNode);
            $this->ensureSankeyNode($nodes, $statusNode);

            $deptTotals[$deptNode] = ($deptTotals[$deptNode] ?? 0) + $total;
            $links[] = ['source' => $deptNode, 'target' => $statusNode, 'value' => $total];
        }

        foreach ($deptTotals as $deptNode => $total) {
            $links[] = ['source' => $root, 'target' => $deptNode, 'value' => $total];
        }

        if ($links === []) {
            return null;
        }

        $total = array_sum($deptTotals);

        $chart = ChartConfig::sankey(
            'people-sankey',
            'Fluxo de colaboradores',
            $nodes,
            $links,
            'Sankey departamento → situação cadastral (dados reais)',
        )->toArray();
        $chart['kpi'] = ['label' => 'Colaboradores', 'value' => $total];

        return $chart;
    }

    /** @return ?array<string, mixed> */
    private function buildPeopleHeatmap(Empresa $empresa): ?array
    {
        $rows = $this->funcionarioRepo->createQueryBuilder('f')
            ->select('COALESCE(d.nome, :sem) AS dept, f.status AS status, COUNT(f.id) AS total')
            ->leftJoin('f.departamento', 'd')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->setParameter('sem', 'Sem departamento')
            ->groupBy('dept, status')
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return null;
        }

        $xLabels = [];
        $yLabels = [];
        $map = [];

        foreach ($rows as $row) {
            $dept = (string) $row['dept'];
            $status = self::FUNC_STATUS_LABELS[(string) $row['status']] ?? (string) $row['status'];
            $total = (int) $row['total'];

            if (!\in_array($status, $xLabels, true)) {
                $xLabels[] = $status;
            }
            if (!\in_array($dept, $yLabels, true)) {
                $yLabels[] = $dept;
            }
            $map[$dept . '|' . $status] = $total;
        }

        $matrix = [];
        foreach ($yLabels as $yIdx => $dept) {
            foreach ($xLabels as $xIdx => $status) {
                $value = $map[$dept . '|' . $status] ?? 0;
                if ($value > 0) {
                    $matrix[] = [$xIdx, $yIdx, $value];
                }
            }
        }

        if ($matrix === []) {
            return null;
        }

        $cellTotal = array_sum(array_column($matrix, 2));

        $chart = ChartConfig::heatmap(
            'people-heatmap',
            'Matriz departamento × situação',
            $xLabels,
            $yLabels,
            $matrix,
            'Heatmap bidimensional da distribuição de pessoas',
        )->toArray();

        return $this->withKpi($chart, 'Colaboradores', $cellTotal);
    }

    /** @return ?array<string, mixed> */
    private function buildWorkforceStackedBar(Empresa $empresa): ?array
    {
        $matrix = $this->fetchPeopleMatrix($empresa);
        if ($matrix === null) {
            return null;
        }

        $datasets = [];
        foreach ($matrix['statuses'] as $status) {
            $data = [];
            foreach ($matrix['depts'] as $dept) {
                $data[] = $matrix['map'][$dept . '|' . $status] ?? 0;
            }
            $datasets[] = ['label' => $status, 'data' => $data];
        }

        $chart = ChartConfig::stackedBar(
            'workforce-stacked',
            'Headcount por departamento e situação',
            $matrix['depts'],
            $datasets,
            'Barras empilhadas — visão comparativa entre áreas',
        )->toArray();

        return $this->withKpi($chart, 'Colaboradores', $matrix['total']);
    }

    /** @return ?array<string, mixed> */
    private function buildWorkforceTreemap(Empresa $empresa): ?array
    {
        $matrix = $this->fetchPeopleMatrix($empresa);
        if ($matrix === null) {
            return null;
        }

        $tree = [];
        foreach ($matrix['depts'] as $dept) {
            $children = [];
            foreach ($matrix['statuses'] as $status) {
                $value = $matrix['map'][$dept . '|' . $status] ?? 0;
                if ($value > 0) {
                    $children[] = ['name' => $status, 'value' => $value];
                }
            }
            if ($children !== []) {
                $tree[] = ['name' => $dept, 'children' => $children];
            }
        }

        if ($tree === []) {
            return null;
        }

        $chart = ChartConfig::treemap(
            'workforce-treemap',
            'Mapa proporcional do workforce',
            $tree,
            'Treemap hierárquico departamento → situação cadastral',
        )->toArray();

        return $this->withKpi($chart, 'Áreas', \count($tree));
    }

    /** @return ?array<string, mixed> */
    private function buildHeadcountByDeptBar(Empresa $empresa): ?array
    {
        $byDept = $this->countFuncionariosByDepartamento($empresa);
        if (!$this->hasValues($byDept['values'])) {
            return null;
        }

        $chart = ChartConfig::barPro(
            'headcount-dept',
            'Headcount por departamento',
            $byDept['labels'],
            $byDept['values'],
            'Ranking das áreas com maior concentração de pessoas',
            true,
        )->toArray();

        return $this->withKpi($chart, 'Maior área', max($byDept['values']));
    }

    /** @return ?array<string, mixed> */
    private function buildWorkforceStatusRing(Empresa $empresa): ?array
    {
        $byStatus = $this->countFuncionariosByStatus($empresa);
        if (!$this->hasValues($byStatus['values'])) {
            return null;
        }

        $chart = ChartConfig::ring(
            'workforce-status-ring',
            'Composição por situação',
            $byStatus['labels'],
            $byStatus['values'],
            'Distribuição percentual do quadro de colaboradores',
        )->toArray();

        return $this->withKpi($chart, 'Total', array_sum($byStatus['values']));
    }

    /** @return ?array<string, mixed> */
    private function buildRhFunnel(Empresa $empresa): ?array
    {
        $adm = $this->countRhByStatus(RhOnboardingProcess::class, $empresa);
        $dem = $this->countRhByStatus(RhOffboardingProcess::class, $empresa);
        $merged = ['Rascunho' => 0, 'Em andamento' => 0, 'Concluído' => 0, 'Cancelado' => 0];

        foreach ([$adm, $dem] as $block) {
            foreach ($block['labels'] as $idx => $label) {
                $merged[$label] = ($merged[$label] ?? 0) + (int) ($block['values'][$idx] ?? 0);
            }
        }

        $steps = [];
        foreach ($merged as $name => $value) {
            if ($value > 0) {
                $steps[] = ['name' => $name, 'value' => $value];
            }
        }

        if ($steps === []) {
            return null;
        }

        $chart = ChartConfig::funnel(
            'rh-funnel',
            'Funil de processos RH',
            $steps,
            'Conversão agregada de admissões e desligamentos por estágio',
        )->toArray();

        return $this->withKpi($chart, 'Processos', array_sum($merged));
    }

    /** @return ?array<string, mixed> */
    private function buildRhHealthGauge(Empresa $empresa): ?array
    {
        $adm = $this->countRhByStatus(RhOnboardingProcess::class, $empresa);
        $dem = $this->countRhByStatus(RhOffboardingProcess::class, $empresa);
        $total = array_sum($adm['values']) + array_sum($dem['values']);
        if ($total <= 0) {
            return null;
        }

        $concluded = 0;
        foreach ([$adm, $dem] as $block) {
            foreach ($block['labels'] as $idx => $label) {
                if ($label === 'Concluído') {
                    $concluded += (int) ($block['values'][$idx] ?? 0);
                }
            }
        }

        $rate = (int) round(($concluded / $total) * 100);

        $chart = ChartConfig::gauge(
            'rh-health-gauge',
            'Índice de conclusão RH',
            $rate,
            100,
            'Percentual de processos concluídos sobre o total registrado',
            '%',
        )->toArray();
        $chart['size'] = 'compact';

        return $this->withKpi($chart, 'Concluídos', $concluded);
    }

    /** @return ?array<string, mixed> */
    private function buildFeriasPipeline(Empresa $empresa): ?array
    {
        $rows = $this->feriasRepo->createQueryBuilder('f')
            ->select('f.status AS status, COUNT(f.id) AS total')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('f.status')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return null;
        }

        $labelsMap = [
            'SOLICITADA' => 'Solicitada',
            'APROVADA' => 'Aprovada',
            'REJEITADA' => 'Rejeitada',
            'EM_GOZO' => 'Em gozo',
            'CONCLUIDA' => 'Concluída',
        ];

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $key = (string) $row['status'];
            $labels[] = $labelsMap[$key] ?? $key;
            $values[] = (int) $row['total'];
        }

        $chart = ChartConfig::barPro(
            'ferias-pipeline',
            'Pipeline de férias',
            $labels,
            $values,
            'Volume de solicitações por status no ciclo de férias',
        )->toArray();

        return $this->withKpi($chart, 'Solicitações', array_sum($values));
    }

    /** @return ?array<string, mixed> */
    private function buildRhThroughputBar(Empresa $empresa): ?array
    {
        $adm = array_sum($this->countRhByStatus(RhOnboardingProcess::class, $empresa)['values']);
        $dem = array_sum($this->countRhByStatus(RhOffboardingProcess::class, $empresa)['values']);
        if ($adm + $dem <= 0) {
            return null;
        }

        $chart = ChartConfig::barPro(
            'rh-throughput',
            'Throughput RH',
            ['Admissões', 'Desligamentos'],
            [$adm, $dem],
            'Comparativo de volume entre fluxos de entrada e saída',
        )->toArray();

        return $this->withKpi($chart, 'Total RH', $adm + $dem);
    }

    /** @return ?array<string, mixed> */
    private function buildRhSankey(Empresa $empresa): ?array
    {
        $adm = $this->countRhByStatus(RhOnboardingProcess::class, $empresa);
        $dem = $this->countRhByStatus(RhOffboardingProcess::class, $empresa);

        if (!$this->hasValues($adm['values']) && !$this->hasValues($dem['values'])) {
            return null;
        }

        $root = 'Processos RH';
        $nodes = [['name' => $root], ['name' => 'Admissões'], ['name' => 'Desligamentos']];
        $links = [];
        $admTotal = array_sum($adm['values']);
        $demTotal = array_sum($dem['values']);

        if ($admTotal > 0) {
            $links[] = ['source' => $root, 'target' => 'Admissões', 'value' => $admTotal];
            foreach ($adm['labels'] as $idx => $label) {
                $value = (int) ($adm['values'][$idx] ?? 0);
                if ($value <= 0) {
                    continue;
                }
                $target = 'Adm · ' . $label;
                $this->ensureSankeyNode($nodes, $target);
                $links[] = ['source' => 'Admissões', 'target' => $target, 'value' => $value];
            }
        }

        if ($demTotal > 0) {
            $links[] = ['source' => $root, 'target' => 'Desligamentos', 'value' => $demTotal];
            foreach ($dem['labels'] as $idx => $label) {
                $value = (int) ($dem['values'][$idx] ?? 0);
                if ($value <= 0) {
                    continue;
                }
                $target = 'Off · ' . $label;
                $this->ensureSankeyNode($nodes, $target);
                $links[] = ['source' => 'Desligamentos', 'target' => $target, 'value' => $value];
            }
        }

        if ($links === []) {
            return null;
        }

        $links = $this->mergeSankeyLinks($links);

        $chart = ChartConfig::sankey(
            'rh-sankey',
            'Pipeline de admissões e desligamentos',
            $nodes,
            $links,
            'Fluxo operacional do RH com status reais',
        )->toArray();
        $chart['kpi'] = ['label' => 'Processos', 'value' => $admTotal + $demTotal];

        return $chart;
    }

    /** @return ?array<string, mixed> */
    private function buildDeliveryGauge(Empresa $empresa): ?array
    {
        $total = (int) $this->tarefaRepo->count(['empresa' => $empresa]);
        if ($total <= 0) {
            return null;
        }

        $done = (int) $this->tarefaRepo->count(['empresa' => $empresa, 'status' => DevTarefa::STATUS_CONCLUIDO]);
        $rate = (int) round(($done / $total) * 100);

        $chart = ChartConfig::gauge(
            'delivery-gauge',
            'Taxa de entrega',
            $rate,
            100,
            'Percentual de tarefas concluídas no portfólio',
            '%',
        )->toArray();
        $chart['size'] = 'compact';

        return $this->withKpi($chart, 'Concluídas', $done);
    }

    /** @return ?array<string, mixed> */
    private function buildKanbanPipelineBar(Empresa $empresa): ?array
    {
        $byStatus = $this->countTarefasByStatus($empresa);
        if (!$this->hasValues($byStatus['values'])) {
            return null;
        }

        $chart = ChartConfig::barPro(
            'kanban-pipeline',
            'Pipeline Kanban',
            $byStatus['labels'],
            $byStatus['values'],
            'Distribuição de tarefas por coluna do fluxo',
        )->toArray();

        return $this->withKpi($chart, 'Tarefas', array_sum($byStatus['values']));
    }

    /** @return ?array<string, mixed> */
    private function buildKanbanStackedByProject(Empresa $empresa): ?array
    {
        $projects = $this->projetoRepo->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.nome', 'ASC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        if ($projects === []) {
            return null;
        }

        $labels = [];
        $statusKeys = array_keys(DevTarefa::KANBAN_COLUMNS);
        $datasets = [];
        foreach ($statusKeys as $status) {
            $datasets[] = ['label' => DevTarefa::KANBAN_COLUMNS[$status], 'data' => []];
        }

        foreach ($projects as $project) {
            $labels[] = mb_strlen($project->getNome()) > 18
                ? mb_substr($project->getNome(), 0, 16) . '…'
                : $project->getNome();
            foreach ($statusKeys as $idx => $status) {
                $datasets[$idx]['data'][] = (int) $this->tarefaRepo->count([
                    'projeto' => $project,
                    'status' => $status,
                ]);
            }
        }

        if (array_sum(array_map(static fn (array $ds): int => array_sum($ds['data']), $datasets)) === 0) {
            return null;
        }

        $chart = ChartConfig::stackedBar(
            'kanban-by-project',
            'Kanban por projeto (top 6)',
            $labels,
            $datasets,
            'Throughput de entregas desagregado por iniciativa',
        )->toArray();

        return $this->withKpi($chart, 'Projetos', \count($labels));
    }

    /** @return ?array<string, mixed> */
    private function buildProjectsTreemap(Empresa $empresa): ?array
    {
        $projects = $this->projetoRepo->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getResult();

        if ($projects === []) {
            return null;
        }

        $statusLabels = [
            DevProjeto::STATUS_IDEIA => 'Ideia',
            DevProjeto::STATUS_EM_ANDAMENTO => 'Em andamento',
            DevProjeto::STATUS_PAUSADO => 'Pausado',
            DevProjeto::STATUS_FEITO => 'Concluído',
        ];
        $buckets = [];
        foreach ($projects as $project) {
            $status = $statusLabels[$project->getStatus()] ?? $project->getStatus();
            $tasks = (int) $this->tarefaRepo->count(['projeto' => $project]);
            $buckets[$status][] = [
                'name' => $project->getNome(),
                'value' => max(1, $tasks),
            ];
        }

        $tree = [];
        foreach ($buckets as $status => $children) {
            $tree[] = ['name' => $status, 'children' => $children];
        }

        $chart = ChartConfig::treemap(
            'projects-treemap',
            'Mapa de portfólio',
            $tree,
            'Tamanho proporcional ao volume de tarefas por projeto',
        )->toArray();

        return $this->withKpi($chart, 'Projetos', \count($projects));
    }

    /** @return ?array<string, mixed> */
    private function buildProjectStatusRing(Empresa $empresa): ?array
    {
        $byStatus = $this->countProjetosByStatus($empresa);
        if (!$this->hasValues($byStatus['values'])) {
            return null;
        }

        $chart = ChartConfig::ring(
            'project-status-ring',
            'Mix de maturidade',
            $byStatus['labels'],
            $byStatus['values'],
            'Composição do portfólio por estágio de maturidade',
        )->toArray();
        $chart['size'] = 'compact';

        return $this->withKpi($chart, 'Portfólio', array_sum($byStatus['values']));
    }

    /** @return ?array<string, mixed> */
    private function buildProjectsBubble(Empresa $empresa): ?array
    {
        $projects = $this->projetoRepo->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getResult();

        if ($projects === []) {
            return null;
        }

        $statusLabels = [
            DevProjeto::STATUS_IDEIA => 'Ideia',
            DevProjeto::STATUS_EM_ANDAMENTO => 'Em andamento',
            DevProjeto::STATUS_PAUSADO => 'Pausado',
            DevProjeto::STATUS_FEITO => 'Concluído',
        ];

        $points = [];
        foreach ($projects as $project) {
            $taskCount = (int) $this->tarefaRepo->count(['projeto' => $project]);
            $status = $statusLabels[$project->getStatus()] ?? $project->getStatus();
            $points[] = [
                'x' => $taskCount,
                'y' => match ($project->getStatus()) {
                    DevProjeto::STATUS_FEITO => 4,
                    DevProjeto::STATUS_EM_ANDAMENTO => 3,
                    DevProjeto::STATUS_PAUSADO => 2,
                    default => 1,
                },
                'r' => max(8, min(40, 8 + $taskCount * 2)),
                'label' => $project->getNome() . ' (' . $status . ')',
            ];
        }

        if ($points === []) {
            return null;
        }

        $chart = ChartConfig::bubble(
            'projects-bubble',
            'Portfólio · tarefas × maturidade',
            $points,
            'Cada bolha é um projeto: eixo X = tarefas, tamanho = volume de entregas',
            'Tarefas',
            'Maturidade',
        )->toArray();
        $chart['kpi'] = ['label' => 'Projetos', 'value' => \count($points)];

        return $chart;
    }

    /** @return ?array<string, mixed> */
    private function buildAccessSankey(User $user, ?Empresa $empresa, bool $isTenant): ?array
    {
        if ($isTenant && $empresa === null) {
            return $this->buildTenantSankey();
        }

        $qb = $this->userRepo->createQueryBuilder('u')
            ->select('u.ativo AS ativo, u.perfil AS perfil, COUNT(u.id) AS total')
            ->groupBy('u.ativo, u.perfil')
            ->orderBy('total', 'DESC');

        if ($empresa !== null) {
            $qb->andWhere('u.empresa = :empresa')->setParameter('empresa', $empresa);
        }

        $rows = $qb->getQuery()->getArrayResult();
        if ($rows === []) {
            return null;
        }

        $root = $empresa?->getNome() ?? 'Acesso';
        $nodes = [['name' => $root]];
        $links = [];

        foreach ($rows as $row) {
            $value = (int) ($row['total'] ?? 0);
            if ($value <= 0) {
                continue;
            }
            $bucket = ($row['ativo'] ?? false) ? 'Contas ativas' : 'Contas inativas';
            $perfil = self::PERFIL_LABELS[(string) ($row['perfil'] ?? '')] ?? (string) ($row['perfil'] ?? 'Perfil');
            $perfilNode = 'Perfil · ' . $perfil;

            $this->ensureSankeyNode($nodes, $bucket);
            $this->ensureSankeyNode($nodes, $perfilNode);
            $links[] = ['source' => $root, 'target' => $bucket, 'value' => $value];
            $links[] = ['source' => $bucket, 'target' => $perfilNode, 'value' => $value];
        }

        $links = $this->mergeSankeyLinks($links);
        $total = (int) $this->userRepo->count($empresa !== null ? ['empresa' => $empresa] : []);

        $chart = ChartConfig::sankey(
            'access-sankey',
            'Mapa de acessos',
            $nodes,
            $links,
            'Hierarquia empresa → status da conta → perfil de permissão',
        )->toArray();

        return $this->withKpi($chart, 'Contas', $total);
    }

    /** @return ?array<string, mixed> */
    private function buildTenantSankey(): ?array
    {
        $empresas = $this->empresaRepo->findBy(['ativo' => true], ['nome' => 'ASC'], 8);
        if ($empresas === []) {
            return null;
        }

        $root = 'Plataforma Unio';
        $nodes = [['name' => $root]];
        $links = [];

        foreach ($empresas as $emp) {
            $userCount = (int) $this->userRepo->count(['empresa' => $emp]);
            if ($userCount <= 0) {
                continue;
            }
            $node = (string) $emp->getNome();
            $this->ensureSankeyNode($nodes, $node);
            $links[] = ['source' => $root, 'target' => $node, 'value' => $userCount];

            $byPerfil = $this->countUsersByPerfil($emp, false);
            foreach ($byPerfil['labels'] as $idx => $label) {
                $value = (int) ($byPerfil['values'][$idx] ?? 0);
                if ($value <= 0) {
                    continue;
                }
                $perfilNode = $node . ' · ' . $label;
                $this->ensureSankeyNode($nodes, $perfilNode);
                $links[] = ['source' => $node, 'target' => $perfilNode, 'value' => $value];
            }
        }

        if ($links === []) {
            return null;
        }

        return ChartConfig::sankey(
            'tenant-sankey',
            'Fluxo multi-empresa',
            $nodes,
            $this->mergeSankeyLinks($links),
            'Empresas ativas e perfis de usuário na plataforma',
        )->toArray();
    }

    /** @return ?array<string, mixed> */
    private function buildProfileRing(Empresa $empresa): ?array
    {
        $byPerfil = $this->countUsersByPerfil($empresa, false);
        if (!$this->hasValues($byPerfil['values'])) {
            return null;
        }

        $chart = ChartConfig::ring(
            'profile-ring',
            'Mix de perfis de acesso',
            $byPerfil['labels'],
            $byPerfil['values'],
            'Distribuição de papéis e permissões na empresa',
        )->toArray();
        $chart['size'] = 'compact';

        return $this->withKpi($chart, 'Contas', array_sum($byPerfil['values']));
    }

    /** @return ?array<string, mixed> */
    private function buildUserActivityGauge(Empresa $empresa): ?array
    {
        $byAtivo = $this->countUsersByAtivo($empresa, false);
        $total = array_sum($byAtivo['values']);
        if ($total <= 0) {
            return null;
        }

        $active = (int) ($byAtivo['values'][0] ?? 0);
        $rate = (int) round(($active / $total) * 100);

        $chart = ChartConfig::gauge(
            'user-activity-gauge',
            'Contas ativas',
            $rate,
            100,
            'Percentual de usuários com acesso ativo',
            '%',
        )->toArray();
        $chart['size'] = 'compact';

        return $this->withKpi($chart, 'Ativas', $active);
    }

    /** @return ?array<string, mixed> */
    private function buildTenantEmpresaTreemap(): ?array
    {
        $empresas = $this->empresaRepo->findBy(['ativo' => true], ['nome' => 'ASC'], 12);
        if ($empresas === []) {
            return null;
        }

        $tree = [];
        foreach ($empresas as $emp) {
            $users = (int) $this->userRepo->count(['empresa' => $emp]);
            $people = (int) $this->funcionarioRepo->count(['empresa' => $emp]);
            if ($users + $people <= 0) {
                continue;
            }
            $tree[] = [
                'name' => (string) $emp->getNome(),
                'value' => max(1, $users + $people),
            ];
        }

        if ($tree === []) {
            return null;
        }

        $chart = ChartConfig::treemap(
            'tenant-empresa-treemap',
            'Mapa multi-empresa',
            $tree,
            'Peso relativo de usuários + colaboradores por empresa ativa',
        )->toArray();

        return $this->withKpi($chart, 'Empresas', \count($tree));
    }

    /** @param list<array{name: string}> $nodes */
    private function ensureSankeyNode(array &$nodes, string $name): void
    {
        foreach ($nodes as $node) {
            if (($node['name'] ?? '') === $name) {
                return;
            }
        }
        $nodes[] = ['name' => $name];
    }

    /**
     * @param list<array{source: string, target: string, value: int|float}> $links
     *
     * @return list<array{source: string, target: string, value: int|float}>
     */
    private function mergeSankeyLinks(array $links): array
    {
        $merged = [];
        foreach ($links as $link) {
            $key = $link['source'] . '→' . $link['target'];
            if (!isset($merged[$key])) {
                $merged[$key] = $link;
                continue;
            }
            $merged[$key]['value'] += $link['value'];
        }

        return array_values($merged);
    }

    /** @return ?array<string, mixed> */
    private function buildOperationalRadar(User $user, Empresa $empresa): ?array
    {
        $headcount = (int) $this->funcionarioRepo->count(['empresa' => $empresa, 'status' => 'ATIVO']);
        $rhOpen = $this->countOpenRhProcesses($empresa);
        $projectsActive = (int) $this->projetoRepo->count([
            'empresa' => $empresa,
            'status' => DevProjeto::STATUS_EM_ANDAMENTO,
        ]);
        $taskCount = (int) $this->tarefaRepo->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->join('t.projeto', 'p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
        $users = (int) $this->userRepo->count(['empresa' => $empresa]);

        $metrics = [
            ['name' => 'Pessoas ativas', 'raw' => $headcount, 'max' => max(10, $headcount)],
            ['name' => 'RH em aberto', 'raw' => $rhOpen, 'max' => max(5, $rhOpen)],
            ['name' => 'Projetos ativos', 'raw' => $projectsActive, 'max' => max(5, $projectsActive)],
            ['name' => 'Tarefas', 'raw' => $taskCount, 'max' => max(20, $taskCount)],
            ['name' => 'Usuários', 'raw' => $users, 'max' => max(10, $users)],
        ];

        if (array_sum(array_column($metrics, 'raw')) === 0) {
            return null;
        }

        $indicators = array_map(
            static fn (array $m): array => ['name' => $m['name'], 'max' => 100],
            $metrics,
        );
        $values = array_map(
            static fn (array $m): int => (int) round(min(100, ($m['raw'] / max(1, $m['max'])) * 100)),
            $metrics,
        );

        $chart = ChartConfig::radar(
            'operational-radar',
            'Radar operacional',
            $indicators,
            [['name' => 'Índice relativo', 'value' => $values]],
            'KPIs normalizados: pessoas, RH, projetos e entregas',
        )->toArray();
        $chart['kpi'] = ['label' => 'Pessoas ativas', 'value' => $headcount];

        return $chart;
    }

    private function countOpenRhProcesses(Empresa $empresa): int
    {
        $openStatuses = [
            RhOnboardingProcess::STATUS_RASCUNHO,
            RhOnboardingProcess::STATUS_EM_ANDAMENTO,
        ];

        $onboarding = (int) $this->onboardingRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.empresa = :empresa')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', $openStatuses)
            ->getQuery()
            ->getSingleScalarResult();

        $offboarding = (int) $this->offboardingRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.empresa = :empresa')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', $openStatuses)
            ->getQuery()
            ->getSingleScalarResult();

        return $onboarding + $offboarding;
    }

    /** @return ?array<string, mixed> */
    private function buildEvolutionChart(User $user, ?Empresa $empresa, bool $isTenant): ?array
    {
        $evolution = $this->countRegistrationsLastMonths($empresa, $isTenant, 6);
        if (!$this->hasValues($evolution['users']) && !$this->hasValues($evolution['funcionarios'])) {
            return null;
        }

        $datasets = array_values(array_filter([
            $this->hasValues($evolution['users'])
                ? ['label' => 'Usuários', 'data' => $evolution['users']]
                : null,
            $empresa !== null && $this->hasValues($evolution['funcionarios'])
                ? ['label' => 'Colaboradores', 'data' => $evolution['funcionarios']]
                : null,
        ]));

        $chart = ChartConfig::areaLine(
            'evolution-registrations',
            'Novos cadastros (6 meses)',
            $evolution['labels'],
            $datasets,
            'Série temporal executiva de crescimento de contas e colaboradores',
        )->toArray();
        $chart['size'] = 'hero';

        return $this->withKpi(
            $chart,
            'Total no período',
            array_sum($evolution['users']) + array_sum($evolution['funcionarios']),
        );
    }

    /** @return ?array<string, mixed> */
    private function buildTaskVelocityChart(Empresa $empresa): ?array
    {
        $velocity = $this->countEntityRegistrationsLastMonths($empresa, DevTarefa::class, 6);
        if (!$this->hasValues($velocity['values'])) {
            return null;
        }

        $chart = ChartConfig::areaLine(
            'task-velocity',
            'Velocidade de tarefas criadas',
            $velocity['labels'],
            [['label' => 'Novas tarefas', 'data' => $velocity['values']]],
            'Throughput mensal de criação de entregas no portfólio',
        )->toArray();

        return $this->withKpi($chart, 'Total', array_sum($velocity['values']));
    }

    /** @return ?array<string, mixed> */
    private function buildRhActivityChart(Empresa $empresa): ?array
    {
        $onboarding = $this->countEntityRegistrationsLastMonths($empresa, RhOnboardingProcess::class, 6);
        $offboarding = $this->countEntityRegistrationsLastMonths($empresa, RhOffboardingProcess::class, 6);

        if (!$this->hasValues($onboarding['values']) && !$this->hasValues($offboarding['values'])) {
            return null;
        }

        $datasets = array_values(array_filter([
            $this->hasValues($onboarding['values'])
                ? ['label' => 'Admissões', 'data' => $onboarding['values']]
                : null,
            $this->hasValues($offboarding['values'])
                ? ['label' => 'Desligamentos', 'data' => $offboarding['values']]
                : null,
        ]));

        $chart = ChartConfig::areaLine(
            'rh-activity-trend',
            'Movimentações RH (6 meses)',
            $onboarding['labels'],
            $datasets,
            'Pulsos mensais de admissões e desligamentos abertos',
        )->toArray();

        return $this->withKpi(
            $chart,
            'Processos',
            array_sum($onboarding['values']) + array_sum($offboarding['values']),
        );
    }

    /**
     * @return ?array{
     *     depts: list<string>,
     *     statuses: list<string>,
     *     map: array<string, int>,
     *     total: int
     * }
     */
    private function fetchPeopleMatrix(Empresa $empresa): ?array
    {
        $rows = $this->funcionarioRepo->createQueryBuilder('f')
            ->select('COALESCE(d.nome, :sem) AS dept, f.status AS status, COUNT(f.id) AS total')
            ->leftJoin('f.departamento', 'd')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->setParameter('sem', 'Sem departamento')
            ->groupBy('dept, status')
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return null;
        }

        $depts = [];
        $statuses = [];
        $map = [];
        $total = 0;

        foreach ($rows as $row) {
            $dept = (string) $row['dept'];
            $status = self::FUNC_STATUS_LABELS[(string) $row['status']] ?? (string) $row['status'];
            $count = (int) $row['total'];
            if (!\in_array($dept, $depts, true)) {
                $depts[] = $dept;
            }
            if (!\in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }
            $map[$dept . '|' . $status] = $count;
            $total += $count;
        }

        return ['depts' => $depts, 'statuses' => $statuses, 'map' => $map, 'total' => $total];
    }

    /**
     * @param class-string $entityClass
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    private function countEntityRegistrationsLastMonths(?Empresa $empresa, string $entityClass, int $months): array
    {
        $tz = new DateTimeZone(self::TZ);
        $now = new DateTimeImmutable('now', $tz);
        $labels = [];
        $values = [];

        $repo = match ($entityClass) {
            DevTarefa::class => $this->tarefaRepo,
            RhOnboardingProcess::class => $this->onboardingRepo,
            RhOffboardingProcess::class => $this->offboardingRepo,
            default => throw new \InvalidArgumentException('Unsupported entity'),
        };

        for ($i = $months - 1; $i >= 0; --$i) {
            $monthStart = $now->modify('first day of this month')->modify("-{$i} months")->setTime(0, 0);
            $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);
            $labels[] = $this->formatMonthLabel($monthStart);

            $qb = $repo->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->andWhere('e.criadoEm BETWEEN :start AND :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd);

            if ($empresa !== null) {
                $qb->andWhere('e.empresa = :empresa')->setParameter('empresa', $empresa);
            }

            $values[] = (int) $qb->getQuery()->getSingleScalarResult();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @param list<int> $values */
    private function hasValues(array $values): bool
    {
        return array_sum($values) > 0;
    }

    /** @return array{labels: list<string>, values: list<int>} */
    private function countUsersByPerfil(?Empresa $empresa, bool $isTenant): array
    {
        $qb = $this->userRepo->createQueryBuilder('u')
            ->select('u.perfil AS perfil, COUNT(u.id) AS total')
            ->groupBy('u.perfil')
            ->orderBy('total', 'DESC');

        if (!$isTenant && $empresa !== null) {
            $qb->andWhere('u.empresa = :empresa')->setParameter('empresa', $empresa);
        }

        return $this->mapGroupedRows($qb->getQuery()->getArrayResult(), 'perfil', self::PERFIL_LABELS);
    }

    /** @return array{labels: list<string>, values: list<int>} */
    private function countUsersByAtivo(?Empresa $empresa, bool $isTenant): array
    {
        $labels = ['Ativos', 'Inativos'];
        $values = [0, 0];

        $qb = $this->userRepo->createQueryBuilder('u')
            ->select('u.ativo AS ativo, COUNT(u.id) AS total')
            ->groupBy('u.ativo');

        if (!$isTenant && $empresa !== null) {
            $qb->andWhere('u.empresa = :empresa')->setParameter('empresa', $empresa);
        }

        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $idx = ($row['ativo'] ?? false) ? 0 : 1;
            $values[$idx] = (int) $row['total'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels: list<string>, values: list<int>, total: int} */
    private function countEmpresasAtivas(): array
    {
        $total = (int) $this->empresaRepo->count([]);
        $ativas = (int) $this->empresaRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.ativo = true')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'labels' => ['Ativas', 'Inativas'],
            'values' => [$ativas, max(0, $total - $ativas)],
            'total' => $total,
        ];
    }

    /** @return array{labels: list<string>, values: list<int>} */
    private function countFuncionariosByStatus(Empresa $empresa): array
    {
        $qb = $this->funcionarioRepo->createQueryBuilder('f')
            ->select('f.status AS status, COUNT(f.id) AS total')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('f.status')
            ->orderBy('total', 'DESC');

        return $this->mapGroupedRows($qb->getQuery()->getArrayResult(), 'status', self::FUNC_STATUS_LABELS);
    }

    /** @return array{labels: list<string>, values: list<int>} */
    private function countFuncionariosByDepartamento(Empresa $empresa): array
    {
        $rows = $this->funcionarioRepo->createQueryBuilder('f')
            ->select('COALESCE(d.nome, :sem) AS nome, COUNT(f.id) AS total')
            ->leftJoin('f.departamento', 'd')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->setParameter('sem', 'Sem departamento')
            ->groupBy('nome')
            ->orderBy('total', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getArrayResult();

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = (string) $row['nome'];
            $values[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param class-string<RhOnboardingProcess|RhOffboardingProcess> $entityClass
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    private function countRhByStatus(string $entityClass, Empresa $empresa): array
    {
        $repo = $entityClass === RhOnboardingProcess::class
            ? $this->onboardingRepo
            : $this->offboardingRepo;

        $qb = $repo->createQueryBuilder('p')
            ->select('p.status AS status, COUNT(p.id) AS total')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('p.status')
            ->orderBy('total', 'DESC');

        return $this->mapGroupedRows($qb->getQuery()->getArrayResult(), 'status', self::RH_STATUS_LABELS);
    }

    /** @return array{labels: list<string>, values: list<int>} */
    private function countTarefasByStatus(Empresa $empresa): array
    {
        $qb = $this->tarefaRepo->createQueryBuilder('t')
            ->select('t.status AS status, COUNT(t.id) AS total')
            ->andWhere('t.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('t.status')
            ->orderBy('total', 'DESC');

        return $this->mapGroupedRows($qb->getQuery()->getArrayResult(), 'status', DevTarefa::KANBAN_COLUMNS);
    }

    /** @return array{labels: list<string>, values: list<int>} */
    private function countProjetosByStatus(Empresa $empresa): array
    {
        $labelsMap = [
            DevProjeto::STATUS_IDEIA => 'Ideia',
            DevProjeto::STATUS_EM_ANDAMENTO => 'Em andamento',
            DevProjeto::STATUS_PAUSADO => 'Pausado',
            DevProjeto::STATUS_FEITO => 'Concluído',
        ];

        $qb = $this->projetoRepo->createQueryBuilder('p')
            ->select('p.status AS status, COUNT(p.id) AS total')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('p.status')
            ->orderBy('total', 'DESC');

        return $this->mapGroupedRows($qb->getQuery()->getArrayResult(), 'status', $labelsMap);
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     users: list<int>,
     *     funcionarios: list<int>
     * }
     */
    private function countRegistrationsLastMonths(?Empresa $empresa, bool $isTenant, int $months): array
    {
        $tz = new DateTimeZone(self::TZ);
        $now = new DateTimeImmutable('now', $tz);
        $labels = [];
        $users = [];
        $funcionarios = [];

        for ($i = $months - 1; $i >= 0; --$i) {
            $monthStart = $now->modify('first day of this month')->modify("-{$i} months")->setTime(0, 0);
            $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);
            $labels[] = $this->formatMonthLabel($monthStart);

            $userQb = $this->userRepo->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->andWhere('u.criadoEm BETWEEN :start AND :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd);

            if (!$isTenant && $empresa !== null) {
                $userQb->andWhere('u.empresa = :empresa')->setParameter('empresa', $empresa);
            }

            $users[] = (int) $userQb->getQuery()->getSingleScalarResult();

            $funcCount = 0;
            if ($empresa !== null) {
                $funcCount = (int) $this->funcionarioRepo->createQueryBuilder('f')
                    ->select('COUNT(f.id)')
                    ->andWhere('f.empresa = :empresa')
                    ->andWhere('f.criadoEm BETWEEN :start AND :end')
                    ->setParameter('empresa', $empresa)
                    ->setParameter('start', $monthStart)
                    ->setParameter('end', $monthEnd)
                    ->getQuery()
                    ->getSingleScalarResult();
            }
            $funcionarios[] = $funcCount;
        }

        return ['labels' => $labels, 'users' => $users, 'funcionarios' => $funcionarios];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $labelMap
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    private function mapGroupedRows(array $rows, string $field, array $labelMap): array
    {
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $key = (string) ($row[$field] ?? '');
            $labels[] = $labelMap[$key] ?? $key;
            $values[] = (int) ($row['total'] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function formatMonthLabel(DateTimeImmutable $date): string
    {
        $months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        return $months[(int) $date->format('n') - 1] . '/' . $date->format('y');
    }
}
