<?php

namespace App\Service\Analytics;

use App\Chart\ChartConfig;
use App\Chart\ChartPanelFactory;
use App\Entity\ClinicAgendamento;
use App\Entity\ClinicConta;
use App\Entity\Empresa;
use App\Repository\ClinicAgendamentoRepository;
use App\Repository\ClinicContaRepository;
use App\Repository\PosOperatorioAlertaRepository;
use App\Repository\PosOperatorioQuestionarioRespostaRepository;

final class ClinicOverviewAnalyticsService
{
    use ClinicChartAnalyticsTrait;

    public function __construct(
        private PosOperatorioAlertaRepository $alertas,
        private ClinicAgendamentoRepository $agendamentos,
        private ClinicContaRepository $contas,
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
    public function getChartPayload(Empresa $empresa, bool $withSections = true): array
    {
        $today = new \DateTimeImmutable('today');
        $dayEnd = $today->modify('+1 day');
        $agendaHoje = $this->agendamentos->findByEmpresaAndInterval($empresa, $today, $dayEnd);
        $confirmados = 0;
        $marcados = 0;
        foreach ($agendaHoje as $a) {
            if ($a->getStatus() === ClinicAgendamento::STATUS_CONFIRMADO) {
                ++$confirmados;
            }
            if (\in_array($a->getStatus(), [ClinicAgendamento::STATUS_MARCADO, ClinicAgendamento::STATUS_CONFIRMADO], true)) {
                ++$marcados;
            }
        }

        $alertasAbertos = $this->alertas->countAbertosByEmpresa($empresa);
        $aReceber = $this->contas->sumValorCentavosByStatus($empresa, ClinicConta::STATUS_ABERTO);
        $respondidos = $this->questionarios->countByEmpresaOnDate($empresa, $today);

        $executive = [
            'kpis' => [
                $this->executiveKpi('alertas', 'Alertas abertos', $alertasAbertos, 'fa-triangle-exclamation', 'Fila clínica agora'),
                $this->executiveKpi('agenda', 'Agenda hoje', \count($agendaHoje), 'fa-calendar-day', 'Horários do dia'),
                $this->executiveKpi('confirm', 'Confirmados', $confirmados, 'fa-circle-check', 'Status confirmado'),
                $this->executiveKpi(
                    'receber',
                    'A receber',
                    round($aReceber / 100, 2),
                    'fa-hand-holding-dollar',
                    'Contas abertas',
                    'R$',
                ),
            ],
        ];

        $sections = [];
        if ($withSections) {
            $sections = array_values(array_filter([
                $this->buildTodaySection($agendaHoje, $respondidos, $alertasAbertos, $marcados, $confirmados),
            ]));
        }

        return $this->chartPanelFactory->wrap($sections, $executive);
    }

    /**
     * @param list<ClinicAgendamento> $agendaHoje
     *
     * @return array<string, mixed>
     */
    private function buildTodaySection(
        array $agendaHoje,
        int $respondidos,
        int $alertasAbertos,
        int $marcados,
        int $confirmados,
    ): array {
        $byStatus = [];
        foreach ($agendaHoje as $a) {
            $st = $a->getStatus();
            $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
        }

        $statusLabels = [
            ClinicAgendamento::STATUS_MARCADO => 'Marcado',
            ClinicAgendamento::STATUS_CONFIRMADO => 'Confirmado',
            ClinicAgendamento::STATUS_ATENDIDO => 'Atendido',
            ClinicAgendamento::STATUS_FALTOU => 'Faltou',
            ClinicAgendamento::STATUS_CANCELADO => 'Cancelado',
        ];

        $labels = [];
        $data = [];
        foreach ($byStatus as $st => $qtd) {
            $labels[] = $statusLabels[$st] ?? $st;
            $data[] = $qtd;
        }

        $charts = [];
        if ($this->hasValues($data)) {
            $charts[] = ChartConfig::ring(
                'overview-agenda-ring',
                'Agenda de hoje',
                $labels,
                $data,
                'Distribuição por status de recepção',
            )->toArray();
        }

        $charts[] = ChartConfig::barPro(
            'overview-hoje-bar',
            'Operação do dia',
            ['Questionários', 'Alertas', 'Marcados', 'Confirmados'],
            [$respondidos, $alertasAbertos, $marcados, $confirmados],
            'Sinais rápidos da clínica hoje',
        )->toArray();

        return $this->makeSection(
            'clinic-overview-today',
            'Performance de hoje',
            'Agenda, confirmações e fila clínica',
            'fa-gauge-high',
            'operational',
            'Hoje',
            $charts,
        );
    }
}
