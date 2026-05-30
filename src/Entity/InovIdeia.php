<?php

namespace App\Entity;

use App\Repository\InovIdeiaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InovIdeiaRepository::class)]
#[ORM\Table(name: 'inov_ideia')]
class InovIdeia
{
    public const STAGE_IDEIA = 'ideia';
    public const STAGE_HIPOTESE = 'hipotese';
    public const STAGE_POC = 'poc';
    public const STAGE_PILOTO = 'piloto';
    public const STAGE_ESCALA = 'escala';
    public const STAGE_ARQUIVADO = 'arquivado';
    public const STAGE_KILL = 'kill';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $autor = null;

    #[ORM\Column(length: 16)]
    private string $codigo = '';

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resumo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $problema = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $hipotese = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $metricaSucesso = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $metodoTeste = null;

    #[ORM\Column(length: 24)]
    private string $estagio = self::STAGE_IDEIA;

    #[ORM\Column(type: 'smallint')]
    private int $impacto = 50;

    #[ORM\Column(type: 'smallint')]
    private int $esforco = 50;

    #[ORM\Column(type: 'smallint')]
    private int $votos = 0;

    #[ORM\Column(type: 'smallint')]
    private int $progresso = 0;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $rigor = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $tags = [];

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $hubRelacionado = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $ownerNome = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $metrica = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $categoria = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $urgencia = null;

    #[ORM\Column]
    private bool $arquivado = false;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->atualizadoEm = $this->criadoEm;
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getAutor(): ?User { return $this->autor; }
    public function setAutor(?User $autor): static { $this->autor = $autor; return $this; }

    public function getCodigo(): string { return $this->codigo; }
    public function setCodigo(string $codigo): static { $this->codigo = $codigo; return $this; }

    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }

    public function getResumo(): ?string { return $this->resumo; }
    public function setResumo(?string $resumo): static { $this->resumo = $resumo; return $this; }

    public function getProblema(): ?string { return $this->problema; }
    public function setProblema(?string $problema): static { $this->problema = $problema; return $this; }

    public function getHipotese(): ?string { return $this->hipotese; }
    public function setHipotese(?string $hipotese): static { $this->hipotese = $hipotese; return $this; }

    public function getMetricaSucesso(): ?string { return $this->metricaSucesso; }
    public function setMetricaSucesso(?string $metricaSucesso): static { $this->metricaSucesso = $metricaSucesso; return $this; }

    public function getMetodoTeste(): ?string { return $this->metodoTeste; }
    public function setMetodoTeste(?string $metodoTeste): static { $this->metodoTeste = $metodoTeste; return $this; }

    public function getEstagio(): string { return $this->estagio; }
    public function setEstagio(string $estagio): static { $this->estagio = $estagio; return $this; }

    public function getImpacto(): int { return $this->impacto; }
    public function setImpacto(int $impacto): static { $this->impacto = max(0, min(100, $impacto)); return $this; }

    public function getEsforco(): int { return $this->esforco; }
    public function setEsforco(int $esforco): static { $this->esforco = max(0, min(100, $esforco)); return $this; }

    public function getVotos(): int { return $this->votos; }
    public function setVotos(int $votos): static { $this->votos = max(0, $votos); return $this; }

    public function getProgresso(): int { return $this->progresso; }
    public function setProgresso(int $progresso): static { $this->progresso = max(0, min(100, $progresso)); return $this; }

    public function getRigor(): ?int { return $this->rigor; }
    public function setRigor(?int $rigor): static { $this->rigor = $rigor === null ? null : max(0, min(100, $rigor)); return $this; }

    /** @return list<string> */
    public function getTags(): array { return $this->tags; }
    /** @param list<string> $tags */
    public function setTags(array $tags): static { $this->tags = array_values(array_filter($tags)); return $this; }

    public function getHubRelacionado(): ?string { return $this->hubRelacionado; }
    public function setHubRelacionado(?string $hubRelacionado): static { $this->hubRelacionado = $hubRelacionado; return $this; }

    public function getOwnerNome(): ?string { return $this->ownerNome; }
    public function setOwnerNome(?string $ownerNome): static { $this->ownerNome = $ownerNome; return $this; }

    public function getMetrica(): ?string { return $this->metrica; }
    public function setMetrica(?string $metrica): static { $this->metrica = $metrica; return $this; }

    public function getCategoria(): ?string { return $this->categoria; }
    public function setCategoria(?string $categoria): static { $this->categoria = $categoria; return $this; }

    public function getUrgencia(): ?string { return $this->urgencia; }
    public function setUrgencia(?string $urgencia): static { $this->urgencia = $urgencia; return $this; }

    public function isArquivado(): bool { return $this->arquivado; }
    public function setArquivado(bool $arquivado): static { $this->arquivado = $arquivado; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): void { $this->atualizadoEm = new \DateTimeImmutable(); }

    public function getQuadrant(): string
    {
        if ($this->impacto >= 60 && $this->esforco < 50) {
            return 'quick_win';
        }
        if ($this->impacto >= 60 && $this->esforco >= 50) {
            return 'big_bet';
        }
        if ($this->impacto < 60 && $this->esforco < 50) {
            return 'fill_in';
        }

        return 'thankless';
    }

    public function getDaysOpen(): int
    {
        return max(0, (int) $this->criadoEm->diff(new \DateTimeImmutable())->days);
    }
}
