<?php

namespace App\Entity;

use App\Repository\JuridicoProcessoEventoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoProcessoEventoRepository::class)]
#[ORM\Table(name: 'juridico_processo_evento')]
#[ORM\Index(columns: ['processo_id', 'ocorreu_em'], name: 'IDX_JUR_PROC_EVT_OCO')]
class JuridicoProcessoEvento
{
    public const TIPO_MOVIMENTACAO = 'movimentacao';
    public const TIPO_PUBLICACAO = 'publicacao';
    public const TIPO_PRAZO = 'prazo';
    public const TIPO_TAREFA = 'tarefa';
    public const TIPO_DOCUMENTO = 'documento';
    public const TIPO_MENSAGEM = 'mensagem';
    public const TIPO_HONORARIO = 'honorario';
    public const TIPO_AUDIENCIA = 'audiencia';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: JuridicoProcesso::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JuridicoProcesso $processo;

    #[ORM\Column(length: 32)]
    private string $tipo;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resumo = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $referenciaTipo = null;

    #[ORM\Column(nullable: true)]
    private ?int $referenciaId = null;

    #[ORM\Column]
    private \DateTimeImmutable $ocorreuEm;

    #[ORM\Column]
    private bool $visivelPortal = true;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadataJson = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->ocorreuEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getProcesso(): JuridicoProcesso { return $this->processo; }
    public function setProcesso(JuridicoProcesso $processo): static { $this->processo = $processo; return $this; }
    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }
    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }
    public function getResumo(): ?string { return $this->resumo; }
    public function setResumo(?string $resumo): static { $this->resumo = $resumo; return $this; }
    public function getReferenciaTipo(): ?string { return $this->referenciaTipo; }
    public function setReferenciaTipo(?string $referenciaTipo): static { $this->referenciaTipo = $referenciaTipo; return $this; }
    public function getReferenciaId(): ?int { return $this->referenciaId; }
    public function setReferenciaId(?int $referenciaId): static { $this->referenciaId = $referenciaId; return $this; }
    public function getOcorreuEm(): \DateTimeImmutable { return $this->ocorreuEm; }
    public function setOcorreuEm(\DateTimeImmutable $ocorreuEm): static { $this->ocorreuEm = $ocorreuEm; return $this; }
    public function isVisivelPortal(): bool { return $this->visivelPortal; }
    public function setVisivelPortal(bool $visivelPortal): static { $this->visivelPortal = $visivelPortal; return $this; }
    /** @return array<string, mixed>|null */
    public function getMetadataJson(): ?array { return $this->metadataJson; }
    /** @param array<string, mixed>|null $metadataJson */
    public function setMetadataJson(?array $metadataJson): static { $this->metadataJson = $metadataJson; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
