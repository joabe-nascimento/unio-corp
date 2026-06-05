<?php

namespace App\Entity;

use App\Repository\IntegConectorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegConectorRepository::class)]
#[ORM\Table(name: 'integ_conector')]
class IntegConector
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ERROR = 'error';

    public const HEALTH_HEALTHY = 'healthy';
    public const HEALTH_DEGRADED = 'degraded';
    public const HEALTH_DOWN = 'down';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 64)]
    private string $catalogoId;

    #[ORM\Column(length: 120)]
    private string $nome;

    #[ORM\Column(length: 32)]
    private string $categoria;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(length: 16)]
    private string $health = self::HEALTH_HEALTHY;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $endpointUrl = null;

    #[ORM\Column(length: 16)]
    private string $latencia = '—';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $uptime = '99.90';

    #[ORM\Column(type: 'integer')]
    private int $eventos24h = 0;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $hubsAlvo = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $configNotas = null;

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

    public function touch(): void
    {
        $this->atualizadoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'db_id' => $this->id,
            'catalogo_id' => $this->catalogoId,
            'name' => $this->nome,
            'nome' => $this->nome,
            'categoria' => $this->categoria,
            'status' => $this->health,
            'health' => $this->health,
            'operational_status' => $this->status,
            'endpoint_url' => $this->endpointUrl,
            'latency' => $this->latencia,
            'latencia' => $this->latencia,
            'uptime' => (float) $this->uptime,
            'events' => $this->eventos24h,
            'eventos_24h' => $this->eventos24h,
            'hubs_alvo' => $this->hubsAlvo,
            'config_notas' => $this->configNotas,
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

    public function getCatalogoId(): string
    {
        return $this->catalogoId;
    }

    public function setCatalogoId(string $catalogoId): static
    {
        $this->catalogoId = $catalogoId;

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

    public function getCategoria(): string
    {
        return $this->categoria;
    }

    public function setCategoria(string $categoria): static
    {
        $this->categoria = $categoria;

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

    public function getHealth(): string
    {
        return $this->health;
    }

    public function setHealth(string $health): static
    {
        $this->health = $health;

        return $this;
    }

    public function getEndpointUrl(): ?string
    {
        return $this->endpointUrl;
    }

    public function setEndpointUrl(?string $endpointUrl): static
    {
        $this->endpointUrl = $endpointUrl;

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

    /** @return list<string> */
    public function getHubsAlvo(): array
    {
        return $this->hubsAlvo;
    }

    /** @param list<string> $hubsAlvo */
    public function setHubsAlvo(array $hubsAlvo): static
    {
        $this->hubsAlvo = array_values($hubsAlvo);

        return $this;
    }

    public function getConfigNotas(): ?string
    {
        return $this->configNotas;
    }

    public function setConfigNotas(?string $configNotas): static
    {
        $this->configNotas = $configNotas;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getAtualizadoEm(): \DateTimeImmutable
    {
        return $this->atualizadoEm;
    }
}
