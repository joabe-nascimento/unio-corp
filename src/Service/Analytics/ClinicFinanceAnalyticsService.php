<?php

namespace App\Service\Analytics;

use App\Chart\ChartConfig;
use App\Chart\ChartPanelFactory;
use App\Entity\ClinicConta;
use App\Entity\Empresa;
use App\Repository\ClinicContaRepository;
use App\Service\PosOperatorio\ClinicFinanceReportService;

final class ClinicFinanceAnalyticsService
{
    use ClinicChartAnalyticsTrait;

    public function __construct(
        private ClinicFinanceReportService $financeReport,
        private ClinicContaRepository $contas,
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
        $report = $this->financeReport->build($empresa);
        $dre = $report['dre'];
        $repasse = $report['repasse'];

        $sections = array_values(array_filter([
            $this->buildFinanceSection($empresa, $dre, $repasse),
        ]));

        return $this->chartPanelFactory->wrap($sections, [
            'kpis' => array_values(array_filter([
                $this->executiveKpi(
                    'receber',
                    'A receber',
                    round(($dre['a_receber_centavos'] ?? 0) / 100, 2),
                    'fa-hand-holding-dollar',
                    'Contas abertas',
                    'R$',
                ),
                $this->executiveKpi(
                    'recebido',
                    'Recebido',
                    round(($dre['recebido_centavos'] ?? 0) / 100, 2),
                    'fa-check',
                    'Contas pagas',
                    'R$',
                ),
                $this->executiveKpi(
                    'glosa',
                    'Glosado',
                    round(($dre['glosado_centavos'] ?? 0) / 100, 2),
                    'fa-ban',
                    'Convênio glosado',
                    'R$',
                ),
                $this->executiveKpi(
                    'pagas',
                    'Contas pagas',
                    (int) ($dre['qtd_pagas'] ?? 0),
                    'fa-receipt',
                    'Volume liquidado',
                ),
            ])),
        ]);
    }

    /**
     * @param array<string, mixed> $dre
     * @param list<array<string, mixed>> $repasse
     *
     * @return ?array<string, mixed>
     */
    private function buildFinanceSection(Empresa $empresa, array $dre, array $repasse): ?array
    {
        $charts = array_values(array_filter([
            $this->buildMixRing($dre),
            $this->buildRevenueTrend($empresa),
            $this->buildRepasseBar($repasse),
        ]));

        if ($charts === []) {
            return null;
        }

        return $this->makeSection(
            'clinic-finance',
            'Financeiro clínico',
            'Mix de receita, tendência e repasse médico',
            'fa-chart-pie',
            'operational',
            'DRE',
            $charts,
        );
    }

    /**
     * @param array<string, mixed> $dre
     *
     * @return ?array<string, mixed>
     */
    private function buildMixRing(array $dre): ?array
    {
        $labels = ['Particular', 'Convênio', 'Cortesia', 'A receber'];
        $data = [
            round(($dre['particular_pago_centavos'] ?? 0) / 100, 2),
            round(($dre['convenio_pago_centavos'] ?? 0) / 100, 2),
            round(($dre['cortesia_centavos'] ?? 0) / 100, 2),
            round(($dre['a_receber_centavos'] ?? 0) / 100, 2),
        ];
        if (!$this->hasValues($data)) {
            return null;
        }

        return $this->withKpi(
            ChartConfig::ring(
                'finance-mix-ring',
                'Mix de receita (R$)',
                $labels,
                $data,
                'Particular, convênio, cortesia e aberto',
            )->toArray(),
            'Recebido',
            round(($dre['recebido_centavos'] ?? 0) / 100, 2),
        );
    }

    /** @return ?array<string, mixed> */
    private function buildRevenueTrend(Empresa $empresa): ?array
    {
        $today = new \DateTimeImmutable('today');
        $since = $today->modify('-29 days')->setTime(0, 0);
        $contas = $this->contas->findByEmpresaAndStatus($empresa, ClinicConta::STATUS_PAGO, 1000);

        /** @var array<string, float> $byDay */
        $byDay = [];
        for ($i = 29; $i >= 0; --$i) {
            $byDay[$today->modify(sprintf('-%d days', $i))->format('Y-m-d')] = 0.0;
        }

        foreach ($contas as $conta) {
            $pagoEm = $conta->getPagoEm() ?? $conta->getAtualizadoEm();
            if ($pagoEm < $since) {
                continue;
            }
            $key = $pagoEm->format('Y-m-d');
            if (!isset($byDay[$key])) {
                continue;
            }
            $byDay[$key] += ((int) ($conta->getValorCentavos() ?? 0)) / 100;
        }

        $labels = [];
        $values = [];
        foreach ($byDay as $ymd => $valor) {
            $labels[] = (new \DateTimeImmutable($ymd))->format('d/m');
            $values[] = round($valor, 2);
        }

        if (!$this->hasValues($values)) {
            return null;
        }

        return $this->withKpi(
            ChartConfig::areaLine(
                'finance-revenue-30d',
                'Receita recebida · 30 dias',
                $labels,
                [['label' => 'R$ recebido', 'data' => $values]],
                'Soma diária das contas pagas',
            )->toArray(),
            '30d',
            round(array_sum($values), 2),
        );
    }

    /**
     * @param list<array<string, mixed>> $repasse
     *
     * @return ?array<string, mixed>
     */
    private function buildRepasseBar(array $repasse): ?array
    {
        if ($repasse === []) {
            return null;
        }

        $top = array_slice($repasse, 0, 8);
        $labels = array_map(static fn (array $r): string => (string) $r['medico_nome'], $top);
        $data = array_map(static fn (array $r): float => round(((int) $r['total_centavos']) / 100, 2), $top);

        return $this->withKpi(
            ChartConfig::barPro(
                'finance-repasse-bar',
                'Repasse por médico (R$)',
                $labels,
                $data,
                'Contas pagas (particular + convênio)',
                true,
            )->toArray(),
            'Médicos',
            \count($top),
        );
    }
}
