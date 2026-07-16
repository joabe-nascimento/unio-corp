<?php

namespace App\Entity;

use App\Repository\ClinicProfissionalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicProfissionalRepository::class)]
#[ORM\Table(name: 'clinic_profissional')]
#[ORM\UniqueConstraint(name: 'UNIQ_CLINIC_PROFISSIONAL_CONSELHO', columns: ['empresa_id', 'conselho', 'numero_conselho'])]
#[ORM\Index(columns: ['empresa_id'], name: 'IDX_CLINIC_PROFISSIONAL_EMPRESA')]
class ClinicProfissional
{
    public const CONSELHO_CRM = 'CRM';
    public const CONSELHO_CRO = 'CRO';
    public const CONSELHO_COREN = 'COREN';
    public const CONSELHO_CREFITO = 'CREFITO';
    public const CONSELHO_OUTRO = 'OUTRO';

    public const CONSELHOS = [
        self::CONSELHO_CRM,
        self::CONSELHO_CRO,
        self::CONSELHO_COREN,
        self::CONSELHO_CREFITO,
        self::CONSELHO_OUTRO,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 160)]
    private string $nome = '';

    #[ORM\Column(length: 16)]
    private string $conselho = self::CONSELHO_CRM;

    #[ORM\Column(length: 32)]
    private string $numeroConselho = '';

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $ufConselho = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $especialidade = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telefone = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $ativo = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->criadoEm = $now;
        $this->atualizadoEm = $now;
    }

    public function touch(): void
    {
        $this->atualizadoEm = new \DateTimeImmutable();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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

    public function getConselho(): string
    {
        return $this->conselho;
    }

    public function setConselho(string $conselho): static
    {
        $this->conselho = $conselho;

        return $this;
    }

    public function getNumeroConselho(): string
    {
        return $this->numeroConselho;
    }

    public function setNumeroConselho(string $numeroConselho): static
    {
        $this->numeroConselho = $numeroConselho;

        return $this;
    }

    public function getUfConselho(): ?string
    {
        return $this->ufConselho;
    }

    public function setUfConselho(?string $ufConselho): static
    {
        $this->ufConselho = $ufConselho;

        return $this;
    }

    public function getEspecialidade(): ?string
    {
        return $this->especialidade;
    }

    public function setEspecialidade(?string $especialidade): static
    {
        $this->especialidade = $especialidade;

        return $this;
    }

    public function getTelefone(): ?string
    {
        return $this->telefone;
    }

    public function setTelefone(?string $telefone): static
    {
        $this->telefone = $telefone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

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

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getAtualizadoEm(): \DateTimeImmutable
    {
        return $this->atualizadoEm;
    }
}
