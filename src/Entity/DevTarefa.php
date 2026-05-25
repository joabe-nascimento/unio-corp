<?php

namespace App\Entity;

use App\Repository\DevTarefaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevTarefaRepository::class)]
#[ORM\Table(name: 'dev_tarefa')]
class DevTarefa
{
    public const STATUS_BACKLOG = 'BACKLOG';
    public const STATUS_A_FAZER = 'A_FAZER';
    public const STATUS_EM_ANDAMENTO = 'EM_ANDAMENTO';
    public const STATUS_CONCLUIDO = 'CONCLUIDO';

    public const KANBAN_COLUMNS = [
        self::STATUS_BACKLOG => 'Backlog',
        self::STATUS_A_FAZER => 'A fazer',
        self::STATUS_EM_ANDAMENTO => 'Em andamento',
        self::STATUS_CONCLUIDO => 'Feito',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: DevProjeto::class, inversedBy: 'tarefas')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private DevProjeto $projeto;

    #[ORM\ManyToOne(targetEntity: DevMeta::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DevMeta $meta = null;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_BACKLOG;

    #[ORM\Column(length: 16)]
    private string $prioridade = 'MEDIA';

    #[ORM\Column]
    private int $ordem = 0;

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

    public function getProjeto(): DevProjeto { return $this->projeto; }
    public function setProjeto(DevProjeto $projeto): static { $this->projeto = $projeto; return $this; }

    public function getMeta(): ?DevMeta { return $this->meta; }
    public function setMeta(?DevMeta $meta): static { $this->meta = $meta; return $this; }

    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }

    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): static { $this->descricao = $descricao; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getPrioridade(): string { return $this->prioridade; }
    public function setPrioridade(string $prioridade): static { $this->prioridade = $prioridade; return $this; }

    public function getOrdem(): int { return $this->ordem; }
    public function setOrdem(int $ordem): static { $this->ordem = $ordem; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): void { $this->atualizadoEm = new \DateTimeImmutable(); }

    public function getPrioridadeLabel(): string
    {
        return match ($this->prioridade) {
            'ALTA' => 'Alta',
            'BAIXA' => 'Baixa',
            default => 'Média',
        };
    }
}
