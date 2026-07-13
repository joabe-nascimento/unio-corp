<?php

namespace App\Service\Organismo\Contract;

use App\Entity\Organismo\OrganismoCareAttestation;
use App\Entity\Organismo\OrganismoCareContract;
use App\Entity\User;
use App\Repository\Organismo\OrganismoCareAttestationRepository;
use App\Service\Organismo\Memory\OrganismMemoryWriter;
use Doctrine\ORM\EntityManagerInterface;

final class ContractAttestationService
{
    public function __construct(
        private OrganismoCareAttestationRepository $attestations,
        private EntityManagerInterface $em,
        private OrganismMemoryWriter $memory,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function attest(
        OrganismoCareContract $contract,
        string $marcoKey,
        string $evidencia,
        ?User $ator = null,
        array $payload = [],
    ): OrganismoCareAttestation {
        $existing = $this->attestations->findByMarco($contract, $marcoKey);
        if ($existing !== null) {
            return $existing;
        }

        $prev = $this->attestations->findLatest($contract);
        $prevHash = $prev?->getContentHash();
        $body = [
            'contract_id' => $contract->getId(),
            'marco' => $marcoKey,
            'evidencia' => $evidencia,
            'prev' => $prevHash,
            'payload' => $payload,
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
        $json = json_encode($body, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', ($prevHash ?? '').'|'.($json === false ? '{}' : $json));

        $att = new OrganismoCareAttestation();
        $att->setContract($contract)
            ->setMarcoKey(mb_substr($marcoKey, 0, 64))
            ->setEvidencia(mb_substr($evidencia, 0, 255))
            ->setAtor($ator)
            ->setPrevHash($prevHash)
            ->setContentHash($hash)
            ->setPayload($payload);
        $this->em->persist($att);

        $this->memory->remember(
            $contract->getEmpresa(),
            'contrato_atestado',
            sprintf('Marco %s atestado — %s', $marcoKey, $contract->getPaciente()->getNome()),
            ['hash' => $hash, 'marco' => $marcoKey],
            16,
            $contract->getPaciente(),
        );
        $this->em->flush();

        return $att;
    }

    /** @return list<array{key: string, label: string, attested: bool, hash: ?string, em: ?string}> */
    public function milestonesView(OrganismoCareContract $contract): array
    {
        $checklist = $contract->getSnapshot()['checklist'] ?? [];
        $items = [];
        if (\is_array($checklist) && $checklist !== []) {
            foreach ($checklist as $i => $row) {
                $key = 'check_'.($row['dia'] ?? $i);
                $label = sprintf('D+%s — %s', $row['dia'] ?? $i, $row['titulo'] ?? $row['item'] ?? 'Marco');
                $att = $this->attestations->findByMarco($contract, (string) $key);
                $items[] = [
                    'key' => (string) $key,
                    'label' => $label,
                    'attested' => $att !== null,
                    'hash' => $att?->getContentHash(),
                    'em' => $att?->getCriadoEm()->format('d/m/Y H:i'),
                ];
            }
        } else {
            foreach ([0, 1, 3, 7, 14] as $dia) {
                $key = 'd_'.$dia;
                $att = $this->attestations->findByMarco($contract, $key);
                $items[] = [
                    'key' => $key,
                    'label' => 'Marco D+'.$dia,
                    'attested' => $att !== null,
                    'hash' => $att?->getContentHash(),
                    'em' => $att?->getCriadoEm()->format('d/m/Y H:i'),
                ];
            }
        }

        $qKey = 'questionario_hoje';
        $qAtt = $this->attestations->findByMarco($contract, $qKey);
        $items[] = [
            'key' => $qKey,
            'label' => 'Questionário do dia',
            'attested' => $qAtt !== null,
            'hash' => $qAtt?->getContentHash(),
            'em' => $qAtt?->getCriadoEm()->format('d/m/Y H:i'),
        ];

        return $items;
    }
}
