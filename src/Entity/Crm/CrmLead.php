<?php

namespace App\Entity\Crm;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\Crm\CrmLeadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrmLeadRepository::class)]
#[ORM\Table(name: 'crm_lead')]
#[ORM\Index(columns: ['empresa_id', 'status'], name: 'idx_crm_lead_empresa_status')]
class CrmLead
{
    public const STATUS_NOVO = 'novo';
    public const STATUS_QUALIFICANDO = 'qualificando';
    public const STATUS_QUALIFICADO = 'qualificado';
    public const STATUS_DESCARTADO = 'descartado';
    public const STATUS_CONVERTIDO = 'convertido';

    public const ORIGEM_MANUAL = 'manual';
    public const ORIGEM_SITE = 'site';
    public const ORIGEM_INDICACAO = 'indicacao';
    public const ORIGEM_EVENTO = 'evento';
    public const ORIGEM_OUTRO = 'outro';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 160)]
    private string $nome;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telefone = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $empresaNome = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $cargo = null;

    #[ORM\Column(length: 32)]
    private string $origem = self::ORIGEM_MANUAL;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_NOVO;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notas = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsavel = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->criadoEm = $now;
        $this->atualizadoEm = $now;
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getNome(): string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email ? mb_strtolower(trim($email)) : null; return $this; }

    public function getTelefone(): ?string { return $this->telefone; }
    public function setTelefone(?string $telefone): static { $this->telefone = $telefone; return $this; }

    public function getEmpresaNome(): ?string { return $this->empresaNome; }
    public function setEmpresaNome(?string $empresaNome): static { $this->empresaNome = $empresaNome; return $this; }

    public function getCargo(): ?string { return $this->cargo; }
    public function setCargo(?string $cargo): static { $this->cargo = $cargo; return $this; }

    public function getOrigem(): string { return $this->origem; }
    public function setOrigem(string $origem): static { $this->origem = $origem; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; $this->touch(); return $this; }

    public function getNotas(): ?string { return $this->notas; }
    public function setNotas(?string $notas): static { $this->notas = $notas; return $this; }

    public function getResponsavel(): ?User { return $this->responsavel; }
    public function setResponsavel(?User $responsavel): static { $this->responsavel = $responsavel; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }

    public function touch(): void { $this->atualizadoEm = new \DateTimeImmutable(); }

    /** @return list<string> */
    public static function statusList(): array
    {
        return [self::STATUS_NOVO, self::STATUS_QUALIFICANDO, self::STATUS_QUALIFICADO, self::STATUS_DESCARTADO, self::STATUS_CONVERTIDO];
    }

    /** @return list<string> */
    public static function origemList(): array
    {
        return [self::ORIGEM_MANUAL, self::ORIGEM_SITE, self::ORIGEM_INDICACAO, self::ORIGEM_EVENTO, self::ORIGEM_OUTRO];
    }
}
