<?php

namespace App\Entity;

use App\Repository\IntegApiKeyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegApiKeyRepository::class)]
#[ORM\Table(name: 'integ_api_key')]
class IntegApiKey
{
    public const AMB_DEV = 'dev';
    public const AMB_PROD = 'prod';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 80)]
    private string $nome;

    #[ORM\Column(length: 16)]
    private string $prefix;

    #[ORM\Column(length: 64)]
    private string $hash;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $scopes = [];

    #[ORM\Column(length: 8)]
    private string $ambiente = self::AMB_PROD;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimoUso = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revogadaEm = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'db_id' => $this->id,
            'nome' => $this->nome,
            'prefix' => $this->prefix,
            'masked_key' => $this->prefix . '••••••••••••',
            'scopes' => $this->scopes,
            'ambiente' => $this->ambiente,
            'ultimo_uso' => $this->ultimoUso?->format('d/m/Y H:i') ?? '—',
            'revogada' => $this->revogadaEm !== null,
            'revogada_em' => $this->revogadaEm?->format('d/m/Y H:i'),
            'criado_em' => $this->criadoEm->format('d/m/Y'),
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

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = $nome;

        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): static
    {
        $this->hash = $hash;

        return $this;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /** @param list<string> $scopes */
    public function setScopes(array $scopes): static
    {
        $this->scopes = array_values($scopes);

        return $this;
    }

    public function getAmbiente(): string
    {
        return $this->ambiente;
    }

    public function setAmbiente(string $ambiente): static
    {
        $this->ambiente = $ambiente;

        return $this;
    }

    public function getUltimoUso(): ?\DateTimeImmutable
    {
        return $this->ultimoUso;
    }

    public function setUltimoUso(?\DateTimeImmutable $ultimoUso): static
    {
        $this->ultimoUso = $ultimoUso;

        return $this;
    }

    public function getRevogadaEm(): ?\DateTimeImmutable
    {
        return $this->revogadaEm;
    }

    public function setRevogadaEm(?\DateTimeImmutable $revogadaEm): static
    {
        $this->revogadaEm = $revogadaEm;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
