<?php

namespace App\Service\Clinic;

use App\Entity\ClinicCheckin;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\PosOperatorioPacienteRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicReceptionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PosOperatorioPacienteRepository $pacientes,
        private ClinicWebhookDispatcherBridge $webhooks,
    ) {}

    /**
     * @return array{ok: true, paciente: PosOperatorioPaciente, checkin: ClinicCheckin}|array{ok: false, error: string}
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

        $this->webhooks->checkinRealizado($empresa, $paciente, $checkin);

        return ['ok' => true, 'paciente' => $paciente, 'checkin' => $checkin];
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
