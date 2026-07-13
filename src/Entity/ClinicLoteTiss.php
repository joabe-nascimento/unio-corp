<?php

namespace App\Entity;

use App\Repository\ClinicLoteTissRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicLoteTissRepository::class)]
#[ORM\Table(name: 'clinic_lote_tiss')]
#[ORM\Index(columns: ['empresa_id', 'status'], name: 'IDX_CLINIC_LOTE_EMPRESA_STATUS')]
class ClinicLoteTiss
{
    public const STATUS_ABERTO = 'aberto';
    public const STATUS_FECHADO = 'fechado';
    public const STATUS_ENVIADO = 'enviado';

    public const STATUSES = [
        self::STATUS_ABERTO,
        self::STATUS_FECHADO,
        self::STATUS_ENVIADO,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: ClinicConvenio::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ClinicConvenio $convenio;

    #[ORM\Column(length: 40)]
    private string $numero = '';

    /** Competência AAAAMM ou AAAA-MM (armazenado como AAAA-MM). */
    #[ORM\Column(length: 7)]
    private string $competencia = '';

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ABERTO;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $atualizadoEm;

    /** @var Collection<int, ClinicGuiaTiss> */
    #[ORM\OneToMany(mappedBy: 'lote', targetEntity: ClinicGuiaTiss::class)]
    #[ORM\OrderBy(['criadoEm' => 'ASC'])]
    private Collection $guias;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->criadoEm = $now;
        $this->atualizadoEm = $now;
        $this->guias = new ArrayCollection();
    }

    public function touch(): void
    {
        $this->atualizadoEm = new \DateTimeImmutable();
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

    public function getConvenio(): ClinicConvenio
    {
        return $this->convenio;
    }

    public function setConvenio(ClinicConvenio $convenio): static
    {
        $this->convenio = $convenio;

        return $this;
    }

    public function getNumero(): string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getCompetencia(): string
    {
        return $this->competencia;
    }

    public function setCompetencia(string $competencia): static
    {
        $this->competencia = $competencia;

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

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getAtualizadoEm(): \DateTimeImmutable
    {
        return $this->atualizadoEm;
    }

    /** @return Collection<int, ClinicGuiaTiss> */
    public function getGuias(): Collection
    {
        return $this->guias;
    }

    public function addGuia(ClinicGuiaTiss $guia): static
    {
        if (!$this->guias->contains($guia)) {
            $this->guias->add($guia);
            $guia->setLote($this);
        }

        return $this;
    }

    public function removeGuia(ClinicGuiaTiss $guia): static
    {
        if ($this->guias->removeElement($guia) && $guia->getLote() === $this) {
            $guia->setLote(null);
        }

        return $this;
    }

    public function isAberto(): bool
    {
        return $this->status === self::STATUS_ABERTO;
    }

    public function canExportXml(): bool
    {
        return \in_array($this->status, [self::STATUS_FECHADO, self::STATUS_ENVIADO], true)
            && !$this->guias->isEmpty();
    }

    public function totalCentavos(): int
    {
        $total = 0;
        foreach ($this->guias as $guia) {
            $total += $guia->totalCentavos();
        }

        return $total;
    }

    public function competenciaCompacta(): string
    {
        return str_replace('-', '', $this->competencia);
    }
}
