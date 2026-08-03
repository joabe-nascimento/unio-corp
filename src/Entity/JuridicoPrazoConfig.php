<?php

namespace App\Entity;

use App\Repository\JuridicoPrazoConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoPrazoConfigRepository::class)]
#[ORM\Table(name: 'juridico_prazo_config')]
#[ORM\UniqueConstraint(name: 'UNIQ_JUR_PRAZO_CFG_EMPRESA', columns: ['empresa_id'])]
class JuridicoPrazoConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column]
    private bool $alertaWhatsapp = true;

    #[ORM\Column]
    private bool $alertaEmail = true;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefoneAlerta = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $emailAlerta = null;

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
    public function isAlertaWhatsapp(): bool { return $this->alertaWhatsapp; }
    public function setAlertaWhatsapp(bool $alertaWhatsapp): static { $this->alertaWhatsapp = $alertaWhatsapp; return $this; }
    public function isAlertaEmail(): bool { return $this->alertaEmail; }
    public function setAlertaEmail(bool $alertaEmail): static { $this->alertaEmail = $alertaEmail; return $this; }
    public function getTelefoneAlerta(): ?string { return $this->telefoneAlerta; }
    public function setTelefoneAlerta(?string $telefoneAlerta): static { $this->telefoneAlerta = $telefoneAlerta; return $this; }
    public function getEmailAlerta(): ?string { return $this->emailAlerta; }
    public function setEmailAlerta(?string $emailAlerta): static { $this->emailAlerta = $emailAlerta; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): static { $this->atualizadoEm = new \DateTimeImmutable(); return $this; }
}
