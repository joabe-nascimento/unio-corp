<?php

namespace App\Entity;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
#[ORM\Table(name: 'api_token')]
#[ORM\Index(columns: ['empresa_id'], name: 'IDX_API_TOKEN_EMPRESA')]
class ApiToken
{
    public const SCOPE_LEITURA = 'leitura';
    public const SCOPE_ESCRITA = 'escrita';

    public const SCOPES = [self::SCOPE_LEITURA, self::SCOPE_ESCRITA];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 120)]
    private string $nome;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(length: 16)]
    private string $tokenPrefix;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $scopes = [self::SCOPE_LEITURA];

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $criadoPor = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimoUsoEm = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revogadoEm = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalRequisicoes = 0;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getNome(): string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }
    public function getTokenHash(): string { return $this->tokenHash; }
    public function setTokenHash(string $tokenHash): static { $this->tokenHash = $tokenHash; return $this; }
    public function getTokenPrefix(): string { return $this->tokenPrefix; }
    public function setTokenPrefix(string $tokenPrefix): static { $this->tokenPrefix = $tokenPrefix; return $this; }

    /** @return list<string> */
    public function getScopes(): array { return $this->scopes; }

    /** @param list<string> $scopes */
    public function setScopes(array $scopes): static { $this->scopes = $scopes; return $this; }

    public function hasScope(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }

    public function isAtivo(): bool { return $this->ativo && $this->revogadoEm === null; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }
    public function getCriadoPor(): ?User { return $this->criadoPor; }
    public function setCriadoPor(?User $criadoPor): static { $this->criadoPor = $criadoPor; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getUltimoUsoEm(): ?\DateTimeImmutable { return $this->ultimoUsoEm; }
    public function registrarUso(): static
    {
        $this->ultimoUsoEm = new \DateTimeImmutable();
        ++$this->totalRequisicoes;

        return $this;
    }
    public function getRevogadoEm(): ?\DateTimeImmutable { return $this->revogadoEm; }
    public function revogar(): static { $this->revogadoEm = new \DateTimeImmutable(); $this->ativo = false; return $this; }
    public function getTotalRequisicoes(): int { return $this->totalRequisicoes; }
}
