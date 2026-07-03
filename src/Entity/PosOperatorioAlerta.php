<?php

namespace App\Entity;

use App\Repository\PosOperatorioAlertaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PosOperatorioAlertaRepository::class)]
#[ORM\Table(name: 'pos_operatorio_alerta')]
#[ORM\Index(name: 'IDX_POSOP_ALERT_EMPRESA_STATUS', columns: ['empresa_id', 'status'])]
#[ORM\Index(name: 'IDX_POSOP_ALERT_PRI', columns: ['prioridade', 'status'])]
class PosOperatorioAlerta
{
    public const STATUS_ABERTO = 'aberto';
    public const STATUS_EM_ATENDIMENTO = 'em_atendimento';
    public const STATUS_RESOLVIDO = 'resolvido';

    public const PRIORIDADES = ['P1', 'P2', 'P3', 'P4'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: PosOperatorioPaciente::class, inversedBy: 'alertas')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PosOperatorioPaciente $paciente;

    #[ORM\Column(length: 2)]
    private string $prioridade = 'P4';

    #[ORM\Column(length: 255)]
    private string $motivo = '';

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_ABERTO;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsavel = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $slaLimiteEm = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvidoEm = null;

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

    public function getPaciente(): PosOperatorioPaciente
    {
        return $this->paciente;
    }

    public function setPaciente(PosOperatorioPaciente $paciente): static
    {
        $this->paciente = $paciente;

        return $this;
    }

    public function getPrioridade(): string
    {
        return $this->prioridade;
    }

    public function setPrioridade(string $prioridade): static
    {
        $this->prioridade = $prioridade;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getResponsavel(): ?User
    {
        return $this->responsavel;
    }

    public function setResponsavel(?User $responsavel): static
    {
        $this->responsavel = $responsavel;

        return $this;
    }

    public function getSlaLimiteEm(): ?\DateTimeImmutable
    {
        return $this->slaLimiteEm;
    }

    public function setSlaLimiteEm(?\DateTimeImmutable $slaLimiteEm): static
    {
        $this->slaLimiteEm = $slaLimiteEm;

        return $this;
    }

    public function getResolvidoEm(): ?\DateTimeImmutable
    {
        return $this->resolvidoEm;
    }

    public function setResolvidoEm(?\DateTimeImmutable $resolvidoEm): static
    {
        $this->resolvidoEm = $resolvidoEm;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
