<?php

namespace App\Entity;

use App\Repository\RhVagaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhVagaRepository::class)]
#[ORM\Table(name: 'rh_vaga')]
class RhVaga
{
    public const STATUS_ABERTA = 'ABERTA';
    public const STATUS_PAUSADA = 'PAUSADA';
    public const STATUS_FECHADA = 'FECHADA';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 150)]
    private string $titulo;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $departamento = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_ABERTA;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }

    public function getDepartamento(): ?string { return $this->departamento; }
    public function setDepartamento(?string $departamento): static { $this->departamento = $departamento; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): static { $this->descricao = $descricao; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAUSADA => 'Pausada',
            self::STATUS_FECHADA => 'Fechada',
            default => 'Aberta',
        };
    }

    public function getStatusClass(): string
    {
        return match ($this->status) {
            self::STATUS_PAUSADA => 'warning',
            self::STATUS_FECHADA => 'secondary',
            default => 'success',
        };
    }
}
