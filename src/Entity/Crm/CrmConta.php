<?php

namespace App\Entity\Crm;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\Crm\CrmContaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrmContaRepository::class)]
#[ORM\Table(name: 'crm_conta')]
#[ORM\Index(columns: ['empresa_id', 'status'], name: 'idx_crm_conta_empresa_status')]
class CrmConta
{
    public const STATUS_PROSPECT = 'prospect';
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_INATIVO = 'inativo';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 180)]
    private string $nome;

    #[ORM\Column(length: 18, nullable: true)]
    private ?string $documento = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telefone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $site = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $segmento = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_PROSPECT;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notas = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

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
    public function getDocumento(): ?string { return $this->documento; }
    public function setDocumento(?string $documento): static { $this->documento = $documento; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email ? mb_strtolower(trim($email)) : null; return $this; }
    public function getTelefone(): ?string { return $this->telefone; }
    public function setTelefone(?string $telefone): static { $this->telefone = $telefone; return $this; }
    public function getSite(): ?string { return $this->site; }
    public function setSite(?string $site): static { $this->site = $site; return $this; }
    public function getSegmento(): ?string { return $this->segmento; }
    public function setSegmento(?string $segmento): static { $this->segmento = $segmento; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; $this->touch(); return $this; }
    public function getNotas(): ?string { return $this->notas; }
    public function setNotas(?string $notas): static { $this->notas = $notas; return $this; }
    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $owner): static { $this->owner = $owner; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): void { $this->atualizadoEm = new \DateTimeImmutable(); }

    /** @return list<string> */
    public static function statusList(): array
    {
        return [self::STATUS_PROSPECT, self::STATUS_ATIVO, self::STATUS_INATIVO];
    }
}
