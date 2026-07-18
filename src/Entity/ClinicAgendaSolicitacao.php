<?php

namespace App\Entity;

use App\Repository\ClinicAgendaSolicitacaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicAgendaSolicitacaoRepository::class)]
#[ORM\Table(name: 'clinic_agenda_solicitacao')]
class ClinicAgendaSolicitacao
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_AGENDADO = 'agendado';
    public const STATUS_RECUSADO = 'recusado';
    public const STATUS_CANCELADO = 'cancelado';

    public const MOTIVO_CONSULTA = 'consulta';
    public const MOTIVO_RETORNO = 'retorno';
    public const MOTIVO_AVALIACAO = 'avaliacao';

    public const PERIODO_MANHA = 'manha';
    public const PERIODO_TARDE = 'tarde';
    public const PERIODO_INDIFERENTE = 'indiferente';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: PosOperatorioPaciente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PosOperatorioPaciente $paciente = null;

    #[ORM\ManyToOne(targetEntity: ClinicAgendamento::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicAgendamento $agendamento = null;

    #[ORM\Column(length: 160)]
    private string $nome = '';

    #[ORM\Column(length: 40)]
    private string $telefone = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 32)]
    private string $motivo = self::MOTIVO_CONSULTA;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataPreferida = null;

    #[ORM\Column(length: 16)]
    private string $periodo = self::PERIODO_INDIFERENTE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacao = null;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_PENDENTE;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
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

    public function getPaciente(): ?PosOperatorioPaciente
    {
        return $this->paciente;
    }

    public function setPaciente(?PosOperatorioPaciente $paciente): static
    {
        $this->paciente = $paciente;

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

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = $nome;

        return $this;
    }

    public function getTelefone(): string
    {
        return $this->telefone;
    }

    public function setTelefone(string $telefone): static
    {
        $this->telefone = $telefone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMotivo(): string
    {
        return $this->motivo;
    }

    public function setMotivo(string $motivo): static
    {
        $this->motivo = $motivo;

        return $this;
    }

    public function getDataPreferida(): ?\DateTimeImmutable
    {
        return $this->dataPreferida;
    }

    public function setDataPreferida(?\DateTimeImmutable $dataPreferida): static
    {
        $this->dataPreferida = $dataPreferida;

        return $this;
    }

    public function getPeriodo(): string
    {
        return $this->periodo;
    }

    public function setPeriodo(string $periodo): static
    {
        $this->periodo = $periodo;

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
}
