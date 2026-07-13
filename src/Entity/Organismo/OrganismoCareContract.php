<?php

namespace App\Entity\Organismo;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\PosOperatorioProtocolo;
use App\Repository\Organismo\OrganismoCareContractRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganismoCareContractRepository::class)]
#[ORM\Table(name: 'organismo_care_contract')]
#[ORM\Index(name: 'IDX_ORG_CONTRACT_EMPRESA', columns: ['empresa_id'])]
#[ORM\Index(name: 'IDX_ORG_CONTRACT_PACIENTE', columns: ['paciente_id', 'ativo'])]
#[ORM\UniqueConstraint(name: 'UNIQ_ORG_CONTRACT_PAC_VER', columns: ['paciente_id', 'versao'])]
class OrganismoCareContract
{
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_CUMPRIDO = 'cumprido';
    public const STATUS_QUEBRADO = 'quebrado';
    public const STATUS_ENCERRADO = 'encerrado';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: PosOperatorioPaciente::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PosOperatorioPaciente $paciente;

    #[ORM\ManyToOne(targetEntity: PosOperatorioProtocolo::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PosOperatorioProtocolo $protocolo = null;

    #[ORM\Column(type: 'smallint')]
    private int $versao = 1;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_ATIVO;

    #[ORM\Column(length: 64)]
    private string $contentHash = '';

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $snapshot = [];

    #[ORM\Column(type: 'boolean')]
    private bool $ativo = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $atualizadoEm;

    /** @var Collection<int, OrganismoCareAttestation> */
    #[ORM\OneToMany(mappedBy: 'contract', targetEntity: OrganismoCareAttestation::class, cascade: ['persist'])]
    #[ORM\OrderBy(['criadoEm' => 'ASC'])]
    private Collection $attestations;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->criadoEm = $now;
        $this->atualizadoEm = $now;
        $this->attestations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getPaciente(): PosOperatorioPaciente
    {
        return $this->paciente;
    }

    public function setPaciente(PosOperatorioPaciente $paciente): static
    {
        $this->paciente = $paciente;

        return $this;
    }

    public function getProtocolo(): ?PosOperatorioProtocolo
    {
        return $this->protocolo;
    }

    public function setProtocolo(?PosOperatorioProtocolo $protocolo): static
    {
        $this->protocolo = $protocolo;

        return $this;
    }

    public function getVersao(): int
    {
        return $this->versao;
    }

    public function setVersao(int $versao): static
    {
        $this->versao = $versao;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->atualizadoEm = new \DateTimeImmutable();

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

    /** @return array<string, mixed> */
    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    /** @param array<string, mixed> $snapshot */
    public function setSnapshot(array $snapshot): static
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function isAtivo(): bool
    {
        return $this->ativo;
    }

    public function setAtivo(bool $ativo): static
    {
        $this->ativo = $ativo;
        $this->atualizadoEm = new \DateTimeImmutable();

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getAtualizadoEm(): \DateTimeImmutable
    {
        return $this->atualizadoEm;
    }

    /** @return Collection<int, OrganismoCareAttestation> */
    public function getAttestations(): Collection
    {
        return $this->attestations;
    }
}
