<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Plantão clínico — quem recebe P1 quando o médico responsável não assume.
 */
final class ClinicDutyRosterService
{
    public function __construct(
        private ClinicIntegrationConfigService $integrationConfig,
        private UserRepository $users,
    ) {}

    /** @return list<User> */
    public function listOnCall(Empresa $empresa): array
    {
        $result = [];
        foreach ($this->integrationConfig->getPlantaoUserIds($empresa) as $id) {
            $user = $this->users->find($id);
            if ($user instanceof User && $user->getEmpresa()?->getId() === $empresa->getId()) {
                $result[] = $user;
            }
        }

        return $result;
    }

    /** @param list<int> $userIds */
    public function setOnCall(Empresa $empresa, array $userIds): void
    {
        $valid = [];
        foreach ($this->users->findActiveByEmpresa($empresa) as $user) {
            if (\in_array((int) $user->getId(), array_map('intval', $userIds), true)) {
                $valid[] = (int) $user->getId();
            }
        }
        $this->integrationConfig->setPlantaoUserIds($empresa, $valid);
    }

    /**
     * Preferência: plantonista → médico responsável → null.
     */
    public function pickForAlert(Empresa $empresa, ?PosOperatorioPaciente $paciente = null, string $prioridade = 'P1'): ?User
    {
        if ($prioridade === 'P1') {
            $onCall = $this->listOnCall($empresa);
            if ($onCall !== []) {
                return $onCall[0];
            }
        }

        return $paciente?->getMedicoResponsavel();
    }

    /** @return list<array{id: int, nome: string, email: ?string, on_call: bool}> */
    public function candidates(Empresa $empresa): array
    {
        $onCallIds = $this->integrationConfig->getPlantaoUserIds($empresa);
        $rows = [];
        foreach ($this->users->findActiveByEmpresa($empresa) as $user) {
            $rows[] = [
                'id' => (int) $user->getId(),
                'nome' => (string) ($user->getNome() ?? $user->getEmail()),
                'email' => $user->getEmail(),
                'on_call' => \in_array((int) $user->getId(), $onCallIds, true),
            ];
        }

        return $rows;
    }
}
