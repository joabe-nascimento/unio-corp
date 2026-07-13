<?php

namespace App\Service\Organismo\Contract;

use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoCareContract;
use App\Entity\PosOperatorioPaciente;
use App\Entity\PosOperatorioProtocolo;
use App\Repository\Organismo\OrganismoCareContractRepository;
use App\Service\Organismo\Memory\OrganismMemoryWriter;
use Doctrine\ORM\EntityManagerInterface;

final class CareContractService
{
    public function __construct(
        private OrganismoCareContractRepository $contracts,
        private EntityManagerInterface $em,
        private OrganismMemoryWriter $memory,
    ) {
    }

    public function ensureForPaciente(PosOperatorioPaciente $paciente): ?OrganismoCareContract
    {
        $protocolo = $paciente->getProtocolo();
        if ($protocolo === null) {
            return $this->contracts->findActiveForPaciente($paciente);
        }

        $active = $this->contracts->findActiveForPaciente($paciente);
        $snapshot = $this->snapshotFromProtocolo($protocolo);
        $hash = $this->hashSnapshot($snapshot);

        if ($active !== null && $active->getContentHash() === $hash) {
            return $active;
        }

        if ($active !== null) {
            $active->setAtivo(false)->setStatus(OrganismoCareContract::STATUS_ENCERRADO);
        }

        $contract = new OrganismoCareContract();
        $contract->setEmpresa($paciente->getEmpresa())
            ->setPaciente($paciente)
            ->setProtocolo($protocolo)
            ->setVersao($this->contracts->nextVersion($paciente))
            ->setStatus(OrganismoCareContract::STATUS_ATIVO)
            ->setAtivo(true)
            ->setSnapshot($snapshot)
            ->setContentHash($hash);
        $this->em->persist($contract);
        $this->memory->remember(
            $paciente->getEmpresa(),
            'contrato_criado',
            sprintf('Contrato v%d — %s', $contract->getVersao(), $protocolo->getNome()),
            ['hash' => $hash, 'protocolo' => $protocolo->getNome()],
            15,
            $paciente,
        );
        $this->em->flush();

        return $contract;
    }

    /** @return list<OrganismoCareContract> */
    public function listActive(Empresa $empresa): array
    {
        return $this->contracts->findActiveByEmpresa($empresa);
    }

    public function findActive(PosOperatorioPaciente $paciente): ?OrganismoCareContract
    {
        return $this->contracts->findActiveForPaciente($paciente);
    }

    /** @return array<string, mixed> */
    private function snapshotFromProtocolo(PosOperatorioProtocolo $protocolo): array
    {
        return [
            'protocolo_id' => $protocolo->getId(),
            'nome' => $protocolo->getNome(),
            'tipo' => $protocolo->getTipoProcedimento(),
            'duracao_dias' => $protocolo->getDuracaoDias(),
            'checklist' => $protocolo->getChecklist(),
            'perguntas' => $protocolo->getPerguntas(),
            'regras_alerta' => $protocolo->getRegrasAlerta(),
            'capturado_em' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function hashSnapshot(array $snapshot): string
    {
        $json = json_encode($snapshot, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return hash('sha256', $json === false ? '{}' : $json);
    }
}
