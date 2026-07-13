<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicAgendamento;
use App\Entity\Empresa;
use App\Repository\ClinicAgendamentoRepository;
use App\Repository\EmpresaRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Confirmação de agenda D-1: WhatsApp Meta (se live), wa.me, e-mail e webhook agenda_confirmacao.
 */
final class ClinicAgendaReminderService
{
    public function __construct(
        private ClinicAgendamentoRepository $agendamentos,
        private ClinicPatientNotifier $patientNotifier,
        private EmpresaRepository $empresas,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return array{
     *     enviados: int,
     *     ignorados: int,
     *     sem_telefone: int,
     *     items: list<array<string, mixed>>
     * }
     */
    public function prepareForTomorrow(Empresa $empresa, ?\DateTimeImmutable $reference = null): array
    {
        $ref = $reference ?? new \DateTimeImmutable('today');
        $dayStart = $ref->modify('+1 day')->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        $pending = $this->agendamentos->findPendingConfirmacaoReminders($empresa, $dayStart, $dayEnd);
        $enviados = 0;
        $semTelefone = 0;
        $items = [];

        foreach ($pending as $agendamento) {
            $result = $this->patientNotifier->notifyAgendaConfirmacao($agendamento);
            $agendamento->setLembreteConfirmacaoEm(new \DateTimeImmutable());
            $agendamento->touch();
            ++$enviados;

            if ($result['whatsapp_url'] === null) {
                ++$semTelefone;
            }

            $items[] = $this->mapItem($agendamento, $result['whatsapp_url']);
        }

        if ($enviados > 0) {
            $this->em->flush();
        }

        return [
            'enviados' => $enviados,
            'ignorados' => 0,
            'sem_telefone' => $semTelefone,
            'items' => $items,
        ];
    }

    /**
     * @return array{empresas: int, enviados: int, sem_telefone: int}
     */
    public function runAll(?int $empresaId = null): array
    {
        $list = $empresaId
            ? array_filter([$this->empresas->find($empresaId)])
            : $this->empresas->findBy(['ativo' => true]);

        $enviados = 0;
        $semTelefone = 0;
        $count = 0;

        foreach ($list as $empresa) {
            if (!$empresa instanceof Empresa) {
                continue;
            }
            ++$count;
            $result = $this->prepareForTomorrow($empresa);
            $enviados += $result['enviados'];
            $semTelefone += $result['sem_telefone'];
        }

        return ['empresas' => $count, 'enviados' => $enviados, 'sem_telefone' => $semTelefone];
    }

    /**
     * Painel: horários de amanhã (com ou sem lembrete já disparado).
     *
     * @return list<array<string, mixed>>
     */
    public function panelForTomorrow(Empresa $empresa): array
    {
        $dayStart = (new \DateTimeImmutable('today'))->modify('+1 day')->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');
        $list = $this->agendamentos->findForConfirmacaoPanel($empresa, $dayStart, $dayEnd);
        $out = [];

        foreach ($list as $agendamento) {
            $url = $this->patientNotifier->confirmWhatsappUrlForAgendamento($agendamento);
            $out[] = $this->mapItem($agendamento, $url);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapItem(ClinicAgendamento $agendamento, ?string $whatsappUrl): array
    {
        $paciente = $agendamento->getPaciente();

        return [
            'id' => $agendamento->getId(),
            'paciente_id' => $paciente->getId(),
            'paciente' => $paciente->getNome(),
            'codigo' => $paciente->getCodigo(),
            'quando' => $agendamento->getInicio()->format('d/m/Y H:i'),
            'titulo' => $agendamento->getTitulo() ?: 'Consulta',
            'status' => $agendamento->getStatus(),
            'lembrete_em' => $agendamento->getLembreteConfirmacaoEm()?->format('d/m H:i'),
            'whatsapp_url' => $whatsappUrl,
            'telefone' => $paciente->getTelefoneContato(),
        ];
    }
}
