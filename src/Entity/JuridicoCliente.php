<?php

namespace App\Entity;

use App\Repository\JuridicoClienteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoClienteRepository::class)]
#[ORM\Table(name: 'juridico_cliente')]
#[ORM\Index(columns: ['empresa_id', 'status'], name: 'IDX_JUR_CLIENTE_EMPRESA_STATUS')]
class JuridicoCliente
{
    public const TIPO_PF = 'PF';
    public const TIPO_PJ = 'PJ';

    public const TIPOS = [self::TIPO_PF, self::TIPO_PJ];

    public const STATUS_STANDARD = 'standard';
    public const STATUS_PREMIUM = 'premium';

    public const STATUSES = [self::STATUS_STANDARD, self::STATUS_PREMIUM];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 180)]
    private string $nome;

    #[ORM\Column(length: 2)]
    private string $tipo = self::TIPO_PJ;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $documento = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefone = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $areaAtuacao = null;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_STANDARD;

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
    public function getNome(): string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }
    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }
    public function getDocumento(): ?string { return $this->documento; }
    public function setDocumento(?string $documento): static { $this->documento = $documento; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }
    public function getTelefone(): ?string { return $this->telefone; }
    public function setTelefone(?string $telefone): static { $this->telefone = $telefone; return $this; }
    public function getAreaAtuacao(): ?string { return $this->areaAtuacao; }
    public function setAreaAtuacao(?string $areaAtuacao): static { $this->areaAtuacao = $areaAtuacao; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getObservacoes(): ?string { return $this->observacoes; }
    public function setObservacoes(?string $observacoes): static { $this->observacoes = $observacoes; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }

    public function isPremium(): bool { return $this->status === self::STATUS_PREMIUM; }
}
