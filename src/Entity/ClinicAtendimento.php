<?php

namespace App\Entity;

use App\Repository\ClinicAtendimentoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicAtendimentoRepository::class)]
#[ORM\Table(name: 'clinic_atendimento')]
#[ORM\UniqueConstraint(name: 'UNIQ_CLINIC_ATEND_AGENDAMENTO', columns: ['agendamento_id'])]
class ClinicAtendimento
{
    public const STATUS_EM_ANDAMENTO = 'em_andamento';
    public const STATUS_FINALIZADO = 'finalizado';
    public const STATUS_CANCELADO = 'cancelado';

    public const STATUSES = [
        self::STATUS_EM_ANDAMENTO,
        self::STATUS_FINALIZADO,
        self::STATUS_CANCELADO,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\OneToOne(targetEntity: ClinicAgendamento::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ClinicAgendamento $agendamento;

    #[ORM\ManyToOne(targetEntity: PosOperatorioPaciente::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PosOperatorioPaciente $paciente;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $medico = null;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_EM_ANDAMENTO;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $queixa = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $exame = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $conduta = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacao = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $iniciadoEm;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finalizadoEm = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->iniciadoEm = $now;
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

    public function getAgendamento(): ClinicAgendamento
    {
        return $this->agendamento;
    }

    public function setAgendamento(ClinicAgendamento $agendamento): static
    {
        $this->agendamento = $agendamento;

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

    public function getMedico(): ?User
    {
        return $this->medico;
    }

    public function setMedico(?User $medico): static
    {
        $this->medico = $medico;

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

    public function getQueixa(): ?string
    {
        return $this->queixa;
    }

    public function setQueixa(?string $queixa): static
    {
        $this->queixa = $queixa;

        return $this;
    }

    public function getExame(): ?string
    {
        return $this->exame;
    }

    public function setExame(?string $exame): static
    {
        $this->exame = $exame;

        return $this;
    }

    public function getConduta(): ?string
    {
        return $this->conduta;
    }

    public function setConduta(?string $conduta): static
    {
        $this->conduta = $conduta;

        return $this;
    }

    public function getObservacao(): ?string
    {
        return $this->observacao;
    }

    public function setObservacao(?string $observacao): static
    {
        $this->observacao = $observacao;

        return $this;
    }

    public function getIniciadoEm(): \DateTimeImmutable
    {
        return $this->iniciadoEm;
    }

    public function setIniciadoEm(\DateTimeImmutable $iniciadoEm): static
    {
        $this->iniciadoEm = $iniciadoEm;

        return $this;
    }

    public function getFinalizadoEm(): ?\DateTimeImmutable
    {
        return $this->finalizadoEm;
    }

    public function setFinalizadoEm(?\DateTimeImmutable $finalizadoEm): static
    {
        $this->finalizadoEm = $finalizadoEm;

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

    public function isEmAndamento(): bool
    {
        return $this->status === self::STATUS_EM_ANDAMENTO;
    }
}
