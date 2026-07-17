<?php

namespace App\Entity;

use App\Repository\ClinicContaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicContaRepository::class)]
#[ORM\Table(name: 'clinic_conta')]
#[ORM\UniqueConstraint(name: 'UNIQ_CLINIC_CONTA_AGENDAMENTO', columns: ['agendamento_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_CLINIC_CONTA_ATENDIMENTO', columns: ['atendimento_id'])]
#[ORM\Index(columns: ['empresa_id', 'status'], name: 'IDX_CLINIC_CONTA_EMPRESA_STATUS')]
class ClinicConta
{
    public const TIPO_PARTICULAR = 'particular';
    public const TIPO_CORTESIA = 'cortesia';
    public const TIPO_CONVENIO = 'convenio';

    public const STATUS_ABERTO = 'aberto';
    public const STATUS_PAGO = 'pago';
    public const STATUS_CANCELADO = 'cancelado';
    public const STATUS_GLOSADO = 'glosado';

    public const TIPOS = [
        self::TIPO_PARTICULAR,
        self::TIPO_CORTESIA,
        self::TIPO_CONVENIO,
    ];

    public const STATUSES = [
        self::STATUS_ABERTO,
        self::STATUS_PAGO,
        self::STATUS_CANCELADO,
        self::STATUS_GLOSADO,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\OneToOne(targetEntity: ClinicAgendamento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicAgendamento $agendamento = null;

    #[ORM\OneToOne(targetEntity: ClinicAtendimento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicAtendimento $atendimento = null;

    #[ORM\ManyToOne(targetEntity: PosOperatorioPaciente::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PosOperatorioPaciente $paciente;

    #[ORM\ManyToOne(targetEntity: ClinicConvenio::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicConvenio $convenio = null;

    #[ORM\Column(length: 16)]
    private string $tipo = self::TIPO_PARTICULAR;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ABERTO;

    #[ORM\Column(nullable: true)]
    private ?int $valorCentavos = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $pagoEm = null;

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $paymentProvider = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $paymentExternalId = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $paymentUrl = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $paymentStatus = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(type: 'datetime_immutable')]
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

    public function getAgendamento(): ?ClinicAgendamento
    {
        return $this->agendamento;
    }

    public function setAgendamento(?ClinicAgendamento $agendamento): static
    {
        $this->agendamento = $agendamento;

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

    public function getConvenio(): ?ClinicConvenio
    {
        return $this->convenio;
    }

    public function setConvenio(?ClinicConvenio $convenio): static
    {
        $this->convenio = $convenio;

        return $this;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

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

    public function getValorCentavos(): ?int
    {
        return $this->valorCentavos;
    }

    public function setValorCentavos(?int $valorCentavos): static
    {
        $this->valorCentavos = $valorCentavos;

        return $this;
    }

    public function getDescricao(): ?string
    {
        return $this->descricao;
    }

    public function setDescricao(?string $descricao): static
    {
        $this->descricao = $descricao;

        return $this;
    }

    public function getPagoEm(): ?\DateTimeImmutable
    {
        return $this->pagoEm;
    }

    public function setPagoEm(?\DateTimeImmutable $pagoEm): static
    {
        $this->pagoEm = $pagoEm;

        return $this;
    }

    public function getPaymentProvider(): ?string
    {
        return $this->paymentProvider;
    }

    public function setPaymentProvider(?string $paymentProvider): static
    {
        $this->paymentProvider = $paymentProvider;

        return $this;
    }

    public function getPaymentExternalId(): ?string
    {
        return $this->paymentExternalId;
    }

    public function setPaymentExternalId(?string $paymentExternalId): static
    {
        $this->paymentExternalId = $paymentExternalId;

        return $this;
    }

    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function setPaymentUrl(?string $paymentUrl): static
    {
        $this->paymentUrl = $paymentUrl;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(?string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;

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

    public function isAberto(): bool
    {
        return $this->status === self::STATUS_ABERTO;
    }
}
