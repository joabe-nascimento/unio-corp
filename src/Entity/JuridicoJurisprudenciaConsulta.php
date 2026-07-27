<?php

namespace App\Entity;

use App\Repository\JuridicoJurisprudenciaConsultaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoJurisprudenciaConsultaRepository::class)]
#[ORM\Table(name: 'juridico_jurisprudencia_consulta')]
#[ORM\Index(columns: ['empresa_id', 'criado_em'], name: 'IDX_JUR_CONSULTA_EMPRESA_DATA')]
class JuridicoJurisprudenciaConsulta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 220)]
    private string $tema;

    #[ORM\Column(length: 40)]
    private string $tribunal;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $periodo = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $areaJuridica = null;

    #[ORM\Column]
    private int $resultadosCount = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getTema(): string { return $this->tema; }
    public function setTema(string $tema): static { $this->tema = $tema; return $this; }
    public function getTribunal(): string { return $this->tribunal; }
    public function setTribunal(string $tribunal): static { $this->tribunal = $tribunal; return $this; }
    public function getPeriodo(): ?string { return $this->periodo; }
    public function setPeriodo(?string $periodo): static { $this->periodo = $periodo; return $this; }
    public function getAreaJuridica(): ?string { return $this->areaJuridica; }
    public function setAreaJuridica(?string $areaJuridica): static { $this->areaJuridica = $areaJuridica; return $this; }
    public function getResultadosCount(): int { return $this->resultadosCount; }
    public function setResultadosCount(int $resultadosCount): static { $this->resultadosCount = $resultadosCount; return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): static { $this->createdBy = $createdBy; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
