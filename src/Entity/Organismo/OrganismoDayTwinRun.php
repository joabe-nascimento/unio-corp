<?php

namespace App\Entity\Organismo;

use App\Entity\Empresa;
use App\Repository\Organismo\OrganismoDayTwinRunRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganismoDayTwinRunRepository::class)]
#[ORM\Table(name: 'organismo_day_twin_run')]
#[ORM\UniqueConstraint(name: 'UNIQ_ORG_TWIN_EMPRESA_DIA', columns: ['empresa_id', 'dia'])]
class OrganismoDayTwinRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dia;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $scenarios = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->dia = new \DateTimeImmutable('today');
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

    public function getDia(): \DateTimeImmutable
    {
        return $this->dia;
    }

    public function setDia(\DateTimeImmutable $dia): static
    {
        $this->dia = $dia;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getScenarios(): array
    {
        return $this->scenarios;
    }

    /** @param list<array<string, mixed>> $scenarios */
    public function setScenarios(array $scenarios): static
    {
        $this->scenarios = $scenarios;
        $this->criadoEm = new \DateTimeImmutable();

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
