<?php

namespace App\Entity;

use App\Repository\ClinicAgendamentoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicAgendamentoRepository::class)]
#[ORM\Table(name: 'clinic_agendamento')]
#[ORM\Index(columns: ['empresa_id', 'inicio'], name: 'IDX_CLINIC_AGEND_EMPRESA_INICIO')]
class ClinicAgendamento
{
    public const STATUS_MARCADO = 'marcado';
    public const STATUS_CONFIRMADO = 'confirmado';
    public const STATUS_CHEGOU = 'chegou';
    public const STATUS_EM_ATENDIMENTO = 'em_atendimento';
    public const STATUS_FALTOU = 'faltou';
    public const STATUS_CANCELADO = 'cancelado';
    public const STATUS_ATENDIDO = 'atendido';

    public const ORIGEM_MANUAL = 'manual';
    public const ORIGEM_PROTOCOLO = 'protocolo';

    public const STATUSES = [
        self::STATUS_MARCADO,
        self::STATUS_CONFIRMADO,
        self::STATUS_CHEGOU,
        self::STATUS_EM_ATENDIMENTO,
        self::STATUS_FALTOU,
        self::STATUS_CANCELADO,
        self::STATUS_ATENDIDO,
    ];

    /** Fluxo típico de recepção (ordem de exibição nas ações). */
    public const STATUS_RECEPCAO = [
        self::STATUS_CONFIRMADO,
        self::STATUS_CHEGOU,
        self::STATUS_EM_ATENDIMENTO,
        self::STATUS_ATENDIDO,
    ];

    public const ORIGENS = [
        self::ORIGEM_MANUAL,
        self::ORIGEM_PROTOCOLO,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: PosOperatorioPaciente::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PosOperatorioPaciente $paciente;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $medico = null;

    #[ORM\ManyToOne(targetEntity: ClinicSala::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicSala $sala = null;

    #[ORM\ManyToOne(targetEntity: ClinicProfissional::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicProfissional $profissional = null;

    #[ORM\ManyToOne(targetEntity: ClinicProcedimento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicProcedimento $procedimento = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $inicio;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $fim;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_MARCADO;

    #[ORM\Column(length: 16)]
    private string $origem = self::ORIGEM_MANUAL;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $titulo = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $observacao = null;

    #[ORM\Column(nullable: true)]
    private ?int $protocoloDia = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lembreteConfirmacaoEm = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->criadoEm = $now;
        $this->atualizadoEm = $now;
        $this->inicio = $now;
        $this->fim = $now->modify('+30 minutes');
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

    public function getSala(): ?ClinicSala
    {
        return $this->sala;
    }

    public function setSala(?ClinicSala $sala): static
    {
        $this->sala = $sala;

        return $this;
    }

    public function getProfissional(): ?ClinicProfissional
    {
        return $this->profissional;
    }

    public function setProfissional(?ClinicProfissional $profissional): static
    {
        $this->profissional = $profissional;

        return $this;
    }

    public function getProcedimento(): ?ClinicProcedimento
    {
        return $this->procedimento;
    }

    public function setProcedimento(?ClinicProcedimento $procedimento): static
    {
        $this->procedimento = $procedimento;

        return $this;
    }

    public function getInicio(): \DateTimeImmutable
    {
        return $this->inicio;
    }

    public function setInicio(\DateTimeImmutable $inicio): static
    {
        $this->inicio = $inicio;

        return $this;
    }

    public function getFim(): \DateTimeImmutable
    {
        return $this->fim;
    }

    public function setFim(\DateTimeImmutable $fim): static
    {
        $this->fim = $fim;

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

    public function getOrigem(): string
    {
        return $this->origem;
    }

    public function setOrigem(string $origem): static
    {
        $this->origem = $origem;

        return $this;
    }

    public function getTitulo(): ?string
    {
        return $this->titulo;
    }

    public function setTitulo(?string $titulo): static
    {
        $this->titulo = $titulo;

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

    public function getProtocoloDia(): ?int
    {
        return $this->protocoloDia;
    }

    public function setProtocoloDia(?int $protocoloDia): static
    {
        $this->protocoloDia = $protocoloDia;

        return $this;
    }

    public function getLembreteConfirmacaoEm(): ?\DateTimeImmutable
    {
        return $this->lembreteConfirmacaoEm;
    }

    public function setLembreteConfirmacaoEm(?\DateTimeImmutable $lembreteConfirmacaoEm): static
    {
        $this->lembreteConfirmacaoEm = $lembreteConfirmacaoEm;

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
}
