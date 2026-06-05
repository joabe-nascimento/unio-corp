<?php

namespace App\Entity;

use App\Repository\IntegSchemaDriftRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegSchemaDriftRepository::class)]
#[ORM\Table(name: 'integ_schema_drift')]
class IntegSchemaDrift
{
    public const SEV_CRITICA = 'critica';
    public const SEV_MEDIA = 'media';
    public const SEV_BAIXA = 'baixa';

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

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $flowKey = null;

    #[ORM\Column(length: 80)]
    private string $campoOrigem;

    #[ORM\Column(length: 80)]
    private string $campoEsperado;

    #[ORM\Column(length: 80)]
    private string $campoDetectado;

    #[ORM\Column(length: 16)]
    private string $severidade = self::SEV_MEDIA;

    #[ORM\Column(type: 'text')]
    private string $sugestao;

    #[ORM\Column(type: 'boolean')]
    private bool $resolvido = false;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $resolucao = null; // 'aceito', 'ignorado', 'mapeado'

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvidoEm = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $resolvidoPor = null;

    #[ORM\Column]
    private \DateTimeImmutable $detectadoEm;

    public function __construct()
    {
        $this->detectadoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'db_id' => $this->id,
            'flow_key' => $this->flowKey,
            'conector' => $this->conector?->getNome(),
            'campo_origem' => $this->campoOrigem,
            'campo_esperado' => $this->campoEsperado,
            'campo_detectado' => $this->campoDetectado,
            'severidade' => $this->severidade,
            'sugestao' => $this->sugestao,
            'resolvido' => $this->resolvido,
            'resolucao' => $this->resolucao,
            'resolvido_em' => $this->resolvidoEm?->format('d/m H:i'),
            'resolvido_por' => $this->resolvidoPor,
            'detectado_em' => $this->detectadoEm->format('d/m H:i'),
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

    public function getFlowKey(): ?string
    {
        return $this->flowKey;
    }

    public function setFlowKey(?string $flowKey): static
    {
        $this->flowKey = $flowKey;

        return $this;
    }

    public function getCampoOrigem(): string
    {
        return $this->campoOrigem;
    }

    public function setCampoOrigem(string $campoOrigem): static
    {
        $this->campoOrigem = $campoOrigem;

        return $this;
    }

    public function getCampoEsperado(): string
    {
        return $this->campoEsperado;
    }

    public function setCampoEsperado(string $campoEsperado): static
    {
        $this->campoEsperado = $campoEsperado;

        return $this;
    }

    public function getCampoDetectado(): string
    {
        return $this->campoDetectado;
    }

    public function setCampoDetectado(string $campoDetectado): static
    {
        $this->campoDetectado = $campoDetectado;

        return $this;
    }

    public function getSeveridade(): string
    {
        return $this->severidade;
    }

    public function setSeveridade(string $severidade): static
    {
        $this->severidade = $severidade;

        return $this;
    }

    public function getSugestao(): string
    {
        return $this->sugestao;
    }

    public function setSugestao(string $sugestao): static
    {
        $this->sugestao = $sugestao;

        return $this;
    }

    public function isResolvido(): bool
    {
        return $this->resolvido;
    }

    public function setResolvido(bool $resolvido): static
    {
        $this->resolvido = $resolvido;

        return $this;
    }

    public function getResolucao(): ?string
    {
        return $this->resolucao;
    }

    public function setResolucao(?string $resolucao): static
    {
        $this->resolucao = $resolucao;

        return $this;
    }

    public function getResolvidoEm(): ?\DateTimeImmutable
    {
        return $this->resolvidoEm;
    }

    public function setResolvidoEm(?\DateTimeImmutable $resolvidoEm): static
    {
        $this->resolvidoEm = $resolvidoEm;

        return $this;
    }

    public function getResolvidoPor(): ?string
    {
        return $this->resolvidoPor;
    }

    public function setResolvidoPor(?string $resolvidoPor): static
    {
        $this->resolvidoPor = $resolvidoPor;

        return $this;
    }
}
