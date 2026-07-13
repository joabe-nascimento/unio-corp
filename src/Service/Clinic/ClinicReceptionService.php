<?php

namespace App\Service\Clinic;

use App\Entity\ClinicAgendamento;
use App\Entity\ClinicCheckin;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\ClinicAgendamentoRepository;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\PosOperatorio\ClinicAgendaService;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicReceptionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PosOperatorioPacienteRepository $pacientes,
        private ClinicWebhookDispatcherBridge $webhooks,
        private ClinicAgendamentoRepository $agendamentos,
        private ClinicAgendaService $agenda,
    ) {}

    /**
     * @return array{ok: true, paciente: PosOperatorioPaciente, checkin: ClinicCheckin, agendamentos_chegou: list<int>}|array{ok: false, error: string}
     */
    public function checkin(
        Empresa $empresa,
        string $metodo,
        string $valor,
        ?User $recepcionista = null,
    ): array {
        $paciente = $this->resolvePaciente($empresa, $metodo, $valor);
        if ($paciente === null) {
            return ['ok' => false, 'error' => 'Paciente não encontrado para este código ou CPF.'];
        }

        $checkin = (new ClinicCheckin())
            ->setEmpresa($empresa)
            ->setPaciente($paciente)
            ->setUnidade($paciente->getUnidade())
            ->setRecepcionista($recepcionista)
            ->setMetodo($metodo)
            ->setCodigoUsado(strtoupper(trim($valor)));

        $this->em->persist($checkin);
        $this->em->flush();

        $agendamentosChegou = $this->markTodayAppointmentsChegou($empresa, $paciente);

        $this->webhooks->checkinRealizado($empresa, $paciente, $checkin);

        return [
            'ok' => true,
            'paciente' => $paciente,
            'checkin' => $checkin,
            'agendamentos_chegou' => $agendamentosChegou,
        ];
    }

    /**
     * @return list<int>
     */
    private function markTodayAppointmentsChegou(Empresa $empresa, PosOperatorioPaciente $paciente): array
    {
        $start = new \DateTimeImmutable('today');
        $end = $start->modify('+1 day');
        $ids = [];

        foreach ($this->agendamentos->findTodayAwaitingReception($empresa, $paciente, $start, $end) as $agendamento) {
            try {
                $this->agenda->changeStatus($agendamento, $empresa, ClinicAgendamento::STATUS_CHEGOU);
                if ($agendamento->getId() !== null) {
                    $ids[] = $agendamento->getId();
                }
            } catch (\InvalidArgumentException) {
                // Mantém check-in mesmo se a transição de agenda falhar.
            }
        }

        return $ids;
    }

    private function resolvePaciente(Empresa $empresa, string $metodo, string $valor): ?PosOperatorioPaciente
    {
        $valor = trim($valor);

        return match ($metodo) {
            ClinicCheckin::METODO_QR => $this->pacientes->findByAnyVerificacaoGlobal(strtoupper($valor)),
            ClinicCheckin::METODO_CPF => $this->pacientes->findByCpfAndEmpresa($empresa, $valor),
            ClinicCheckin::METODO_CODIGO => $this->pacientes->findByCodigo($empresa, strtoupper($valor)),
            default => null,
        };
    }
}
