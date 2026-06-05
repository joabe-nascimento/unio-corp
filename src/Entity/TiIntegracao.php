<?php

namespace App\Entity;

use App\Repository\TiIntegracaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiIntegracaoRepository::class)]
#[ORM\Table(name: 'ti_integracao')]
class TiIntegracao
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_DOWN = 'down';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 120)]
    private string $nome;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_HEALTHY;

    #[ORM\Column(length: 16)]
    private string $latencia = '—';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $uptime = '99.90';

    #[ORM\Column(type: 'integer')]
    private int $eventos24h = 0;

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
            'name' => $this->nome,
            'nome' => $this->nome,
            'status' => $this->status,
            'latency' => $this->latencia,
            'latencia' => $this->latencia,
            'uptime' => (float) $this->uptime,
            'events' => $this->eventos24h,
            'eventos_24h' => $this->eventos24h,
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

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = $nome;

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

    public function getLatencia(): string
    {
        return $this->latencia;
    }

    public function setLatencia(string $latencia): static
    {
        $this->latencia = $latencia;

        return $this;
    }

    public function getUptime(): string
    {
        return $this->uptime;
    }

    public function setUptime(string $uptime): static
    {
        $this->uptime = $uptime;

        return $this;
    }

    public function getEventos24h(): int
    {
        return $this->eventos24h;
    }

    public function setEventos24h(int $eventos24h): static
    {
        $this->eventos24h = $eventos24h;

        return $this;
    }
}
