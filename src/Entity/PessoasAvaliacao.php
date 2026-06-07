<?php

namespace App\Entity;

use App\Repository\PessoasAvaliacaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PessoasAvaliacaoRepository::class)]
class PessoasAvaliacao
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 3, scale: 1)]
    private ?string $nota = null;

    #[ORM\Column(length: 32)]
    private ?string $periodo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comentario = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne(targetEntity: Funcionario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Funcionario $funcionario = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $avaliador = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getNota(): ?string { return $this->nota; }
    public function setNota(string $nota): static { $this->nota = $nota; return $this; }
    public function getPeriodo(): ?string { return $this->periodo; }
    public function setPeriodo(string $periodo): static { $this->periodo = $periodo; return $this; }
    public function getComentario(): ?string { return $this->comentario; }
    public function setComentario(?string $comentario): static { $this->comentario = $comentario; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getEmpresa(): ?Empresa { return $this->empresa; }
    public function setEmpresa(?Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getFuncionario(): ?Funcionario { return $this->funcionario; }
    public function setFuncionario(?Funcionario $funcionario): static { $this->funcionario = $funcionario; return $this; }
    public function getAvaliador(): ?User { return $this->avaliador; }
    public function setAvaliador(?User $avaliador): static { $this->avaliador = $avaliador; return $this; }
}
