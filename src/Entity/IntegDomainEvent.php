<?php

namespace App\Entity;

use App\Repository\IntegDomainEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegDomainEventRepository::class)]
#[ORM\Table(name: 'integ_domain_event')]
#[ORM\Index(name: 'IDX_DE_EMPRESA_TIPO', columns: ['empresa_id', 'tipo'])]
class IntegDomainEvent
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_PROCESSADO = 'processado';
    public const STATUS_FALHOU = 'falhou';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 80)]
    private string $tipo;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $origem = null;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_PENDENTE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $erroProcessamento = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processadoEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'payload_preview' => mb_substr(json_encode($this->payload) ?: '', 0, 100),
            'origem' => $this->origem,
            'status' => $this->status,
            'erro' => $this->erroProcessamento,
            'criado_em' => $this->criadoEm->format('d/m/Y H:i:s'),
            'processado_em' => $this->processadoEm?->format('d/m/Y H:i:s'),
        ];
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

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getOrigem(): ?string
    {
        return $this->origem;
    }

    public function setOrigem(?string $origem): static
    {
        $this->origem = $origem;

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

    public function getErroProcessamento(): ?string
    {
        return $this->erroProcessamento;
    }

    public function setErroProcessamento(?string $erroProcessamento): static
    {
        $this->erroProcessamento = $erroProcessamento;

        return $this;
    }

    public function getProcessadoEm(): ?\DateTimeImmutable
    {
        return $this->processadoEm;
    }

    public function setProcessadoEm(?\DateTimeImmutable $processadoEm): static
    {
        $this->processadoEm = $processadoEm;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
