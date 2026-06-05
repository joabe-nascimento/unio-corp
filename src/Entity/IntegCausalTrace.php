<?php

namespace App\Entity;

use App\Repository\IntegCausalTraceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegCausalTraceRepository::class)]
#[ORM\Table(name: 'integ_causal_trace')]
class IntegCausalTrace
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_IDLE = 'idle';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 64)]
    private string $flowKey;

    #[ORM\Column(length: 160)]
    private string $titulo;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_HEALTHY;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $confiabilidade = '99.00';

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $impacto = [];

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $nos = [];

    /** @var list<int>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tendencia = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $previsao = null;

    #[ORM\Column]
    private \DateTimeImmutable $ultimoEventoEm;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->criadoEm = $now;
        $this->ultimoEventoEm = $now;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'db_id' => $this->id,
            'flow_key' => $this->flowKey,
            'titulo' => $this->titulo,
            'status' => $this->status,
            'confiabilidade' => (float) $this->confiabilidade,
            'impacto' => $this->impacto,
            'nos' => $this->nos,
            'tendencia' => $this->tendencia ?? [],
            'previsao' => $this->previsao ?? [],
            'ultimo_evento' => $this->ultimoEventoEm->format('d/m H:i'),
            'ultimo_evento_rel' => $this->relativeTime($this->ultimoEventoEm),
        ];
    }

    private function relativeTime(\DateTimeImmutable $at): string
    {
        $diff = (new \DateTimeImmutable())->getTimestamp() - $at->getTimestamp();
        if ($diff < 3600) {
            return max(1, (int) floor($diff / 60)) . ' min atrás';
        }
        if ($diff < 86400) {
            return (int) floor($diff / 3600) . ' h atrás';
        }

        return (int) floor($diff / 86400) . ' d atrás';
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

    public function getFlowKey(): string
    {
        return $this->flowKey;
    }

    public function setFlowKey(string $flowKey): static
    {
        $this->flowKey = $flowKey;

        return $this;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): static
    {
        $this->titulo = $titulo;

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

    public function getConfiabilidade(): string
    {
        return $this->confiabilidade;
    }

    public function setConfiabilidade(float|string $confiabilidade): static
    {
        $this->confiabilidade = number_format((float) $confiabilidade, 2, '.', '');

        return $this;
    }

    /** @return array<string, mixed> */
    public function getImpacto(): array
    {
        return $this->impacto;
    }

    /** @param array<string, mixed> $impacto */
    public function setImpacto(array $impacto): static
    {
        $this->impacto = $impacto;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getNos(): array
    {
        return $this->nos;
    }

    /** @param list<array<string, mixed>> $nos */
    public function setNos(array $nos): static
    {
        $this->nos = $nos;

        return $this;
    }

    /** @return list<int> */
    public function getTendencia(): array
    {
        return $this->tendencia ?? [];
    }

    /** @param list<int> $tendencia */
    public function setTendencia(array $tendencia): static
    {
        $this->tendencia = $tendencia;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getPrevisao(): array
    {
        return $this->previsao ?? [];
    }

    /** @param array<string, mixed> $previsao */
    public function setPrevisao(array $previsao): static
    {
        $this->previsao = $previsao;

        return $this;
    }

    public function getUltimoEventoEm(): \DateTimeImmutable
    {
        return $this->ultimoEventoEm;
    }

    public function setUltimoEventoEm(\DateTimeImmutable $ultimoEventoEm): static
    {
        $this->ultimoEventoEm = $ultimoEventoEm;

        return $this;
    }
}
