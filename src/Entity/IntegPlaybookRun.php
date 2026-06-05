<?php

namespace App\Entity;

use App\Repository\IntegPlaybookRunRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegPlaybookRunRepository::class)]
#[ORM\Table(name: 'integ_playbook_run')]
class IntegPlaybookRun
{
    public const STATUS_EM_PROGRESSO = 'em_progresso';
    public const STATUS_CONCLUIDO = 'concluido';
    public const STATUS_CANCELADO = 'cancelado';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $iniciadoPor = null;

    #[ORM\Column(length: 64)]
    private string $playbookId;

    #[ORM\Column(length: 180)]
    private string $titulo;

    /** @var list<array{index: int, titulo: string, descricao: string, feito: bool, evidencia: string|null, feito_em: string|null}> */
    #[ORM\Column(type: 'json')]
    private array $steps = [];

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_EM_PROGRESSO;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $concluidoEm = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function progressPct(): int
    {
        if (empty($this->steps)) {
            return 0;
        }
        $done = count(array_filter($this->steps, fn ($s) => $s['feito'] ?? false));

        return (int) round($done / count($this->steps) * 100);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'playbook_id' => $this->playbookId,
            'titulo' => $this->titulo,
            'steps' => $this->steps,
            'status' => $this->status,
            'progress_pct' => $this->progressPct(),
            'steps_done' => count(array_filter($this->steps, fn ($s) => $s['feito'] ?? false)),
            'steps_total' => count($this->steps),
            'iniciado_por' => $this->iniciadoPor?->getNome() ?: '—',
            'criado_em' => $this->criadoEm->format('d/m/Y H:i'),
            'concluido_em' => $this->concluidoEm?->format('d/m/Y H:i'),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getIniciadoPor(): ?User
    {
        return $this->iniciadoPor;
    }

    public function setIniciadoPor(?User $user): static
    {
        $this->iniciadoPor = $user;

        return $this;
    }

    public function getPlaybookId(): string
    {
        return $this->playbookId;
    }

    public function setPlaybookId(string $playbookId): static
    {
        $this->playbookId = $playbookId;

        return $this;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): static
    {
        $this->titulo = $titulo;

        return $this;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function setSteps(array $steps): static
    {
        $this->steps = $steps;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getConcluidoEm(): ?\DateTimeImmutable
    {
        return $this->concluidoEm;
    }

    public function setConcluidoEm(?\DateTimeImmutable $concluidoEm): static
    {
        $this->concluidoEm = $concluidoEm;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
