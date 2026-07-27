<?php

namespace App\Entity;

use App\Repository\JuridicoProcessoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoProcessoRepository::class)]
#[ORM\Table(name: 'juridico_processo')]
#[ORM\Index(columns: ['empresa_id', 'status'], name: 'IDX_JUR_PROCESSO_EMPRESA_STATUS')]
class JuridicoProcesso
{
    public const FASE_CONHECIMENTO = 'conhecimento';
    public const FASE_INSTRUCAO = 'instrucao';
    public const FASE_SENTENCA = 'sentenca';
    public const FASE_RECURSAL = 'recursal';
    public const FASE_EXECUCAO = 'execucao';
    public const FASE_ENCERRADO = 'encerrado';

    public const FASES = [
        self::FASE_CONHECIMENTO,
        self::FASE_INSTRUCAO,
        self::FASE_SENTENCA,
        self::FASE_RECURSAL,
        self::FASE_EXECUCAO,
        self::FASE_ENCERRADO,
    ];

    public const STATUS_ATIVO = 'ativo';
    public const STATUS_CRITICO = 'critico';
    public const STATUS_ENCERRADO = 'encerrado';

    public const STATUSES = [self::STATUS_ATIVO, self::STATUS_CRITICO, self::STATUS_ENCERRADO];

    public const RESULTADO_PROCEDENTE = 'procedente';
    public const RESULTADO_IMPROCEDENTE = 'improcedente';
    public const RESULTADO_ACORDO = 'acordo';

    public const RESULTADOS = [self::RESULTADO_PROCEDENTE, self::RESULTADO_IMPROCEDENTE, self::RESULTADO_ACORDO];

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

    #[ORM\Column(length: 40)]
    private string $numero;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $area = null;

    #[ORM\Column(length: 20)]
    private string $fase = self::FASE_CONHECIMENTO;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $tribunal = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsavel = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $valor = null;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ATIVO;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $resultado = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacoes = null;

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
    public function getNumero(): string { return $this->numero; }
    public function setNumero(string $numero): static { $this->numero = $numero; return $this; }
    public function getArea(): ?string { return $this->area; }
    public function setArea(?string $area): static { $this->area = $area; return $this; }
    public function getFase(): string { return $this->fase; }
    public function setFase(string $fase): static { $this->fase = $fase; return $this; }
    public function getTribunal(): ?string { return $this->tribunal; }
    public function setTribunal(?string $tribunal): static { $this->tribunal = $tribunal; return $this; }
    public function getResponsavel(): ?User { return $this->responsavel; }
    public function setResponsavel(?User $responsavel): static { $this->responsavel = $responsavel; return $this; }
    public function getValor(): ?string { return $this->valor; }
    public function setValor(?string $valor): static { $this->valor = $valor; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getResultado(): ?string { return $this->resultado; }
    public function setResultado(?string $resultado): static { $this->resultado = $resultado; return $this; }
    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $observacoes): static { $this->observacoes = $observacoes; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }
}
