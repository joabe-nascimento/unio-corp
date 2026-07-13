<?php

namespace App\Entity\Organismo;

use App\Entity\User;
use App\Repository\Organismo\OrganismoCareAttestationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganismoCareAttestationRepository::class)]
#[ORM\Table(name: 'organismo_care_attestation')]
#[ORM\Index(name: 'IDX_ORG_ATTEST_CONTRACT', columns: ['contract_id', 'criado_em'])]
#[ORM\UniqueConstraint(name: 'UNIQ_ORG_ATTEST_MARCO', columns: ['contract_id', 'marco_key'])]
class OrganismoCareAttestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: OrganismoCareContract::class, inversedBy: 'attestations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OrganismoCareContract $contract;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $ator = null;

    #[ORM\Column(length: 64)]
    private string $marcoKey = '';

    #[ORM\Column(length: 255)]
    private string $evidencia = '';

    #[ORM\Column(length: 64)]
    private string $contentHash = '';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $prevHash = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContract(): OrganismoCareContract
    {
        return $this->contract;
    }

    public function setContract(OrganismoCareContract $contract): static
    {
        $this->contract = $contract;

        return $this;
    }

    public function getAtor(): ?User
    {
        return $this->ator;
    }

    public function setAtor(?User $ator): static
    {
        $this->ator = $ator;

        return $this;
    }

    public function getMarcoKey(): string
    {
        return $this->marcoKey;
    }

    public function setMarcoKey(string $marcoKey): static
    {
        $this->marcoKey = $marcoKey;

        return $this;
    }

    public function getEvidencia(): string
    {
        return $this->evidencia;
    }

    public function setEvidencia(string $evidencia): static
    {
        $this->evidencia = $evidencia;

        return $this;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function setContentHash(string $contentHash): static
    {
        $this->contentHash = $contentHash;

        return $this;
    }

    public function getPrevHash(): ?string
    {
        return $this->prevHash;
    }

    public function setPrevHash(?string $prevHash): static
    {
        $this->prevHash = $prevHash;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $payload */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
