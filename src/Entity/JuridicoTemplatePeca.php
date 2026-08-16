<?php

namespace App\Entity;

use App\Repository\JuridicoTemplatePecaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoTemplatePecaRepository::class)]
#[ORM\Table(name: 'juridico_template_peca')]
class JuridicoTemplatePeca
{
    public const STATUS_RASCUNHO = 'rascunho';
    public const STATUS_APROVADO = 'aprovado';
    public const STATUS_ARQUIVADO = 'arquivado';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 160)]
    private string $nome;

    #[ORM\Column(length: 80)]
    private string $tipo = 'peticao';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $area = null;

    #[ORM\Column(type: 'text')]
    private string $corpo = '';

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $variaveis = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_RASCUNHO;

    #[ORM\Column]
    private int $versao = 1;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $aprovadoPor = null;

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
    public function getArea(): ?string { return $this->area; }
    public function setArea(?string $area): static { $this->area = $area; return $this; }
    public function getCorpo(): string { return $this->corpo; }
    public function setCorpo(string $corpo): static { $this->corpo = $corpo; return $this; }
    /** @return list<string> */
    public function getVariaveis(): array { return $this->variaveis ?? []; }
    /** @param list<string>|null $variaveis */
    public function setVariaveis(?array $variaveis): static { $this->variaveis = $variaveis; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getVersao(): int { return $this->versao; }
    public function setVersao(int $versao): static { $this->versao = $versao; return $this; }
    public function getAprovadoPor(): ?User { return $this->aprovadoPor; }
    public function setAprovadoPor(?User $aprovadoPor): static { $this->aprovadoPor = $aprovadoPor; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }
    public function isAprovado(): bool { return $this->status === self::STATUS_APROVADO; }
}
