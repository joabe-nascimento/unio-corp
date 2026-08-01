<?php

namespace App\Entity;

use App\Repository\JuridicoPublicacaoConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoPublicacaoConfigRepository::class)]
#[ORM\Table(name: 'juridico_publicacao_config')]
#[ORM\UniqueConstraint(name: 'UNIQ_JUR_PUB_CFG_EMPRESA', columns: ['empresa_id'])]
class JuridicoPublicacaoConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column]
    private bool $prazoAutomatico = true;

    #[ORM\Column]
    private bool $alertaWhatsapp = true;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefoneAlerta = null;

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
    public function isPrazoAutomatico(): bool { return $this->prazoAutomatico; }
    public function setPrazoAutomatico(bool $prazoAutomatico): static { $this->prazoAutomatico = $prazoAutomatico; return $this; }
    public function isAlertaWhatsapp(): bool { return $this->alertaWhatsapp; }
    public function setAlertaWhatsapp(bool $alertaWhatsapp): static { $this->alertaWhatsapp = $alertaWhatsapp; return $this; }
    public function getTelefoneAlerta(): ?string { return $this->telefoneAlerta; }
    public function setTelefoneAlerta(?string $telefoneAlerta): static { $this->telefoneAlerta = $telefoneAlerta; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }
}
