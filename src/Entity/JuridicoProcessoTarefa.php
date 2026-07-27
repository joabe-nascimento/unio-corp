<?php

namespace App\Entity;

use App\Repository\JuridicoProcessoTarefaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoProcessoTarefaRepository::class)]
#[ORM\Table(name: 'juridico_processo_tarefa')]
#[ORM\Index(columns: ['processo_id', 'status'], name: 'IDX_JUR_TAREFA_PROCESSO_STATUS')]
class JuridicoProcessoTarefa
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_CONCLUIDA = 'concluida';

    public const STATUSES = [self::STATUS_PENDENTE, self::STATUS_CONCLUIDA];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JuridicoProcesso::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JuridicoProcesso $processo;

    #[ORM\Column(length: 160)]
    private string $titulo;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $prazo = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsavel = null;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_PENDENTE;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $concluidoEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProcesso(): JuridicoProcesso { return $this->processo; }
    public function setProcesso(JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }
    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): static { $this->descricao = $descricao; return $this; }
    public function getPrazo(): ?\DateTimeImmutable { return $this->prazo; }
    public function setPrazo(?\DateTimeImmutable $prazo): static { $this->prazo = $prazo; return $this; }
    public function getResponsavel(): ?User { return $this->responsavel; }
    public function setResponsavel(?User $responsavel): static { $this->responsavel = $responsavel; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getConcluidoEm(): ?\DateTimeImmutable { return $this->concluidoEm; }

    public function isConcluida(): bool
    {
        return $this->status === self::STATUS_CONCLUIDA;
    }

    public function isAtrasada(): bool
    {
        return !$this->isConcluida() && $this->prazo !== null && $this->prazo < new \DateTimeImmutable();
    }

    public function marcarConcluida(): static
    {
        $this->status = self::STATUS_CONCLUIDA;
        $this->concluidoEm = new \DateTimeImmutable();

        return $this;
    }

    public function reabrir(): static
    {
        $this->status = self::STATUS_PENDENTE;
        $this->concluidoEm = null;

        return $this;
    }
}
