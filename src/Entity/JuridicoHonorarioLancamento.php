<?php

namespace App\Entity;

use App\Repository\JuridicoHonorarioLancamentoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoHonorarioLancamentoRepository::class)]
#[ORM\Table(name: 'juridico_honorario_lancamento')]
#[ORM\Index(columns: ['empresa_id', 'data'], name: 'IDX_JUR_HONOR_EMPRESA_DATA')]
class JuridicoHonorarioLancamento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $advogado = null;

    #[ORM\ManyToOne(targetEntity: JuridicoProcesso::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoProcesso $processo = null;

    #[ORM\Column]
    private \DateTimeImmutable $data;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $horas;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $valorHora = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column]
    private bool $faturavel = true;

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
    public function getAdvogado(): ?User { return $this->advogado; }
    public function setAdvogado(?User $advogado): static { $this->advogado = $advogado; return $this; }
    public function getProcesso(): ?JuridicoProcesso { return $this->processo; }
    public function setProcesso(?JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function getData(): \DateTimeImmutable { return $this->data; }
    public function setData(\DateTimeImmutable $data): static { $this->data = $data; return $this; }
    public function getHoras(): string { return $this->horas; }
    public function setHoras(string $horas): static { $this->horas = $horas; return $this; }
    public function getValorHora(): ?string { return $this->valorHora; }
    public function setValorHora(?string $valorHora): static { $this->valorHora = $valorHora; return $this; }
    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): static { $this->descricao = $descricao; return $this; }
    public function isFaturavel(): bool { return $this->faturavel; }
    public function setFaturavel(bool $faturavel): static { $this->faturavel = $faturavel; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }

    public function getValorTotal(): float
    {
        return (float) $this->horas * (float) ($this->valorHora ?? '0');
    }
}
