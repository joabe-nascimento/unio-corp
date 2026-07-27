<?php

namespace App\Entity;

use App\Repository\JuridicoPrazoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoPrazoRepository::class)]
#[ORM\Table(name: 'juridico_prazo')]
#[ORM\Index(columns: ['empresa_id', 'cumprido'], name: 'IDX_JUR_PRAZO_EMPRESA_CUMPRIDO')]
class JuridicoPrazo
{
    /** Limiar (em dias) para considerar o prazo crítico. */
    public const LIMIAR_CRITICO_DIAS = 3;
    /** Limiar (em dias) para considerar o prazo em alerta. */
    public const LIMIAR_ALERTA_DIAS = 15;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: JuridicoProcesso::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoProcesso $processo = null;

    #[ORM\Column(length: 80)]
    private string $tipo;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column]
    private \DateTimeImmutable $dataLimite;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsavel = null;

    #[ORM\Column]
    private bool $cumprido = false;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $atualizadoEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getProcesso(): ?JuridicoProcesso { return $this->processo; }
    public function setProcesso(?JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }
    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): static { $this->descricao = $descricao; return $this; }
    public function getDataLimite(): \DateTimeImmutable { return $this->dataLimite; }
    public function setDataLimite(\DateTimeImmutable $dataLimite): static { $this->dataLimite = $dataLimite; return $this; }
    public function getResponsavel(): ?User { return $this->responsavel; }
    public function setResponsavel(?User $responsavel): static { $this->responsavel = $responsavel; return $this; }
    public function isCumprido(): bool { return $this->cumprido; }
    public function setCumprido(bool $cumprido): static { $this->cumprido = $cumprido; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }

    public function getDiasRestantes(): int
    {
        $hoje = new \DateTimeImmutable('today');
        $diff = $hoje->diff($this->dataLimite);
        $dias = (int) $diff->days;

        return $diff->invert === 1 ? -$dias : $dias;
    }

    /** @return 'concluido'|'critico'|'alerta'|'ok' */
    public function getStatusLabel(): string
    {
        if ($this->cumprido) {
            return 'concluido';
        }
        $dias = $this->getDiasRestantes();
        if ($dias <= self::LIMIAR_CRITICO_DIAS) {
            return 'critico';
        }
        if ($dias <= self::LIMIAR_ALERTA_DIAS) {
            return 'alerta';
        }

        return 'ok';
    }
}
