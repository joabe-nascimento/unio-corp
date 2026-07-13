<?php

namespace App\Entity;

use App\Repository\ClinicOutboundMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicOutboundMessageRepository::class)]
#[ORM\Table(name: 'clinic_outbound_message')]
#[ORM\Index(columns: ['empresa_id', 'criado_em'], name: 'idx_clinic_outbound_empresa_criado')]
#[ORM\Index(columns: ['empresa_id', 'status'], name: 'idx_clinic_outbound_empresa_status')]
class ClinicOutboundMessage
{
    public const CANAL_WHATSAPP = 'whatsapp';

    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 24)]
    private string $canal = self::CANAL_WHATSAPP;

    #[ORM\Column(length: 64)]
    private string $evento = '';

    #[ORM\Column(length: 32)]
    private string $destino = '';

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_SKIPPED;

    #[ORM\Column(length: 24)]
    private string $provider = 'noop';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $providerMessageId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $erro = null;

    #[ORM\Column(length: 240, nullable: true)]
    private ?string $corpoPreview = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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

    public function getCanal(): string
    {
        return $this->canal;
    }

    public function setCanal(string $canal): static
    {
        $this->canal = $canal;

        return $this;
    }

    public function getEvento(): string
    {
        return $this->evento;
    }

    public function setEvento(string $evento): static
    {
        $this->evento = $evento;

        return $this;
    }

    public function getDestino(): string
    {
        return $this->destino;
    }

    public function setDestino(string $destino): static
    {
        $this->destino = $destino;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderMessageId(): ?string
    {
        return $this->providerMessageId;
    }

    public function setProviderMessageId(?string $providerMessageId): static
    {
        $this->providerMessageId = $providerMessageId;

        return $this;
    }

    public function getErro(): ?string
    {
        return $this->erro;
    }

    public function setErro(?string $erro): static
    {
        $this->erro = $erro;

        return $this;
    }

    public function getCorpoPreview(): ?string
    {
        return $this->corpoPreview;
    }

    public function setCorpoPreview(?string $corpoPreview): static
    {
        $this->corpoPreview = $corpoPreview;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
