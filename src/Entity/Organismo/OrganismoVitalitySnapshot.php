<?php

namespace App\Entity\Organismo;

use App\Entity\Empresa;
use App\Repository\Organismo\OrganismoVitalitySnapshotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganismoVitalitySnapshotRepository::class)]
#[ORM\Table(name: 'organismo_vitality_snapshot')]
#[ORM\Index(name: 'IDX_ORG_VITAL_EMPRESA_CRIADO', columns: ['empresa_id', 'criado_em'])]
class OrganismoVitalitySnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(type: 'smallint')]
    private int $score = 100;

    #[ORM\Column(length: 24)]
    private string $nivel = 'saudavel';

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $breakdown = [];

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $tendencia = null;

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

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = max(0, min(100, $score));

        return $this;
    }

    public function getNivel(): string
    {
        return $this->nivel;
    }

    public function setNivel(string $nivel): static
    {
        $this->nivel = $nivel;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getBreakdown(): array
    {
        return $this->breakdown;
    }

    /** @param array<string, mixed> $breakdown */
    public function setBreakdown(array $breakdown): static
    {
        $this->breakdown = $breakdown;

        return $this;
    }

    public function getTendencia(): ?int
    {
        return $this->tendencia;
    }

    public function setTendencia(?int $tendencia): static
    {
        $this->tendencia = $tendencia;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
