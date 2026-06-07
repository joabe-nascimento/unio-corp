<?php

namespace App\Entity;

use App\Repository\PessoasCargoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PessoasCargoRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PESSOAS_CARGO_EMPRESA_TITULO', fields: ['empresa', 'titulo'])]
class PessoasCargo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private ?string $titulo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $area = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $nivel = null;

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Empresa $empresa = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitulo(): ?string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }
    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): static { $this->descricao = $descricao; return $this; }
    public function getArea(): ?string { return $this->area; }
    public function setArea(?string $area): static { $this->area = $area; return $this; }
    public function getNivel(): ?string { return $this->nivel; }
    public function setNivel(?string $nivel): static { $this->nivel = $nivel; return $this; }
    public function isAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getEmpresa(): ?Empresa { return $this->empresa; }
    public function setEmpresa(?Empresa $empresa): static { $this->empresa = $empresa; return $this; }
}
