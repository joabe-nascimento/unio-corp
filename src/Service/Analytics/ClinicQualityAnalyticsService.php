<?php

namespace App\Service\Analytics;

use App\Chart\ChartConfig;
use App\Chart\ChartPanelFactory;
use App\Entity\Empresa;
use App\Repository\PosOperatorioQuestionarioRespostaRepository;
use App\Service\PosOperatorio\ClinicOperationsService;

final class ClinicQualityAnalyticsService
{
    use ClinicChartAnalyticsTrait;

    public function __construct(
        private ClinicOperationsService $operations,
        private PosOperatorioQuestionarioRespostaRepository $questionarios,
        private ChartPanelFactory $chartPanelFactory,
    ) {}

    /**
     * @return array{
     *     sections: list<array<string, mixed>>,
     *     executive: array{kpis: list<array<string, mixed>>},
     *     meta: array{chart_count: int, section_count: int, generated_at: string}
     * }
     */
    public function getChartPayload(Empresa $empresa): array
    {
        $quality = $this->operations->buildQuality($empresa);
        $sections = array_values(array_filter([
            $this->buildContinuitySection($empresa, $quality),
        ]));

        return $this->chartPanelFactory->wrap($sections, $this->buildExecutive($quality));
    }

    /**
     * @param array<string, mixed> $quality
     *
     * @return array{kpis: list<array<string, mixed>>}
     */
    private function buildExecutive(array $quality): array
    {
        $pre = $quality['pre_op'] ?? [];

        return [
            'kpis' => array_values(array_filter([
                $this->executiveKpi('resp', 'Taxa resposta', (int) ($quality['taxa_resposta'] ?? 0), 'fa-percent', 'Questionários de hoje', '%'),
                $this->executiveKpi('sla', 'SLA alertas', (int) ($quality['sla_pct'] ?? 100), 'fa-stopwatch', 'Abertos dentro do prazo', '%'),
                $this->executiveKpi('noshow', 'No-show D−1', (int) ($pre['noshow_d1_taxa'] ?? 0), 'fa-user-xmark', 'Após lembrete de confirmação', '%'),
                $this->executiveKpi('alertas', 'Alertas abertos', (int) ($quality['alertas_abertos'] ?? 0), 'fa-triangle-exclamation', 'Fila clínica agora'),
            ])),
        ];
    }

    /**
     * @param array<string, mixed> $quality
     *
     * @return ?array<string, mixed>
     */
    private function buildContinuitySection(Empresa $empresa, array $quality): ?array
    {
        $charts = array_values(array_filter([
            $this->buildResponseTrend($empresa),
            $this->buildSlaGauge($quality),
            $this->buildPhaseBar($quality),
            $this->buildPreOpRing($quality),
        ]));

        if ($charts === []) {
            return null;
        }

        return $this->makeSection(
            'clinic-quality',
            'Continuidade clínica',
            'Resposta, SLA, fases e anti no-show',
            'fa-chart-line',
            'operational',
            'Qualidade',
            $charts,
        );
    }

    /** @return ?array<string, mixed> */
    private function buildResponseTrend(Empresa $empresa): ?array
    {
        $today = new \DateTimeImmutable('today');
        $labels = [];
        $respondidos = [];
        $taxas = [];

        for ($i = 13; $i >= 0; --$i) {
            $day = $today->modify(sprintf('-%d days', $i));
            $labels[] = $day->format('d/m');
            $resp = $this->questionarios->countByEmpresaOnDate($empresa, $day);
            $pend = $this->questionarios->countPacientesPendentesHoje($empresa, $day);
            $total = $resp + $pend;
            $respondidos[] = $resp;
            $taxas[] = $total > 0 ? (int) round(($resp / $total) * 100) : ($resp > 0 ? 100 : 0);
        }

        if (!$this->hasValues($respondidos) && !$this->hasValues($taxas)) {
            return null;
        }

        return $this->withKpi(
            ChartConfig::areaLine(
                'quality-response-14d',
                'Taxa de resposta · 14 dias',
                $labels,
                [
                    ['label' => 'Taxa %', 'data' => $taxas],
                    ['label' => 'Respondidos', 'data' => $respondidos],
                ],
                'Questionários respondidos vs pendentes por dia',
            )->toArray(),
            'Hoje',
            (int) end($taxas),
        );
    }

    /**
     * @param array<string, mixed> $quality
     *
     * @return array<string, mixed>
     */
    private function buildSlaGauge(array $quality): array
    {
        $sla = (int) ($quality['sla_pct'] ?? 100);

        return $this->withKpi(
            ChartConfig::gauge(
                'quality-sla-gauge',
                'SLA de alertas abertos',
                $sla,
                100,
                'Percentual ainda dentro do prazo',
                '%',
            )->toArray(),
            'SLA',
            $sla,
        );
    }

    /**
     * @param array<string, mixed> $quality
     *
     * @return ?array<string, mixed>
     */
    private function buildPhaseBar(array $quality): ?array
    {
        /** @var array<string, int> $heatmap */
        $heatmap = $quality['heatmap'] ?? [];
        if ($heatmap === []) {
            return null;
        }

        $labels = array_keys($heatmap);
        $data = array_values($heatmap);

        return $this->withKpi(
            ChartConfig::barPro(
                'quality-phase-bar',
                'Pacientes por fase',
                $labels,
                $data,
                'Distribuição relativa à cirurgia',
            )->toArray(),
            'Fases',
            array_sum($data),
        );
    }

    /**
     * @param array<string, mixed> $quality
     *
     * @return ?array<string, mixed>
     */
    private function buildPreOpRing(array $quality): ?array
    {
        $pre = $quality['pre_op'] ?? [];
        $checkOk = (int) ($pre['checkin_ok'] ?? 0);
        $preTotal = (int) ($pre['pre_semana'] ?? 0);
        $checkPending = max(0, $preTotal - $checkOk);
        $noshow = (int) ($pre['noshow_d1'] ?? 0);
        $noshowBase = max(0, (int) ($pre['noshow_base'] ?? 0) - $noshow);

        $labels = ['Check-in ok', 'Check-in pendente', 'Compareceu (D−1)', 'No-show (D−1)'];
        $data = [$checkOk, $checkPending, $noshowBase, $noshow];
        if (!$this->hasValues($data)) {
            return null;
        }

        return $this->withKpi(
            ChartConfig::ring(
                'quality-preop-ring',
                'Pré-op e no-show',
                $labels,
                $data,
                'Check-in da semana e faltas após lembrete D−1',
            )->toArray(),
            'No-show %',
            (int) ($pre['noshow_d1_taxa'] ?? 0),
        );
    }
}
