<?php

namespace App\Entity;

use App\Repository\ClinicApiTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicApiTokenRepository::class)]
#[ORM\Table(name: 'clinic_api_token')]
class ClinicApiToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 80)]
    private string $nome = '';

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $escopos = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $ativo = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ultimoUsoEm = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
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

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    /** @return list<string> */
    public function getEscopos(): array
    {
        return $this->escopos;
    }

    /** @param list<string> $escopos */
    public function setEscopos(array $escopos): static
    {
        $this->escopos = $escopos;

        return $this;
    }

    public function isAtivo(): bool
    {
        return $this->ativo;
    }

    public function setAtivo(bool $ativo): static
    {
        $this->ativo = $ativo;

        return $this;
    }

    public function getUltimoUsoEm(): ?\DateTimeImmutable
    {
        return $this->ultimoUsoEm;
    }

    public function setUltimoUsoEm(?\DateTimeImmutable $ultimoUsoEm): static
    {
        $this->ultimoUsoEm = $ultimoUsoEm;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
