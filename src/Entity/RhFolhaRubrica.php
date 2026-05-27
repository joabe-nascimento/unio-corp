<?php

namespace App\Entity;

use App\Repository\RhFolhaRubricaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhFolhaRubricaRepository::class)]
#[ORM\Table(name: 'rh_folha_rubrica')]
#[ORM\UniqueConstraint(name: 'UNIQ_RH_RUB_EMP_COD', fields: ['empresa', 'codigo'])]
class RhFolhaRubrica
{
    public const TIPO_PROVENTO = 'PROVENTO';
    public const TIPO_DESCONTO = 'DESCONTO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 16)]
    private string $codigo;

    #[ORM\Column(length: 120)]
    private string $descricao;

    #[ORM\Column(length: 16)]
    private string $tipo;

    #[ORM\Column]
    private bool $incideInss = true;

    #[ORM\Column]
    private bool $incideIrrf = true;

    #[ORM\Column]
    private bool $incideFgts = true;

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getCodigo(): string { return $this->codigo; }
    public function setCodigo(string $codigo): static { $this->codigo = $codigo; return $this; }

    public function getDescricao(): string { return $this->descricao; }
    public function setDescricao(string $descricao): static { $this->descricao = $descricao; return $this; }

    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }

    public function isIncideInss(): bool { return $this->incideInss; }
    public function setIncideInss(bool $incideInss): static { $this->incideInss = $incideInss; return $this; }

    public function isIncideIrrf(): bool { return $this->incideIrrf; }
    public function setIncideIrrf(bool $incideIrrf): static { $this->incideIrrf = $incideIrrf; return $this; }

    public function isIncideFgts(): bool { return $this->incideFgts; }
    public function setIncideFgts(bool $incideFgts): static { $this->incideFgts = $incideFgts; return $this; }
}
