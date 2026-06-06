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

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $tipoContrato = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $localTrabalho = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $requisitos = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $vagasQuantidade = 1;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publicadaEm = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $recrutador = null;

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

    public function getTipoContrato(): ?string { return $this->tipoContrato; }
    public function setTipoContrato(?string $tipoContrato): static { $this->tipoContrato = $tipoContrato; return $this; }

    public function getLocalTrabalho(): ?string { return $this->localTrabalho; }
    public function setLocalTrabalho(?string $localTrabalho): static { $this->localTrabalho = $localTrabalho; return $this; }

    public function getRequisitos(): ?string { return $this->requisitos; }
    public function setRequisitos(?string $requisitos): static { $this->requisitos = $requisitos; return $this; }

    public function getVagasQuantidade(): int { return $this->vagasQuantidade; }
    public function setVagasQuantidade(int $vagasQuantidade): static { $this->vagasQuantidade = max(1, $vagasQuantidade); return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): static { $this->slug = $slug; return $this; }
    public function getPublicadaEm(): ?\DateTimeImmutable { return $this->publicadaEm; }
    public function setPublicadaEm(?\DateTimeImmutable $publicadaEm): static { $this->publicadaEm = $publicadaEm; return $this; }
    public function getRecrutador(): ?User { return $this->recrutador; }
    public function setRecrutador(?User $recrutador): static { $this->recrutador = $recrutador; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    public function isPublicavel(): bool
    {
        return $this->status === self::STATUS_ABERTA;
    }

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
