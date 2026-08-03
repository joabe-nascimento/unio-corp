<?php

namespace App\Entity;

use App\Repository\JuridicoCobrancaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoCobrancaRepository::class)]
#[ORM\Table(name: 'juridico_cobranca')]
#[ORM\Index(columns: ['empresa_id', 'status'], name: 'IDX_JUR_COBR_EMPRESA_STATUS')]
class JuridicoCobranca
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_PAGO = 'pago';
    public const STATUS_VENCIDO = 'vencido';
    public const STATUS_CANCELADO = 'cancelado';

    public const STATUSES = [
        self::STATUS_PENDENTE,
        self::STATUS_PAGO,
        self::STATUS_VENCIDO,
        self::STATUS_CANCELADO,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: JuridicoCliente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoCliente $cliente = null;

    #[ORM\ManyToOne(targetEntity: JuridicoProcesso::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoProcesso $processo = null;

    #[ORM\ManyToOne(targetEntity: JuridicoHonorarioLancamento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoHonorarioLancamento $lancamento = null;

    #[ORM\Column(length: 200)]
    private string $descricao;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $valor;

    #[ORM\Column]
    private \DateTimeImmutable $vencimento;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_PENDENTE;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $pagoEm = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimaCobrancaEm = null;

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
    public function getCliente(): ?JuridicoCliente { return $this->cliente; }
    public function setCliente(?JuridicoCliente $cliente): static { $this->cliente = $cliente; return $this; }
    public function getProcesso(): ?JuridicoProcesso { return $this->processo; }
    public function setProcesso(?JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function getLancamento(): ?JuridicoHonorarioLancamento { return $this->lancamento; }
    public function setLancamento(?JuridicoHonorarioLancamento $lancamento): static { $this->lancamento = $lancamento; return $this; }
    public function getDescricao(): string { return $this->descricao; }
    public function setDescricao(string $descricao): static { $this->descricao = $descricao; return $this; }
    public function getValor(): string { return $this->valor; }
    public function setValor(string $valor): static { $this->valor = $valor; return $this; }
    public function getValorFloat(): float { return (float) $this->valor; }
    public function getVencimento(): \DateTimeImmutable { return $this->vencimento; }
    public function setVencimento(\DateTimeImmutable $vencimento): static { $this->vencimento = $vencimento; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getPagoEm(): ?\DateTimeImmutable { return $this->pagoEm; }
    public function setPagoEm(?\DateTimeImmutable $pagoEm): static { $this->pagoEm = $pagoEm; return $this; }
    public function getUltimaCobrancaEm(): ?\DateTimeImmutable { return $this->ultimaCobrancaEm; }
    public function setUltimaCobrancaEm(?\DateTimeImmutable $ultimaCobrancaEm): static { $this->ultimaCobrancaEm = $ultimaCobrancaEm; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }

    public function isAberta(): bool
    {
        return \in_array($this->status, [self::STATUS_PENDENTE, self::STATUS_VENCIDO], true);
    }

    public function getDiasAtraso(): int
    {
        if (!$this->isAberta()) {
            return 0;
        }
        $hoje = new \DateTimeImmutable('today');
        if ($this->vencimento >= $hoje) {
            return 0;
        }
        $diff = $hoje->diff($this->vencimento);

        return (int) $diff->days;
    }
}
