<?php

namespace App\Entity;

use App\Repository\PosOperatorioPacienteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paciente em acompanhamento pós-operatório.
 */
#[ORM\Entity(repositoryClass: PosOperatorioPacienteRepository::class)]
#[ORM\Table(name: 'pos_operatorio_paciente')]
#[ORM\Index(name: 'IDX_POSOP_PAC_EMPRESA_STATUS', columns: ['empresa_id', 'status'])]
#[ORM\UniqueConstraint(name: 'UNIQ_POSOP_PAC_CODIGO', columns: ['empresa_id', 'codigo'])]
class PosOperatorioPaciente
{
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_ALERTA = 'alerta';
    public const STATUS_ENCERRADO = 'encerrado';
    public const STATUS_PENDENTE = 'pendente';

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

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $consentimentoLgpdEm = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    /** @var Collection<int, PosOperatorioQuestionarioResposta> */
    #[ORM\OneToMany(mappedBy: 'paciente', targetEntity: PosOperatorioQuestionarioResposta::class, cascade: ['persist'])]
    #[ORM\OrderBy(['respondidoEm' => 'DESC'])]
    private Collection $questionarios;

    /** @var Collection<int, PosOperatorioAlerta> */
    #[ORM\OneToMany(mappedBy: 'paciente', targetEntity: PosOperatorioAlerta::class, cascade: ['persist'])]
    #[ORM\OrderBy(['criadoEm' => 'DESC'])]
    private Collection $alertas;

    /** @var Collection<int, PosOperatorioEvento> */
    #[ORM\OneToMany(mappedBy: 'paciente', targetEntity: PosOperatorioEvento::class, cascade: ['persist'])]
    #[ORM\OrderBy(['criadoEm' => 'DESC'])]
    private Collection $eventos;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->questionarios = new ArrayCollection();
        $this->alertas = new ArrayCollection();
        $this->eventos = new ArrayCollection();
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

    public function getProtocolo(): ?PosOperatorioProtocolo
    {
        return $this->protocolo;
    }

    public function setProtocolo(?PosOperatorioProtocolo $protocolo): static
    {
        $this->protocolo = $protocolo;

        return $this;
    }

    public function getMedicoResponsavel(): ?User
    {
        return $this->medicoResponsavel;
    }

    public function setMedicoResponsavel(?User $medicoResponsavel): static
    {
        $this->medicoResponsavel = $medicoResponsavel;

        return $this;
    }

    public function getPortalUser(): ?User
    {
        return $this->portalUser;
    }

    public function setPortalUser(?User $portalUser): static
    {
        $this->portalUser = $portalUser;

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

    public function getProcedimento(): ?string
    {
        return $this->procedimento;
    }

    public function setProcedimento(?string $procedimento): static
    {
        $this->procedimento = $procedimento;

        return $this;
    }

    public function getDataCirurgia(): ?\DateTimeImmutable
    {
        return $this->dataCirurgia;
    }

    public function setDataCirurgia(?\DateTimeImmutable $dataCirurgia): static
    {
        $this->dataCirurgia = $dataCirurgia;

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

    public function getTelefoneContato(): ?string
    {
        return $this->telefoneContato;
    }

    public function setTelefoneContato(?string $telefoneContato): static
    {
        $this->telefoneContato = $telefoneContato;

        return $this;
    }

    public function getConsentimentoLgpdEm(): ?\DateTimeImmutable
    {
        return $this->consentimentoLgpdEm;
    }

    public function setConsentimentoLgpdEm(?\DateTimeImmutable $consentimentoLgpdEm): static
    {
        $this->consentimentoLgpdEm = $consentimentoLgpdEm;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    /** @return Collection<int, PosOperatorioQuestionarioResposta> */
    public function getQuestionarios(): Collection
    {
        return $this->questionarios;
    }

    /** @return Collection<int, PosOperatorioAlerta> */
    public function getAlertas(): Collection
    {
        return $this->alertas;
    }

    /** @return Collection<int, PosOperatorioEvento> */
    public function getEventos(): Collection
    {
        return $this->eventos;
    }

    public function getDiaPosOperatorio(?\DateTimeImmutable $ref = null): ?int
    {
        if (!$this->dataCirurgia) {
            return null;
        }
        $ref ??= new \DateTimeImmutable('today');

        return (int) $this->dataCirurgia->diff($ref)->days;
    }

    public function getUltimaResposta(): ?PosOperatorioQuestionarioResposta
    {
        $first = $this->questionarios->first();

        return $first instanceof PosOperatorioQuestionarioResposta ? $first : null;
    }
}
