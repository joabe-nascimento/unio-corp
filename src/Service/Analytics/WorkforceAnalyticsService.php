<?php

namespace App\Service\Analytics;

use App\Chart\ChartConfig;
use App\Entity\DevProjeto;
use App\Entity\Empresa;
use App\Entity\RhOnboardingProcess;
use App\Entity\User;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Repository\UserRepository;
use App\Service\NavigationService;

final class WorkforceAnalyticsService
{
    use ChartAnalyticsTrait;

    public function __construct(
        private FuncionarioRepository $funcionarioRepo,
        private DevProjetoRepository $projetoRepo,
        private DevTarefaRepository $tarefaRepo,
        private UserRepository $userRepo,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhOffboardingProcessRepository $offboardingRepo,
        private NavigationService $navigation,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buildSections(User $user, ?Empresa $empresa): array
    {
        if ($empresa === null || !($this->navigation->showModuloRh($user) || $this->navigation->showModuloPessoas($user))) {
            return [];
        }

        $workforce = array_values(array_filter([
            $this->buildPeopleSankey($empresa),
            $this->buildPeopleHeatmap($empresa),
            $this->buildWorkforceStackedBar($empresa),
            $this->buildWorkforceTreemap($empresa),
            $this->buildHeadcountByDeptBar($empresa),
            $this->buildWorkforceStatusRing($empresa),
            $this->buildOperationalRadar($user, $empresa),
        ]));

        if ($workforce === []) {
            return [];
        }

        return [
            $this->makeSection(
                'workforce-intelligence',
                'Workforce Intelligence',
                'Composição, distribuição e saúde do capital humano em tempo real',
                'fa-users-viewfinder',
                'executive',
                'People Analytics',
                $workforce,
            ),
        ];
    }

    public function countOpenRhProcesses(Empresa $empresa): int
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
}
