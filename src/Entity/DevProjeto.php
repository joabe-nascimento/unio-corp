<?php

namespace App\Entity;

use App\Repository\DevProjetoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevProjetoRepository::class)]
#[ORM\Table(name: 'dev_projeto')]
class DevProjeto
{
    public const STATUS_IDEIA = 'IDEIA';
    public const STATUS_EM_ANDAMENTO = 'EM_ANDAMENTO';
    public const STATUS_PAUSADO = 'PAUSADO';
    public const STATUS_FEITO = 'FEITO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 150)]
    private string $nome;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $codigo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $area = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_IDEIA;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $cor = '#4F7FFF';

    #[ORM\Column(type: 'smallint')]
    private int $progresso = 0;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataAlvo = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column]
    private \DateTimeImmutable $atualizadoEm;

    /** @var Collection<int, DevMeta> */
    #[ORM\OneToMany(targetEntity: DevMeta::class, mappedBy: 'projeto')]
    private Collection $metas;

    /** @var Collection<int, DevTarefa> */
    #[ORM\OneToMany(targetEntity: DevTarefa::class, mappedBy: 'projeto')]
    private Collection $tarefas;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->atualizadoEm = $this->criadoEm;
        $this->metas = new ArrayCollection();
        $this->tarefas = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getNome(): string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }

    public function getCodigo(): ?string { return $this->codigo; }
    public function setCodigo(?string $codigo): static { $this->codigo = $codigo; return $this; }

    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): static { $this->descricao = $descricao; return $this; }

    public function getArea(): ?string { return $this->area; }
    public function setArea(?string $area): static { $this->area = $area; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCor(): ?string { return $this->cor; }
    public function setCor(?string $cor): static { $this->cor = $cor; return $this; }

    public function getProgresso(): int { return $this->progresso; }
    public function setProgresso(int $progresso): static { $this->progresso = max(0, min(100, $progresso)); return $this; }

    public function getDataAlvo(): ?\DateTimeImmutable { return $this->dataAlvo; }
    public function setDataAlvo(?\DateTimeImmutable $dataAlvo): static { $this->dataAlvo = $dataAlvo; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): void { $this->atualizadoEm = new \DateTimeImmutable(); }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_EM_ANDAMENTO => 'Em andamento',
            self::STATUS_PAUSADO => 'Pausado',
            self::STATUS_FEITO => 'Feito',
            default => 'Ideia',
        };
    }

    public function getStatusClass(): string
    {
        return match ($this->status) {
            self::STATUS_EM_ANDAMENTO => 'info',
            self::STATUS_PAUSADO => 'warning',
            self::STATUS_FEITO => 'success',
            default => 'secondary',
        };
    }
}
