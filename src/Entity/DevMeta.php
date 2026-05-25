<?php

namespace App\Entity;

use App\Repository\DevMetaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevMetaRepository::class)]
#[ORM\Table(name: 'dev_meta')]
class DevMeta
{
    public const STATUS_PENDENTE = 'PENDENTE';
    public const STATUS_EM_ANDAMENTO = 'EM_ANDAMENTO';
    public const STATUS_ATINGIDA = 'ATINGIDA';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: DevProjeto::class, inversedBy: 'metas')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DevProjeto $projeto = null;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_PENDENTE;

    #[ORM\Column(length: 16)]
    private string $prioridade = 'MEDIA';

    #[ORM\Column(type: 'smallint')]
    private int $progressoPercent = 0;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataAlvo = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->atualizadoEm = $this->criadoEm;
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getProjeto(): ?DevProjeto { return $this->projeto; }
    public function setProjeto(?DevProjeto $projeto): static { $this->projeto = $projeto; return $this; }

    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }

    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): static { $this->descricao = $descricao; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getPrioridade(): string { return $this->prioridade; }
    public function setPrioridade(string $prioridade): static { $this->prioridade = $prioridade; return $this; }

    public function getProgressoPercent(): int { return $this->progressoPercent; }
    public function setProgressoPercent(int $v): static { $this->progressoPercent = max(0, min(100, $v)); return $this; }

    public function getDataAlvo(): ?\DateTimeImmutable { return $this->dataAlvo; }
    public function setDataAlvo(?\DateTimeImmutable $d): static { $this->dataAlvo = $d; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): void { $this->atualizadoEm = new \DateTimeImmutable(); }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_EM_ANDAMENTO => 'Em andamento',
            self::STATUS_ATINGIDA => 'Atingida',
            default => 'Pendente',
        };
    }

    public function getStatusClass(): string
    {
        return match ($this->status) {
            self::STATUS_EM_ANDAMENTO => 'info',
            self::STATUS_ATINGIDA => 'success',
            default => 'secondary',
        };
    }

    public function getPrioridadeLabel(): string
    {
        return match ($this->prioridade) {
            'ALTA' => 'Alta',
            'BAIXA' => 'Baixa',
            default => 'Média',
        };
    }
}
