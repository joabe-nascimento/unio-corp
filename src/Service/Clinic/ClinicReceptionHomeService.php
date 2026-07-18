<?php

namespace App\Service\Clinic;

use App\Entity\ClinicAgendamento;
use App\Entity\Empresa;
use App\Service\PosOperatorio\ClinicAgendaService;
use App\Service\PosOperatorio\ClinicOperationsService;

/**
 * Dados da tela inicial operacional — agenda do dia, pendências e painel clínico.
 */
final class ClinicReceptionHomeService
{
    public function __construct(
        private ClinicAgendaService $agenda,
        private ClinicDayPanelService $dayPanel,
        private ClinicOperationsService $operations,
        private ClinicPublicBookingService $booking,
        private ClinicAssinaturaService $assinaturas,
        private ClinicTarefaService $tarefas,
    ) {}

    /** @return array<string, mixed> */
    public function build(Empresa $empresa): array
    {
        $dayData = $this->agenda->listDay($empresa);
        $stats = $this->agenda->countByStatus($dayData['items']);
        $queue = $this->operations->buildWorkQueue($empresa);
        $painel = $this->dayPanel->build($empresa);
        $assinatura = $this->assinaturas->dashboardSummary($empresa, 5);
        $bookingUrl = $this->booking->publicUrl($empresa);

        return [
            'agenda' => [
                'day' => $dayData['day'],
                'stats' => $stats,
                'cards' => $this->buildAgendaCards($stats),
                'slots' => $this->formatAgendaSlots($dayData['items']),
                'total' => $stats['total'],
            ],
            'painel' => $painel,
            'queue' => $queue,
            'booking' => [
                'url' => $bookingUrl,
                'pending' => $this->booking->countPending($empresa),
                'items' => $this->booking->listPendingRows($empresa, 4),
            ],
            'assinaturas' => $assinatura,
            'tarefas' => [
                'pending' => $this->tarefas->countPending($empresa),
                'items' => $this->tarefas->listPendingRows($empresa, 6),
            ],
            'promo' => [
                'title' => 'Link de agendamento',
                'description' => 'Compartilhe com pacientes para solicitar horário online. A recepção confirma e agenda na clínica.',
                'cta' => 'Ver solicitações',
                'secondary_cta' => 'Agenda do dia',
            ],
        ];
    }

    /**
     * @param array<string, int> $stats
     *
     * @return list<array{key: string, label: string, value: int, tone: string, icon: string}>
     */
    private function buildAgendaCards(array $stats): array
    {
        return [
            [
                'key' => 'agendados',
                'label' => 'Agendados',
                'value' => ($stats[ClinicAgendamento::STATUS_MARCADO] ?? 0)
                    + ($stats[ClinicAgendamento::STATUS_CONFIRMADO] ?? 0),
                'tone' => 'sky',
                'icon' => 'fa-calendar-check',
            ],
            [
                'key' => 'na_clinica',
                'label' => 'Na clínica',
                'value' => ($stats[ClinicAgendamento::STATUS_CHEGOU] ?? 0)
                    + ($stats[ClinicAgendamento::STATUS_EM_ATENDIMENTO] ?? 0),
                'tone' => 'amber',
                'icon' => 'fa-door-open',
            ],
            [
                'key' => 'atendidos',
                'label' => 'Atendidos',
                'value' => $stats[ClinicAgendamento::STATUS_ATENDIDO] ?? 0,
                'tone' => 'sage',
                'icon' => 'fa-circle-check',
            ],
            [
                'key' => 'faltas',
                'label' => 'Faltas',
                'value' => $stats[ClinicAgendamento::STATUS_FALTOU] ?? 0,
                'tone' => 'rose',
                'icon' => 'fa-user-xmark',
            ],
            [
                'key' => 'cancelados',
                'label' => 'Cancelados',
                'value' => $stats[ClinicAgendamento::STATUS_CANCELADO] ?? 0,
                'tone' => 'lavender',
                'icon' => 'fa-calendar-xmark',
            ],
        ];
    }

    /**
     * @param list<ClinicAgendamento> $items
     *
     * @return list<array<string, mixed>>
     */
    private function formatAgendaSlots(array $items): array
    {
        usort($items, static fn (ClinicAgendamento $a, ClinicAgendamento $b): int => $a->getInicio() <=> $b->getInicio());

        $labels = ClinicAgendaService::statusLabels();
        $slots = [];

        foreach (\array_slice($items, 0, 8) as $item) {
            $status = $item->getStatus();
            $slots[] = [
                'id' => $item->getId(),
                'hora' => $item->getInicio()->format('H:i'),
                'paciente' => $item->getPaciente()->getNome(),
                'codigo' => $item->getPaciente()->getCodigo(),
                'titulo' => $item->getTitulo() ?: 'Consulta',
                'medico' => $item->getMedico()?->getNome(),
                'status' => $status,
                'status_label' => $labels[$status] ?? $status,
                'tone' => match ($status) {
                    ClinicAgendamento::STATUS_ATENDIDO => 'sage',
                    ClinicAgendamento::STATUS_CHEGOU, ClinicAgendamento::STATUS_EM_ATENDIMENTO => 'amber',
                    ClinicAgendamento::STATUS_FALTOU => 'rose',
                    ClinicAgendamento::STATUS_CANCELADO => 'lavender',
                    ClinicAgendamento::STATUS_CONFIRMADO => 'sky',
                    default => 'default',
                },
            ];
        }

        return $slots;
    }
}
