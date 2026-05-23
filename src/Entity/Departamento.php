<?php

namespace App\Entity;

use App\Repository\DepartamentoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DepartamentoRepository::class)]
class Departamento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nome = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $codigo = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class, inversedBy: 'departamentos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Empresa $empresa = null;

    #[ORM\OneToMany(targetEntity: Funcionario::class, mappedBy: 'departamento')]
    private Collection $funcionarios;

    public function __construct()
    {
        $this->funcionarios = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNome(): ?string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }
    public function getCodigo(): ?string { return $this->codigo; }
    public function setCodigo(?string $codigo): static { $this->codigo = $codigo; return $this; }
    public function getEmpresa(): ?Empresa { return $this->empresa; }
    public function setEmpresa(?Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getFuncionarios(): Collection { return $this->funcionarios; }
}
