<?php

namespace App\Entity;

use App\Repository\RhFeriasRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhFeriasRepository::class)]
#[ORM\Table(name: 'rh_ferias')]
class RhFerias
{
    public const STATUS_SOLICITADA = 'SOLICITADA';
    public const STATUS_APROVADA = 'APROVADA';
    public const STATUS_REJEITADA = 'REJEITADA';
    public const STATUS_EM_GOZO = 'EM_GOZO';
    public const STATUS_CONCLUIDA = 'CONCLUIDA';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: Funcionario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Funcionario $funcionario;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'solicitante_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $solicitante = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'aprovador_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $aprovador = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_SOLICITADA;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dataInicio;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dataFim;

    #[ORM\Column]
    private int $dias = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacoes = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motivoRejeicao = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column]
    private \DateTimeImmutable $atualizadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $aprovadoEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->atualizadoEm = $this->criadoEm;
    }

    public function touch(): void
    {
        $this->atualizadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getFuncionario(): Funcionario { return $this->funcionario; }
    public function setFuncionario(Funcionario $funcionario): static { $this->funcionario = $funcionario; return $this; }

    public function getSolicitante(): ?User { return $this->solicitante; }
    public function setSolicitante(?User $solicitante): static { $this->solicitante = $solicitante; return $this; }

    public function getAprovador(): ?User { return $this->aprovador; }
    public function setAprovador(?User $aprovador): static { $this->aprovador = $aprovador; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getDataInicio(): \DateTimeImmutable { return $this->dataInicio; }
    public function setDataInicio(\DateTimeImmutable $dataInicio): static { $this->dataInicio = $dataInicio; return $this; }

    public function getDataFim(): \DateTimeImmutable { return $this->dataFim; }
    public function setDataFim(\DateTimeImmutable $dataFim): static { $this->dataFim = $dataFim; return $this; }

    public function getDias(): int { return $this->dias; }
    public function setDias(int $dias): static { $this->dias = $dias; return $this; }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $observacoes): static { $this->observacoes = $observacoes; return $this; }

    public function getMotivoRejeicao(): ?string { return $this->motivoRejeicao; }
    public function setMotivoRejeicao(?string $motivoRejeicao): static { $this->motivoRejeicao = $motivoRejeicao; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }
    public function getAprovadoEm(): ?\DateTimeImmutable { return $this->aprovadoEm; }
    public function setAprovadoEm(?\DateTimeImmutable $aprovadoEm): static { $this->aprovadoEm = $aprovadoEm; return $this; }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APROVADA => 'Aprovada',
            self::STATUS_REJEITADA => 'Rejeitada',
            self::STATUS_EM_GOZO => 'Em gozo',
            self::STATUS_CONCLUIDA => 'Concluída',
            default => 'Solicitada',
        };
    }

    public function getStatusClass(): string
    {
        return match ($this->status) {
            self::STATUS_APROVADA, self::STATUS_CONCLUIDA => 'success',
            self::STATUS_REJEITADA => 'danger',
            self::STATUS_EM_GOZO => 'warning',
            default => 'info',
        };
    }
}
