<?php

namespace App\Entity;

use App\Repository\JuridicoPrazoAlertaLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoPrazoAlertaLogRepository::class)]
#[ORM\Table(name: 'juridico_prazo_alerta_log')]
#[ORM\UniqueConstraint(name: 'UNIQ_JUR_PRAZO_ALERTA', columns: ['prazo_id', 'nivel', 'canal'])]
class JuridicoPrazoAlertaLog
{
    public const CANAL_WHATSAPP = 'whatsapp';
    public const CANAL_EMAIL = 'email';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JuridicoPrazo::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JuridicoPrazo $prazo;

    #[ORM\Column(length: 16)]
    private string $nivel;

    #[ORM\Column(length: 16)]
    private string $canal;

    #[ORM\Column]
    private \DateTimeImmutable $enviadoEm;

    public function __construct()
    {
        $this->enviadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPrazo(): JuridicoPrazo { return $this->prazo; }
    public function setPrazo(JuridicoPrazo $prazo): static { $this->prazo = $prazo; return $this; }
    public function getNivel(): string { return $this->nivel; }
    public function setNivel(string $nivel): static { $this->nivel = $nivel; return $this; }
    public function getCanal(): string { return $this->canal; }
    public function setCanal(string $canal): static { $this->canal = $canal; return $this; }
    public function getEnviadoEm(): \DateTimeImmutable { return $this->enviadoEm; }
}
