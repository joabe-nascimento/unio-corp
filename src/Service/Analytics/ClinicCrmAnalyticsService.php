<?php

namespace App\Service\Analytics;

use App\Chart\ChartConfig;
use App\Chart\ChartPanelFactory;
use App\Entity\Crm\CrmOportunidade;
use App\Entity\Empresa;
use App\Service\Crm\CrmService;

final class ClinicCrmAnalyticsService
{
    use ClinicChartAnalyticsTrait;

    public function __construct(
        private CrmService $crm,
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
        $analytics = $this->crm->getAnalytics($empresa);
        $sections = array_values(array_filter([
            $this->buildCommercialSection($analytics),
        ]));

        return $this->chartPanelFactory->wrap($sections, [
            'kpis' => array_values(array_filter([
                $this->executiveKpi('leads', 'Leads', (int) ($analytics['leads_total'] ?? 0), 'fa-user-plus', 'Cadastro total'),
                $this->executiveKpi(
                    'conversao',
                    'Conversão',
                    $analytics['conversao_lead_pct'] ?? 0,
                    'fa-right-left',
                    'Lead → cliente',
                    '%',
                ),
                $this->executiveKpi(
                    'win',
                    'Win rate',
                    $analytics['win_rate_pct'] ?? 0,
                    'fa-trophy',
                    'Ganho / fechados',
                    '%',
                ),
                $this->executiveKpi(
                    'pipeline',
                    'Pipeline',
                    round((float) ($analytics['pipeline_valor'] ?? 0), 0),
                    'fa-chart-line',
                    'Valor em aberto',
                    'R$',
                ),
            ])),
        ]);
    }

    /**
     * @param array<string, mixed> $analytics
     *
     * @return ?array<string, mixed>
     */
    private function buildCommercialSection(array $analytics): ?array
    {
        $charts = array_values(array_filter([
            $this->buildFunnel($analytics),
            $this->buildOrigemBar($analytics),
            $this->buildStageBar($analytics),
        ]));

        if ($charts === []) {
            return null;
        }

        return $this->makeSection(
            'clinic-crm',
            'Comercial',
            'Funil, origem e estágios do pipeline',
            'fa-handshake',
            'operational',
            'CRM',
            $charts,
        );
    }

    /**
     * @param array<string, mixed> $analytics
     *
     * @return ?array<string, mixed>
     */
    private function buildFunnel(array $analytics): ?array
    {
        /** @var array<string, int> $byStage */
        $byStage = $analytics['by_stage'] ?? [];
        $meta = $analytics['stage_meta'] ?? [];
        $order = [
            CrmOportunidade::STAGE_LEAD,
            CrmOportunidade::STAGE_QUALIFICACAO,
            CrmOportunidade::STAGE_PROPOSTA,
            CrmOportunidade::STAGE_NEGOCIACAO,
            CrmOportunidade::STAGE_GANHO,
        ];

        $steps = [];
        foreach ($order as $stage) {
            $qtd = (int) ($byStage[$stage] ?? 0);
            if ($qtd <= 0 && $steps === []) {
                continue;
            }
            $label = (string) ($meta[$stage]['label'] ?? $stage);
            $steps[] = ['name' => $label, 'value' => max(0, $qtd)];
        }

        if ($steps === [] || !$this->hasValues(array_column($steps, 'value'))) {
            return null;
        }

        return $this->withKpi(
            ChartConfig::funnel(
                'crm-funnel',
                'Funil de oportunidades',
                $steps,
                'Estágios ativos até ganho',
            )->toArray(),
            'Abertas',
            array_sum(array_column($steps, 'value')),
        );
    }

    /**
     * @param array<string, mixed> $analytics
     *
     * @return ?array<string, mixed>
     */
    private function buildOrigemBar(array $analytics): ?array
    {
        /** @var array<string, int> $byOrigem */
        $byOrigem = $analytics['by_origem'] ?? [];
        $labelsMap = $analytics['origem_labels'] ?? [];
        $labels = [];
        $data = [];
        foreach ($byOrigem as $origem => $qtd) {
            if ((int) $qtd <= 0) {
                continue;
            }
            $labels[] = (string) ($labelsMap[$origem] ?? $origem);
            $data[] = (int) $qtd;
        }
        if ($data === []) {
            return null;
        }

        return ChartConfig::barPro(
            'crm-origem-bar',
            'Leads por origem',
            $labels,
            $data,
            'Captação comercial',
        )->toArray();
    }

    /**
     * @param array<string, mixed> $analytics
     *
     * @return ?array<string, mixed>
     */
    private function buildStageBar(array $analytics): ?array
    {
        /** @var array<string, int> $byStage */
        $byStage = $analytics['by_stage'] ?? [];
        $meta = $analytics['stage_meta'] ?? [];
        $labels = [];
        $data = [];
        foreach ($byStage as $stage => $qtd) {
            if ((int) $qtd <= 0) {
                continue;
            }
            $labels[] = (string) ($meta[$stage]['label'] ?? $stage);
            $data[] = (int) $qtd;
        }
        if ($data === []) {
            return null;
        }

        return ChartConfig::doughnut(
            'crm-stage-donut',
            'Distribuição por estágio',
            $labels,
            $data,
            'Oportunidades no pipeline',
        )->toArray();
    }
}
