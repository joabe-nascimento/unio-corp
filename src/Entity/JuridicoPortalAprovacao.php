<?php

namespace App\Entity;

use App\Repository\JuridicoPortalAprovacaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoPortalAprovacaoRepository::class)]
#[ORM\Table(name: 'juridico_portal_aprovacao')]
class JuridicoPortalAprovacao
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JuridicoCliente::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JuridicoCliente $cliente;

    #[ORM\ManyToOne(targetEntity: JuridicoDocumento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoDocumento $documento = null;

    #[ORM\ManyToOne(targetEntity: JuridicoProcesso::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JuridicoProcesso $processo = null;

    #[ORM\Column]
    private bool $aceito = false;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCliente(): JuridicoCliente { return $this->cliente; }
    public function setCliente(JuridicoCliente $cliente): static { $this->cliente = $cliente; return $this; }
    public function getDocumento(): ?JuridicoDocumento { return $this->documento; }
    public function setDocumento(?JuridicoDocumento $documento): static { $this->documento = $documento; return $this; }
    public function getProcesso(): ?JuridicoProcesso { return $this->processo; }
    public function setProcesso(?JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function isAceito(): bool { return $this->aceito; }
    public function setAceito(bool $aceito): static { $this->aceito = $aceito; return $this; }
    public function getIp(): ?string { return $this->ip; }
    public function setIp(?string $ip): static { $this->ip = $ip; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
