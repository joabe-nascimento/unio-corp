<?php

namespace App\Service\Organismo\Contract;

use App\Entity\Organismo\OrganismoCareAttestation;
use App\Entity\Organismo\OrganismoCareContract;
use App\Entity\PosOperatorioPaciente;
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

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     dia: int|null,
     *     fase: string,
     *     attested: bool,
     *     hash: ?string,
     *     em: ?string
     * }>
     */
    public function milestonesView(OrganismoCareContract $contract): array
    {
        $checklist = $contract->getSnapshot()['checklist'] ?? [];
        $items = [];
        if (\is_array($checklist) && $checklist !== []) {
            $rows = array_values(array_filter(
                $checklist,
                static fn ($row): bool => \is_array($row),
            ));
            usort($rows, static function (array $a, array $b): int {
                return ((int) ($a['dia'] ?? 0)) <=> ((int) ($b['dia'] ?? 0));
            });

            foreach ($rows as $i => $row) {
                $dia = (int) ($row['dia'] ?? $i);
                $key = self::marcoKeyForDia($dia);
                $titulo = (string) ($row['titulo'] ?? $row['item'] ?? 'Marco');
                $label = PosOperatorioPaciente::formatDiaRelativoLabel($dia).' — '.$titulo;
                $att = $this->attestations->findByMarco($contract, $key);
                $items[] = [
                    'key' => $key,
                    'label' => $label,
                    'dia' => $dia,
                    'fase' => $dia < 0 ? 'pre' : ($dia === 0 ? 'handoff' : 'pos'),
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
                    'label' => 'Marco '.PosOperatorioPaciente::formatDiaRelativoLabel($dia),
                    'dia' => $dia,
                    'fase' => 'pos',
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
            'label' => 'Check-in do dia',
            'dia' => null,
            'fase' => 'pos',
            'attested' => $qAtt !== null,
            'hash' => $qAtt?->getContentHash(),
            'em' => $qAtt?->getCriadoEm()->format('d/m/Y H:i'),
        ];

        return $items;
    }

    public static function marcoKeyForDia(int $dia): string
    {
        return 'check_'.$dia;
    }

    /** Atesta o marco do checklist relativo ao dia, se existir no contrato. */
    public function attestChecklistDia(
        OrganismoCareContract $contract,
        int $dia,
        string $evidencia,
        ?User $ator = null,
        array $payload = [],
    ): ?OrganismoCareAttestation {
        $key = self::marcoKeyForDia($dia);
        $checklist = $contract->getSnapshot()['checklist'] ?? [];
        $has = false;
        if (\is_array($checklist)) {
            foreach ($checklist as $row) {
                if (\is_array($row) && (int) ($row['dia'] ?? 9999) === $dia) {
                    $has = true;
                    break;
                }
            }
        }
        if (!$has) {
            if ($dia === -1 && \is_array($checklist)) {
                foreach ($checklist as $row) {
                    if (!\is_array($row)) {
                        continue;
                    }
                    // alias legado: dia 0 com texto de confirmação
                    if ((int) ($row['dia'] ?? 9999) === 0
                        && str_contains(mb_strtolower((string) ($row['item'] ?? $row['titulo'] ?? '')), 'confirm')) {
                        return $this->attest($contract, 'check_0', $evidencia, $ator, $payload);
                    }
                }
            }

            return null;
        }

        return $this->attest($contract, $key, $evidencia, $ator, $payload);
    }
}
