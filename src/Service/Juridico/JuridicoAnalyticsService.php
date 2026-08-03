<?php

namespace App\Service\Juridico;

use App\Chart\ChartConfig;
use App\Chart\ChartPanelBuilder;
use App\Entity\Empresa;
use App\Entity\JuridicoProcesso;
use App\Repository\JuridicoCobrancaRepository;
use App\Repository\JuridicoHonorarioLancamentoRepository;
use App\Repository\JuridicoPrazoRepository;
use App\Repository\JuridicoProcessoRepository;

/**
 * BI de Carteira — Analytics Jurídico. Consolida dados reais da carteira (processos,
 * prazos e honorários) em gráficos e KPIs executivos, com suporte a visão consolidada
 * de grupo (matriz + filiais) para escritórios em rede.
 */
final class JuridicoAnalyticsService
{
    public function __construct(
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoPrazoRepository $prazoRepo,
        private JuridicoHonorarioLancamentoRepository $honorarioRepo,
        private JuridicoCobrancaRepository $cobrancaRepo,
    ) {
    }

    /**
     * @return array{
     *     processos_total: int, processos_ativos: int, processos_criticos: int,
     *     valor_carteira: float, taxa_exito: ?float, sla_prazos: float,
     *     receita_mes: float, escritorios: int,
     *     recebido_mes: float, titulos_aberto: float, titulos_vencidos: int
     * }
     */
    public function kpis(Empresa $empresa, bool $consolidado): array
    {
        $empresas = $consolidado ? $empresa->grupoEconomico() : [$empresa];

        $ativos = $this->processoRepo->countByEmpresasAndStatus($empresas, JuridicoProcesso::STATUS_ATIVO);
        $criticos = $this->processoRepo->countByEmpresasAndStatus($empresas, JuridicoProcesso::STATUS_CRITICO);
        $encerrados = $this->processoRepo->countByEmpresasAndStatus($empresas, JuridicoProcesso::STATUS_ENCERRADO);
        $sla = $this->prazoRepo->slaGrupo($empresas);

        return [
            'processos_total' => $ativos + $criticos + $encerrados,
            'processos_ativos' => $ativos,
            'processos_criticos' => $criticos,
            'valor_carteira' => $this->processoRepo->sumValorAtivoByEmpresas($empresas),
            'taxa_exito' => $this->processoRepo->taxaExitoGrupo($empresas),
            'sla_prazos' => $sla['taxa'],
            'receita_mes' => $this->honorarioRepo->sumReceitaGrupoMes($empresas),
            'recebido_mes' => $this->cobrancaRepo->sumRecebidoMes($empresas),
            'titulos_aberto' => $this->cobrancaRepo->sumAberto($empresas),
            'titulos_vencidos' => $this->cobrancaRepo->countVencidas($empresas),
            'escritorios' => \count($empresas),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildSections(Empresa $empresa, bool $consolidado): array
    {
        $empresas = $consolidado ? $empresa->grupoEconomico() : [$empresa];
        $builder = new ChartPanelBuilder();

        $carteira = array_values(array_filter([
            $this->buildStatusRing($empresas),
            $this->buildFaseBar($empresas),
            $this->buildAreaBar($empresas),
            $this->buildTribunalDoughnut($empresas),
        ]));
        $builder->addSectionIfNotEmpty(
            'carteira',
            'Composição da carteira',
            'Distribuição dos processos por status, fase, área e tribunal',
            'fa-scale-balanced',
            $carteira,
        );

        $evolucao = array_values(array_filter([
            $this->buildEvolucaoLine($empresas),
            $this->buildReceitaLine($empresas),
            $this->buildRecebidoLine($empresas),
        ]));
        $builder->addSectionIfNotEmpty(
            'evolucao',
            'Evolução mensal',
            'Novos processos e receita de honorários nos últimos 6 meses',
            'fa-chart-line',
            $evolucao,
        );

        $produtividade = array_values(array_filter([
            $this->buildProdutividadeGauge($empresas),
            $this->buildAgingBar($empresas),
        ]));
        $builder->addSectionIfNotEmpty(
            'produtividade',
            'Produtividade e cumprimento',
            'Saúde operacional: prazos cumpridos dentro do prazo (SLA)',
            'fa-gauge-high',
            $produtividade,
        );

        return $builder->build();
    }

    /** @param list<Empresa> $empresas */
    private function buildStatusRing(array $empresas): ?ChartConfig
    {
        $dados = $this->processoRepo->countByStatusGrouped($empresas);
        if (array_sum($dados['values']) === 0) {
            return null;
        }

        $chart = ChartConfig::ring('bi-status', 'Processos por status', $dados['labels'], $dados['values'], 'Ativos, críticos e encerrados na carteira');

        return $this->comKpi($chart->with(['size' => 'compact']), 'Total', array_sum($dados['values']));
    }

    /** @param list<Empresa> $empresas */
    private function buildFaseBar(array $empresas): ?ChartConfig
    {
        $dados = $this->processoRepo->countByFaseGrouped($empresas);
        if (array_sum($dados['values']) === 0) {
            return null;
        }

        return ChartConfig::bar('bi-fase', 'Processos por fase', $dados['labels'], $dados['values'], 'Do conhecimento à execução');
    }

    /** @param list<Empresa> $empresas */
    private function buildAreaBar(array $empresas): ?ChartConfig
    {
        $dados = $this->processoRepo->countByAreaGrouped($empresas);
        if (array_sum($dados['values']) === 0) {
            return null;
        }

        return ChartConfig::bar('bi-area', 'Processos por área', $dados['labels'], $dados['values'], 'Top 10 áreas do direito com mais casos', true);
    }

    /** @param list<Empresa> $empresas */
    private function buildTribunalDoughnut(array $empresas): ?ChartConfig
    {
        $dados = $this->processoRepo->countByTribunalGrouped($empresas);
        if (array_sum($dados['values']) === 0) {
            return null;
        }

        return ChartConfig::doughnut('bi-tribunal', 'Processos por tribunal', $dados['labels'], $dados['values'], 'Top 8 tribunais com mais processos ativos');
    }

    /** @param list<Empresa> $empresas */
    private function buildEvolucaoLine(array $empresas): ?ChartConfig
    {
        $dados = $this->processoRepo->evolucaoMensal($empresas, 6);
        if (array_sum($dados['values']) === 0) {
            return null;
        }

        return ChartConfig::line('bi-evolucao', 'Novos processos por mês', $dados['labels'], [
            ['label' => 'Processos cadastrados', 'data' => $dados['values']],
        ], 'Cadastros nos últimos 6 meses');
    }

    /** @param list<Empresa> $empresas */
    private function buildReceitaLine(array $empresas): ?ChartConfig
    {
        $dados = $this->honorarioRepo->receitaUltimosMeses($empresas, 6);
        if (array_sum($dados['values']) <= 0) {
            return null;
        }

        return ChartConfig::line('bi-receita', 'Receita de honorários', $dados['labels'], [
            ['label' => 'Receita (R$)', 'data' => array_map(static fn ($v) => round($v, 2), $dados['values'])],
        ], 'Horas faturáveis × valor/hora, últimos 6 meses');
    }

    /** @param list<Empresa> $empresas */
    private function buildRecebidoLine(array $empresas): ?ChartConfig
    {
        $dados = $this->cobrancaRepo->recebidoUltimosMeses($empresas, 6);
        if (array_sum($dados['values']) <= 0) {
            return null;
        }

        return ChartConfig::line('bi-recebido', 'Recebimentos (cobrança)', $dados['labels'], [
            ['label' => 'Recebido (R$)', 'data' => array_map(static fn ($v) => round($v, 2), $dados['values'])],
        ], 'Títulos pagos nos últimos 6 meses');
    }

    /** @param list<Empresa> $empresas */
    private function buildAgingBar(array $empresas): ?ChartConfig
    {
        $dados = $this->cobrancaRepo->agingGrupo($empresas);
        if (array_sum($dados['values']) <= 0) {
            return null;
        }

        return ChartConfig::bar('bi-aging', 'Aging de inadimplência', $dados['labels'], $dados['values'], 'Valor em aberto por faixa de atraso', true);
    }

    /** @param list<Empresa> $empresas */
    private function buildProdutividadeGauge(array $empresas): ?ChartConfig
    {
        $sla = $this->prazoRepo->slaGrupo($empresas);
        if ($sla['cumpridos'] + $sla['vencidos_pendentes'] === 0) {
            return null;
        }

        $chart = ChartConfig::gauge('bi-sla', 'SLA de prazos', $sla['taxa'], 100, 'Percentual de prazos cumpridos dentro do prazo', '%');

        return $this->comKpi($chart->with(['size' => 'compact']), 'Cumpridos', $sla['cumpridos']);
    }

    private function comKpi(ChartConfig $chart, string $label, int|float $value): ChartConfig
    {
        return $chart->with(['kpi' => ['label' => $label, 'value' => $value]]);
    }
}
