<?php

namespace App\Entity;

use App\Repository\IntegLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegLogRepository::class)]
#[ORM\Table(name: 'integ_log')]
class IntegLog
{
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARN = 'warn';
    public const LEVEL_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: IntegConector::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?IntegConector $conector = null;

    #[ORM\ManyToOne(targetEntity: IntegWebhook::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?IntegWebhook $webhook = null;

    #[ORM\Column(length: 8)]
    private string $nivel = self::LEVEL_INFO;

    #[ORM\Column(length: 80)]
    private string $origem;

    #[ORM\Column(type: 'text')]
    private string $mensagem;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $traceId = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'db_id' => $this->id,
            'time' => $this->criadoEm->format('d/m H:i:s'),
            'level' => $this->nivel,
            'connector' => $this->conector?->getNome() ?? $this->origem,
            'origem' => $this->origem,
            'message' => $this->mensagem,
            'mensagem' => $this->mensagem,
            'trace_id' => $this->traceId,
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

    public function getConector(): ?IntegConector
    {
        return $this->conector;
    }

    public function setConector(?IntegConector $conector): static
    {
        $this->conector = $conector;

        return $this;
    }

    public function getWebhook(): ?IntegWebhook
    {
        return $this->webhook;
    }

    public function setWebhook(?IntegWebhook $webhook): static
    {
        $this->webhook = $webhook;

        return $this;
    }

    public function getNivel(): string
    {
        return $this->nivel;
    }

    public function setNivel(string $nivel): static
    {
        $this->nivel = $nivel;

        return $this;
    }

    public function getOrigem(): string
    {
        return $this->origem;
    }

    public function setOrigem(string $origem): static
    {
        $this->origem = $origem;

        return $this;
    }

    public function getMensagem(): string
    {
        return $this->mensagem;
    }

    public function setMensagem(string $mensagem): static
    {
        $this->mensagem = $mensagem;

        return $this;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    public function setTraceId(?string $traceId): static
    {
        $this->traceId = $traceId;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
