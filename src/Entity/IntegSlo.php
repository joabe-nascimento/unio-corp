<?php

namespace App\Entity;

use App\Repository\IntegSloRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegSloRepository::class)]
#[ORM\Table(name: 'integ_slo')]
class IntegSlo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 64)]
    private string $flowKey;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $metaUptime = '99.00';

    #[ORM\Column(type: 'integer')]
    private int $metaLatenciaMs = 300;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $uptimeAtual = '99.90';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $latenciaAtualMs = null;

    #[ORM\Column(type: 'boolean')]
    private bool $emBrecha = false;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function isEmBrecha(): bool
    {
        return (float) $this->uptimeAtual < (float) $this->metaUptime
            || ($this->latenciaAtualMs !== null && $this->latenciaAtualMs > $this->metaLatenciaMs);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'flow_key' => $this->flowKey,
            'titulo' => $this->titulo,
            'meta_uptime' => (float) $this->metaUptime,
            'meta_latencia_ms' => $this->metaLatenciaMs,
            'uptime_atual' => (float) $this->uptimeAtual,
            'latencia_atual_ms' => $this->latenciaAtualMs,
            'em_brecha' => $this->isEmBrecha(),
            'status' => $this->isEmBrecha() ? 'brecha' : 'ok',
            'gap_uptime' => round((float) $this->uptimeAtual - (float) $this->metaUptime, 2),
        ];
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }

    public function setEmpresa(Empresa $e): static { $this->empresa = $e; return $this; }

    public function getFlowKey(): string { return $this->flowKey; }

    public function setFlowKey(string $fk): static { $this->flowKey = $fk; return $this; }

    public function getTitulo(): string { return $this->titulo; }

    public function setTitulo(string $t): static { $this->titulo = $t; return $this; }

    public function getMetaUptime(): string { return $this->metaUptime; }

    public function setMetaUptime(string $v): static { $this->metaUptime = $v; return $this; }

    public function getMetaLatenciaMs(): int { return $this->metaLatenciaMs; }

    public function setMetaLatenciaMs(int $v): static { $this->metaLatenciaMs = $v; return $this; }

    public function getUptimeAtual(): string { return $this->uptimeAtual; }

    public function setUptimeAtual(string $v): static { $this->uptimeAtual = $v; return $this; }

    public function getLatenciaAtualMs(): ?int { return $this->latenciaAtualMs; }

    public function setLatenciaAtualMs(?int $v): static { $this->latenciaAtualMs = $v; return $this; }

    public function isSetEmBrecha(): bool { return $this->emBrecha; }

    public function setEmBrecha(bool $v): static { $this->emBrecha = $v; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
