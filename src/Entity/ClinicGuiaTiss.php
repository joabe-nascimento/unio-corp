<?php

namespace App\Entity;

use App\Repository\ClinicGuiaTissRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicGuiaTissRepository::class)]
#[ORM\Table(name: 'clinic_guia_tiss')]
#[ORM\UniqueConstraint(name: 'UNIQ_CLINIC_GUIA_CONTA', columns: ['conta_id'])]
#[ORM\Index(columns: ['empresa_id', 'status'], name: 'IDX_CLINIC_GUIA_EMPRESA_STATUS')]
class ClinicGuiaTiss
{
    public const STATUS_RASCUNHO = 'rascunho';
    public const STATUS_ENVIADO = 'enviado';
    public const STATUS_AUTORIZADO = 'autorizado';
    public const STATUS_GLOSADO = 'glosado';
    public const STATUS_PAGO = 'pago';
    public const STATUS_CANCELADO = 'cancelado';

    public const STATUSES = [
        self::STATUS_RASCUNHO,
        self::STATUS_ENVIADO,
        self::STATUS_AUTORIZADO,
        self::STATUS_GLOSADO,
        self::STATUS_PAGO,
        self::STATUS_CANCELADO,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\OneToOne(targetEntity: ClinicConta::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ClinicConta $conta;

    #[ORM\ManyToOne(targetEntity: ClinicAtendimento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicAtendimento $atendimento = null;

    #[ORM\ManyToOne(targetEntity: PosOperatorioPaciente::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PosOperatorioPaciente $paciente;

    #[ORM\ManyToOne(targetEntity: ClinicConvenio::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ClinicConvenio $convenio;

    #[ORM\Column(length: 40)]
    private string $numeroGuia = '';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $senhaAutorizacao = null;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_RASCUNHO;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motivoGlosa = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $atualizadoEm;

    /** @var Collection<int, ClinicGuiaItem> */
    #[ORM\OneToMany(mappedBy: 'guia', targetEntity: ClinicGuiaItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $itens;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->criadoEm = $now;
        $this->atualizadoEm = $now;
        $this->itens = new ArrayCollection();
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

    public function getConta(): ClinicConta
    {
        return $this->conta;
    }

    public function setConta(ClinicConta $conta): static
    {
        $this->conta = $conta;

        return $this;
    }

    public function getAtendimento(): ?ClinicAtendimento
    {
        return $this->atendimento;
    }

    public function setAtendimento(?ClinicAtendimento $atendimento): static
    {
        $this->atendimento = $atendimento;

        return $this;
    }

    public function getPaciente(): PosOperatorioPaciente
    {
        return $this->paciente;
    }

    public function setPaciente(PosOperatorioPaciente $paciente): static
    {
        $this->paciente = $paciente;

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

    public function getNumeroGuia(): string
    {
        return $this->numeroGuia;
    }

    public function setNumeroGuia(string $numeroGuia): static
    {
        $this->numeroGuia = $numeroGuia;

        return $this;
    }

    public function getSenhaAutorizacao(): ?string
    {
        return $this->senhaAutorizacao;
    }

    public function setSenhaAutorizacao(?string $senhaAutorizacao): static
    {
        $this->senhaAutorizacao = $senhaAutorizacao;

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

    public function getMotivoGlosa(): ?string
    {
        return $this->motivoGlosa;
    }

    public function setMotivoGlosa(?string $motivoGlosa): static
    {
        $this->motivoGlosa = $motivoGlosa;

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

    /** @return Collection<int, ClinicGuiaItem> */
    public function getItens(): Collection
    {
        return $this->itens;
    }

    public function addItem(ClinicGuiaItem $item): static
    {
        if (!$this->itens->contains($item)) {
            $this->itens->add($item);
            $item->setGuia($this);
        }

        return $this;
    }

    public function removeItem(ClinicGuiaItem $item): static
    {
        $this->itens->removeElement($item);

        return $this;
    }

    public function isEditable(): bool
    {
        return \in_array($this->status, [self::STATUS_RASCUNHO, self::STATUS_ENVIADO, self::STATUS_AUTORIZADO], true);
    }

    public function totalCentavos(): int
    {
        $total = 0;
        foreach ($this->itens as $item) {
            $valor = $item->getValorCentavos();
            if ($valor !== null) {
                $total += $valor * max(1, $item->getQuantidade());
            }
        }

        return $total;
    }
}
