<?php

namespace App\Entity\Organismo;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Repository\Organismo\OrganismoMemoryFactRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganismoMemoryFactRepository::class)]
#[ORM\Table(name: 'organismo_memory_fact')]
#[ORM\Index(name: 'IDX_ORG_MEMORY_EMPRESA_TIPO', columns: ['empresa_id', 'tipo', 'criado_em'])]
#[ORM\Index(name: 'IDX_ORG_MEMORY_PACIENTE', columns: ['paciente_id', 'criado_em'])]
class OrganismoMemoryFact
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
    private string $tipo = '';

    #[ORM\Column(length: 160)]
    private string $sujeito = '';

    #[ORM\Column(type: 'smallint')]
    private int $peso = 1;

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

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getSujeito(): string
    {
        return $this->sujeito;
    }

    public function setSujeito(string $sujeito): static
    {
        $this->sujeito = $sujeito;

        return $this;
    }

    public function getPeso(): int
    {
        return $this->peso;
    }

    public function setPeso(int $peso): static
    {
        $this->peso = max(1, min(100, $peso));

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
