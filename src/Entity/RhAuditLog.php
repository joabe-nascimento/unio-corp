<?php

namespace App\Entity;

use App\Repository\RhAuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhAuditLogRepository::class)]
#[ORM\Table(name: 'rh_audit_log')]
class RhAuditLog
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
    private ?User $user = null;

    #[ORM\Column(length: 48)]
    private string $modulo;

    #[ORM\Column(length: 64)]
    private string $acao;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $entidade = null;

    #[ORM\Column(nullable: true)]
    private ?int $entidadeId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getModulo(): string { return $this->modulo; }
    public function setModulo(string $modulo): static { $this->modulo = $modulo; return $this; }

    public function getAcao(): string { return $this->acao; }
    public function setAcao(string $acao): static { $this->acao = $acao; return $this; }

    public function getEntidade(): ?string { return $this->entidade; }
    public function setEntidade(?string $entidade): static { $this->entidade = $entidade; return $this; }

    public function getEntidadeId(): ?int { return $this->entidadeId; }
    public function setEntidadeId(?int $entidadeId): static { $this->entidadeId = $entidadeId; return $this; }

    public function getPayload(): ?array { return $this->payload; }
    public function setPayload(?array $payload): static { $this->payload = $payload; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
