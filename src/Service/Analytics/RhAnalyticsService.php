<?php

namespace App\Service\Analytics;

use App\Chart\ChartConfig;
use App\Chart\ChartPanelFactory;
use App\Entity\Empresa;
use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Entity\User;
use App\Repository\FuncionarioRepository;
use App\Repository\RhFeriasRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Service\NavigationService;

final class RhAnalyticsService
{
    use ChartAnalyticsTrait;

    public function __construct(
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhOffboardingProcessRepository $offboardingRepo,
        private RhFeriasRepository $feriasRepo,
        private FuncionarioRepository $funcionarioRepo,
        private NavigationService $navigation,
        private ChartPanelFactory $chartPanelFactory,
        private WorkforceAnalyticsService $workforce,
        private GrowthAnalyticsService $growth,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buildSections(User $user, ?Empresa $empresa): array
    {
        if ($empresa === null || !$this->navigation->showModuloRh($user)) {
            return [];
        }

        $rh = array_values(array_filter([
            $this->buildRhSankey($empresa),
            $this->buildRhFunnel($empresa),
            $this->buildRhHealthGauge($empresa),
            $this->buildFeriasPipeline($empresa),
            $this->buildRhThroughputBar($empresa),
        ]));

        if ($rh === []) {
            return [];
        }

        return [
            $this->makeSection(
                'rh-operations',
                'RH Operations',
                'Pipeline de admissões, desligamentos e ciclo de férias',
                'fa-user-tie',
                'operational',
                'Human Resources',
                $rh,
            ),
        ];
    }

    /**
     * @return array{
     *     sections: list<array<string, mixed>>,
     *     executive: array{kpis: list<array<string, mixed>>},
     *     meta: array{chart_count: int, section_count: int, generated_at: string}
     * }
     */
    public function getChartPayload(User $user, Empresa $empresa): array
    {
        return $this->chartPanelFactory->wrap(
            $this->buildSections($user, $empresa),
            $this->buildExecutiveSummary($empresa),
        );
    }

    /**
     * Painel completo do hub RH — workforce + operações + tendências.
     *
     * @return array{
     *     sections: list<array<string, mixed>>,
     *     executive: array{kpis: list<array<string, mixed>>},
     *     meta: array{chart_count: int, section_count: int, generated_at: string}
     * }
     */
    public function getHubChartPayload(User $user, Empresa $empresa): array
    {
        $sections = array_merge(
            $this->workforce->buildSections($user, $empresa),
            $this->buildSections($user, $empresa),
            $this->growth->buildRhHubSections($user, $empresa),
        );

        return $this->chartPanelFactory->wrap(
            $sections,
            $this->buildExecutiveSummary($empresa),
        );
    }

    /**
     * @return array{kpis: list<array<string, mixed>>}
     */
    public function buildExecutiveSummary(Empresa $empresa): array
    {
        $headcount = (int) $this->funcionarioRepo->count(['empresa' => $empresa]);
        $ativos = (int) $this->funcionarioRepo->count(['empresa' => $empresa, 'status' => 'ATIVO']);
        $rhOpen = $this->workforce->countOpenRhProcesses($empresa);

        $kpis = array_values(array_filter([
            $headcount > 0 ? $this->executiveKpi('headcount', 'Colaboradores', $headcount, 'fa-users', 'Cadastro total') : null,
            $ativos > 0 ? $this->executiveKpi('active-people', 'Pessoas ativas', $ativos, 'fa-user-check', 'Status operacional') : null,
            $rhOpen > 0 ? $this->executiveKpi('rh-open', 'RH em aberto', $rhOpen, 'fa-user-clock', 'Admissões e desligamentos') : null,
            $this->buildFeriasExecutiveKpi($empresa),
        ]));

        return ['kpis' => $kpis];
    }

    /** @return ?array<string, mixed> */
    private function buildFeriasExecutiveKpi(Empresa $empresa): ?array
    {
        $pending = (int) $this->feriasRepo->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.empresa = :empresa')
            ->andWhere('f.status IN (:statuses)')
            ->setParameter('empresa', $empresa)
            ->setParameter('statuses', ['SOLICITADA', 'APROVADA', 'EM_GOZO'])
            ->getQuery()
            ->getSingleScalarResult();

        if ($pending <= 0) {
            return null;
        }

        return $this->executiveKpi('ferias-open', 'Férias em ciclo', $pending, 'fa-umbrella-beach', 'Solicitadas, aprovadas ou em gozo');
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
}
