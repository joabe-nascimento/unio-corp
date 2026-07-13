<?php

namespace App\Entity\Organismo;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Repository\Organismo\OrganismoReflexLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganismoReflexLogRepository::class)]
#[ORM\Table(name: 'organismo_reflex_log')]
#[ORM\Index(name: 'IDX_ORG_REFLEX_EMPRESA_CRIADO', columns: ['empresa_id', 'criado_em'])]
#[ORM\Index(name: 'IDX_ORG_REFLEX_CODE', columns: ['empresa_id', 'reflex_code'])]
class OrganismoReflexLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: PosOperatorioPaciente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PosOperatorioPaciente $paciente = null;

    #[ORM\Column(length: 64)]
    private string $reflexCode = '';

    #[ORM\Column(length: 255)]
    private string $motivo = '';

    #[ORM\Column(length: 64)]
    private string $acao = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alvo = null;

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

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getPaciente(): ?PosOperatorioPaciente
    {
        return $this->paciente;
    }

    public function setPaciente(?PosOperatorioPaciente $paciente): static
    {
        $this->paciente = $paciente;

        return $this;
    }

    public function getReflexCode(): string
    {
        return $this->reflexCode;
    }

    public function setReflexCode(string $reflexCode): static
    {
        $this->reflexCode = $reflexCode;

        return $this;
    }

    public function getMotivo(): string
    {
        return $this->motivo;
    }

    public function setMotivo(string $motivo): static
    {
        $this->motivo = $motivo;

        return $this;
    }

    public function getAcao(): string
    {
        return $this->acao;
    }

    public function setAcao(string $acao): static
    {
        $this->acao = $acao;

        return $this;
    }

    public function getAlvo(): ?string
    {
        return $this->alvo;
    }

    public function setAlvo(?string $alvo): static
    {
        $this->alvo = $alvo;

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
