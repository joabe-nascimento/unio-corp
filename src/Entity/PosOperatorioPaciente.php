<?php

namespace App\Entity;

use App\Repository\PosOperatorioPacienteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paciente em acompanhamento pós-operatório (domínio clínico — migration pendente).
 */
#[ORM\Entity(repositoryClass: PosOperatorioPacienteRepository::class)]
#[ORM\Table(name: 'pos_operatorio_paciente')]
#[ORM\Index(name: 'IDX_POSOP_PAC_EMPRESA_STATUS', columns: ['empresa_id', 'status'])]
class PosOperatorioPaciente
{
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_ALERTA = 'alerta';
    public const STATUS_ENCERRADO = 'encerrado';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: PosOperatorioProtocolo::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PosOperatorioProtocolo $protocolo = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $medicoResponsavel = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $portalUser = null;

    #[ORM\Column(length: 16)]
    private string $codigo = '';

    #[ORM\Column(length: 160)]
    private string $nome = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $procedimento = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataCirurgia = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_ATIVO;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telefoneContato = null;

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

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = $codigo;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }
}
