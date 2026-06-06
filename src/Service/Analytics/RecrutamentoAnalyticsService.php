<?php

namespace App\Service\Analytics;

use App\Chart\ChartConfig;
use App\Chart\ChartPanelFactory;
use App\Entity\Empresa;
use App\Entity\RhVaga;
use App\Repository\RhCandidatoRepository;
use App\Repository\RhVagaRepository;
use App\Rh\RhCandidatoEtapa;
use App\Rh\RhCandidatoOrigem;

final class RecrutamentoAnalyticsService
{
    use ChartAnalyticsTrait;

    private const VAGA_STATUS_LABELS = [
        RhVaga::STATUS_ABERTA => 'Aberta',
        RhVaga::STATUS_PAUSADA => 'Pausada',
        RhVaga::STATUS_FECHADA => 'Fechada',
    ];

    public function __construct(
        private RhCandidatoRepository $candidatoRepo,
        private RhVagaRepository $vagaRepo,
        private ChartPanelFactory $chartPanelFactory,
    ) {}

    /**
     * @return array{
     *     sections: list<array<string, mixed>>,
     *     executive: array{kpis: list<array<string, mixed>>},
     *     meta: array{chart_count: int, section_count: int, generated_at: string}
     * }
     */
    public function getHubChartPayload(Empresa $empresa): array
    {
        return $this->chartPanelFactory->wrap(
            $this->buildSections($empresa),
            $this->buildExecutiveSummary($empresa),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildSections(Empresa $empresa): array
    {
        $sections = [];

        $captacao = array_values(array_filter([
            $this->buildOrigemRing($empresa),
            $this->buildSelectionFunnel($empresa),
            $this->buildOrigemEtapaSankey($empresa),
        ]));

        if ($captacao !== []) {
            $sections[] = $this->makeSection(
                'recrutamento-captacao',
                'Captação e funil',
                'De onde vêm os candidatos e como avançam no processo seletivo',
                'fa-route',
                'operational',
                'Recruitment',
                $captacao,
            );
        }

        $vagas = array_values(array_filter([
            $this->buildVagasStatusRing($empresa),
            $this->buildVagasDepartamentoBar($empresa),
            $this->buildVagasLocalBar($empresa),
        ]));

        if ($vagas !== []) {
            $sections[] = $this->makeSection(
                'recrutamento-vagas',
                'Portfolio de vagas',
                'Distribuição das posições abertas por status, área e local',
                'fa-briefcase',
                'operational',
                'Job openings',
                $vagas,
            );
        }

        return $sections;
    }

    /** @return array{kpis: list<array<string, mixed>>} */
    public function buildExecutiveSummary(Empresa $empresa): array
    {
        $ativos = (int) $this->candidatoRepo->countAtivosByEmpresa($empresa);
        $abertas = (int) $this->vagaRepo->countAbertasByEmpresa($empresa);
        $hireRate = $this->candidatoRepo->hireRatePercent($empresa);
        $origemCounts = $this->candidatoRepo->countByOrigemForEmpresa($empresa);

        $topOrigem = null;
        $topOrigemCount = 0;
        foreach ($origemCounts as $origemId => $count) {
            if ($count > $topOrigemCount) {
                $topOrigemCount = $count;
                $topOrigem = $origemId;
            }
        }

        $kpis = array_values(array_filter([
            $ativos > 0 ? $this->executiveKpi('rec-ativo', 'No funil', $ativos, 'fa-users', 'Candidatos em etapas ativas') : null,
            $abertas > 0 ? $this->executiveKpi('rec-vagas', 'Vagas abertas', $abertas, 'fa-briefcase', 'Posições em recrutamento') : null,
            $hireRate !== null ? $this->executiveKpi('rec-hire', 'Taxa de contratação', $hireRate, 'fa-user-check', 'Contratados sobre total', '%') : null,
            $topOrigem !== null && $topOrigemCount > 0
                ? $this->executiveKpi(
                    'rec-origem',
                    'Canal principal',
                    $topOrigemCount,
                    'fa-bullhorn',
                    RhCandidatoOrigem::label($topOrigem),
                )
                : null,
        ]));

        return ['kpis' => $kpis];
    }

    /** @return ?array<string, mixed> */
    private function buildOrigemRing(Empresa $empresa): ?array
    {
        $counts = $this->candidatoRepo->countByOrigemForEmpresa($empresa);
        $labels = [];
        $values = [];

        foreach (RhCandidatoOrigem::ALL as $origemId) {
            $count = (int) ($counts[$origemId] ?? 0);
            if ($count <= 0) {
                continue;
            }
            $labels[] = RhCandidatoOrigem::label($origemId);
            $values[] = $count;
        }

        if (!$this->hasValues($values)) {
            return null;
        }

        $chart = ChartConfig::ring(
            'rec-origem-ring',
            'Origem dos candidatos',
            $labels,
            $values,
            'Canais de captação — essencial para medir ROI de cada fonte',
        )->toArray();

        return $this->withKpi($chart, 'Candidatos', array_sum($values));
    }

    /** @return ?array<string, mixed> */
    private function buildSelectionFunnel(Empresa $empresa): ?array
    {
        $steps = [];
        foreach (RhCandidatoEtapa::PIPELINE_ORDER as $etapaId) {
            $count = $this->candidatoRepo->countByEtapaForEmpresa($empresa, $etapaId);
            if ($count > 0) {
                $steps[] = ['name' => RhCandidatoEtapa::label($etapaId), 'value' => $count];
            }
        }

        if ($steps === []) {
            return null;
        }

        $chart = ChartConfig::funnel(
            'rec-selection-funnel',
            'Funil de seleção',
            $steps,
            'Distribuição por etapa ativa — triagem até contratação',
        )->toArray();

        return $this->withKpi($chart, 'No funil', array_sum(array_column($steps, 'value')));
    }

    /** @return ?array<string, mixed> */
    private function buildOrigemEtapaSankey(Empresa $empresa): ?array
    {
        $matrix = $this->candidatoRepo->countOrigemEtapaForEmpresa($empresa);
        if ($matrix === []) {
            return null;
        }

        $root = 'Candidatos';
        $nodes = [['name' => $root]];
        $links = [];

        $origemTotals = [];
        foreach ($matrix as $row) {
            $origemTotals[$row['origem']] = ($origemTotals[$row['origem']] ?? 0) + $row['total'];
        }

        foreach ($origemTotals as $origemId => $total) {
            if ($total <= 0) {
                continue;
            }
            $origemLabel = RhCandidatoOrigem::label($origemId);
            $this->ensureSankeyNode($nodes, $origemLabel);
            $links[] = ['source' => $root, 'target' => $origemLabel, 'value' => $total];
        }

        foreach ($matrix as $row) {
            if ($row['total'] <= 0) {
                continue;
            }
            $origemLabel = RhCandidatoOrigem::label($row['origem']);
            $etapaLabel = RhCandidatoEtapa::label($row['etapa']);
            $this->ensureSankeyNode($nodes, $etapaLabel);
            $links[] = [
                'source' => $origemLabel,
                'target' => $etapaLabel,
                'value' => $row['total'],
            ];
        }

        $links = $this->mergeSankeyLinks($links);
        if ($links === []) {
            return null;
        }

        $chart = ChartConfig::sankey(
            'rec-origem-etapa-sankey',
            'Origem → etapa',
            $nodes,
            $links,
            'Fluxo dos candidatos desde o canal de entrada até a etapa atual',
        )->toArray();
        $chart['size'] = 'hero';

        return $this->withKpi($chart, 'Registros', array_sum($origemTotals));
    }

    /** @return ?array<string, mixed> */
    private function buildVagasStatusRing(Empresa $empresa): ?array
    {
        $counts = $this->vagaRepo->countByStatusForEmpresa($empresa);
        $labels = [];
        $values = [];

        foreach (self::VAGA_STATUS_LABELS as $statusId => $label) {
            $count = (int) ($counts[$statusId] ?? 0);
            if ($count <= 0) {
                continue;
            }
            $labels[] = $label;
            $values[] = $count;
        }

        if (!$this->hasValues($values)) {
            return null;
        }

        $chart = ChartConfig::ring(
            'rec-vagas-status',
            'Vagas por status',
            $labels,
            $values,
            'Abertas, pausadas e encerradas no cadastro',
        )->toArray();
        $chart['size'] = 'compact';

        return $this->withKpi($chart, 'Vagas', array_sum($values));
    }

    /** @return ?array<string, mixed> */
    private function buildVagasDepartamentoBar(Empresa $empresa): ?array
    {
        $grouped = $this->vagaRepo->countGroupedByDepartamentoForEmpresa($empresa);
        if (!$this->hasValues($grouped['values'])) {
            return null;
        }

        $chart = ChartConfig::barPro(
            'rec-vagas-depto',
            'Vagas por departamento',
            $grouped['labels'],
            $grouped['values'],
            'Áreas com posições cadastradas — inclui vagas sem departamento',
            true,
        )->toArray();

        return $this->withKpi($chart, 'Posições', array_sum($grouped['values']));
    }

    /** @return ?array<string, mixed> */
    private function buildVagasLocalBar(Empresa $empresa): ?array
    {
        $grouped = $this->vagaRepo->countGroupedByLocalForEmpresa($empresa);
        if (!$this->hasValues($grouped['values'])) {
            return null;
        }

        $chart = ChartConfig::barPro(
            'rec-vagas-local',
            'Vagas por local',
            $grouped['labels'],
            $grouped['values'],
            'Onde as vagas estão alocadas — remoto, híbrido ou sede',
            true,
        )->toArray();

        return $this->withKpi($chart, 'Locais', \count($grouped['labels']));
    }
}
