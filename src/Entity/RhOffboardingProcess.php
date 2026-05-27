<?php

namespace App\Entity;

use App\Repository\RhOffboardingProcessRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhOffboardingProcessRepository::class)]
#[ORM\Table(name: 'rh_offboarding_process')]
class RhOffboardingProcess
{
    public const STATUS_RASCUNHO = 'RASCUNHO';
    public const STATUS_EM_ANDAMENTO = 'EM_ANDAMENTO';
    public const STATUS_CONCLUIDO = 'CONCLUIDO';
    public const STATUS_CANCELADO = 'CANCELADO';

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

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_RASCUNHO;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataPrevista = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataConclusao = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $motivo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacoes = null;

    /** @var list<array{id: string, label: string, done: bool}> */
    #[ORM\Column(type: 'json')]
    private array $checklist = [];

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->atualizadoEm = $this->criadoEm;
    }

    public static function defaultChecklist(): array
    {
        return [
            ['id' => 'acessos', 'label' => 'Revogar acessos à plataforma', 'done' => false],
            ['id' => 'ti', 'label' => 'Devolução de equipamentos', 'done' => false],
            ['id' => 'docs', 'label' => 'Documentação rescisória', 'done' => false],
            ['id' => 'rh', 'label' => 'Atualizar cadastro e folha', 'done' => false],
        ];
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getFuncionario(): Funcionario { return $this->funcionario; }
    public function setFuncionario(Funcionario $funcionario): static { $this->funcionario = $funcionario; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getDataPrevista(): ?\DateTimeImmutable { return $this->dataPrevista; }
    public function setDataPrevista(?\DateTimeImmutable $dataPrevista): static { $this->dataPrevista = $dataPrevista; return $this; }

    public function getDataConclusao(): ?\DateTimeImmutable { return $this->dataConclusao; }
    public function setDataConclusao(?\DateTimeImmutable $dataConclusao): static { $this->dataConclusao = $dataConclusao; return $this; }

    public function getMotivo(): ?string { return $this->motivo; }
    public function setMotivo(?string $motivo): static { $this->motivo = $motivo; return $this; }

    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $observacoes): static { $this->observacoes = $observacoes; return $this; }

    public function getChecklist(): array { return $this->checklist; }
    public function setChecklist(array $checklist): static { $this->checklist = $checklist; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): void { $this->atualizadoEm = new \DateTimeImmutable(); }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_EM_ANDAMENTO => 'Em andamento',
            self::STATUS_CONCLUIDO => 'Concluído',
            self::STATUS_CANCELADO => 'Cancelado',
            default => 'Rascunho',
        };
    }

    public function getStatusClass(): string
    {
        return match ($this->status) {
            self::STATUS_EM_ANDAMENTO => 'info',
            self::STATUS_CONCLUIDO => 'success',
            self::STATUS_CANCELADO => 'secondary',
            default => 'warning',
        };
    }

    public function checklistDoneCount(): int
    {
        return count(array_filter($this->checklist, static fn (array $i) => !empty($i['done'])));
    }

    public function checklistProgress(): int
    {
        if ($this->checklist === []) {
            return 0;
        }

        return (int) round(($this->checklistDoneCount() / count($this->checklist)) * 100);
    }

    public function isChecklistComplete(): bool
    {
        return $this->checklist !== [] && $this->checklistDoneCount() === \count($this->checklist);
    }
}
