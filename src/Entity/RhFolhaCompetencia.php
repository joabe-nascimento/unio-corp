<?php

namespace App\Entity;

use App\Repository\RhFolhaCompetenciaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhFolhaCompetenciaRepository::class)]
#[ORM\Table(name: 'rh_folha_competencia')]
#[ORM\UniqueConstraint(name: 'UNIQ_FOLHA_EMPRESA_REF', fields: ['empresa', 'referencia'])]
class RhFolhaCompetencia
{
    public const STATUS_ABERTA = 'ABERTA';
    public const STATUS_FECHADA = 'FECHADA';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 7)]
    private string $referencia;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ABERTA;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $totalProventos = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $totalDescontos = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $totalLiquido = '0';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $fechadoEm = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    /** @var Collection<int, RhFolhaLancamento> */
    #[ORM\OneToMany(targetEntity: RhFolhaLancamento::class, mappedBy: 'competencia', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lancamentos;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->lancamentos = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getReferencia(): string { return $this->referencia; }
    public function setReferencia(string $referencia): static { $this->referencia = $referencia; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getTotalProventos(): string { return $this->totalProventos; }
    public function setTotalProventos(string $v): static { $this->totalProventos = $v; return $this; }

    public function getTotalDescontos(): string { return $this->totalDescontos; }
    public function setTotalDescontos(string $v): static { $this->totalDescontos = $v; return $this; }

    public function getTotalLiquido(): string { return $this->totalLiquido; }
    public function setTotalLiquido(string $v): static { $this->totalLiquido = $v; return $this; }

    public function getFechadoEm(): ?\DateTimeImmutable { return $this->fechadoEm; }
    public function setFechadoEm(?\DateTimeImmutable $fechadoEm): static { $this->fechadoEm = $fechadoEm; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    /** @return Collection<int, RhFolhaLancamento> */
    public function getLancamentos(): Collection { return $this->lancamentos; }

    public function isFechada(): bool
    {
        return $this->status === self::STATUS_FECHADA;
    }

    public function getReferenciaLabel(): string
    {
        $parts = explode('-', $this->referencia);

        return ($parts[1] ?? '') . '/' . ($parts[0] ?? $this->referencia);
    }
}
