<?php

namespace App\Service\Analytics;

use App\Chart\ChartConfig;
use App\Entity\DevProjeto;
use App\Entity\DevTarefa;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;
use App\Service\NavigationService;

final class DeliveryAnalyticsService
{
    use ChartAnalyticsTrait;

    public function __construct(
        private DevTarefaRepository $tarefaRepo,
        private DevProjetoRepository $projetoRepo,
        private NavigationService $navigation,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buildSections(User $user, ?Empresa $empresa): array
    {
        if ($empresa === null || !$this->navigation->showProjetosMetas($user)) {
            return [];
        }

        $delivery = array_values(array_filter([
            $this->buildDeliveryGauge($empresa),
            $this->buildKanbanPipelineBar($empresa),
            $this->buildKanbanStackedByProject($empresa),
            $this->buildProjectsTreemap($empresa),
            $this->buildProjectsBubble($empresa),
            $this->buildProjectStatusRing($empresa),
        ]));

        if ($delivery === []) {
            return [];
        }

        return [
            $this->makeSection(
                'project-delivery',
                'Project Delivery',
                'Portfólio, throughput de tarefas e maturidade de entregas',
                'fa-rocket',
                'operational',
                'Delivery Office',
                $delivery,
            ),
        ];
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
}
