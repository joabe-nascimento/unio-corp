<?php

namespace App\Entity;

use App\Repository\JuridicoMetaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Meta de desempenho do escritório (receita ou taxa de êxito), opcionalmente
 * segmentada por área do direito ou por advogado responsável — usada pelo
 * painel de Analytics Jurídico para medir progresso real x objetivo do mês.
 */
#[ORM\Entity(repositoryClass: JuridicoMetaRepository::class)]
#[ORM\Table(name: 'juridico_meta')]
#[ORM\Index(columns: ['empresa_id', 'periodo'], name: 'IDX_JUR_META_EMPRESA_PERIODO')]
class JuridicoMeta
{
    public const TIPO_RECEITA = 'receita';
    public const TIPO_TAXA_EXITO = 'taxa_exito';
    public const TIPOS = [self::TIPO_RECEITA, self::TIPO_TAXA_EXITO];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 20)]
    private string $tipo;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $area = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $responsavel = null;

    #[ORM\Column(length: 7)]
    private string $periodo;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $valorMeta;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $criadoPor = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }
    public function getArea(): ?string { return $this->area; }
    public function setArea(?string $area): static { $this->area = $area; return $this; }
    public function getResponsavel(): ?User { return $this->responsavel; }
    public function setResponsavel(?User $responsavel): static { $this->responsavel = $responsavel; return $this; }
    public function getPeriodo(): string { return $this->periodo; }
    public function setPeriodo(string $periodo): static { $this->periodo = $periodo; return $this; }
    public function getValorMeta(): string { return $this->valorMeta; }
    public function setValorMeta(string $valorMeta): static { $this->valorMeta = $valorMeta; return $this; }
    public function getCriadoPor(): ?User { return $this->criadoPor; }
    public function setCriadoPor(?User $criadoPor): static { $this->criadoPor = $criadoPor; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    public function getEscopoLabel(): string
    {
        if ($this->responsavel !== null) {
            return $this->responsavel->getNome() ?? 'Advogado';
        }

        if ($this->area !== null && $this->area !== '') {
            return $this->area;
        }

        return 'Escritório inteiro';
    }
}
