<?php

namespace App\Entity;

use App\Repository\ClinicOrcamentoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicOrcamentoRepository::class)]
#[ORM\Table(name: 'clinic_orcamento')]
#[ORM\Index(columns: ['empresa_id'], name: 'IDX_CLINIC_ORCAMENTO_EMPRESA')]
class ClinicOrcamento
{
    public const STATUS_RASCUNHO = 'rascunho';
    public const STATUS_ENVIADO = 'enviado';
    public const STATUS_APROVADO = 'aprovado';
    public const STATUS_RECUSADO = 'recusado';
    public const STATUS_CONVERTIDO = 'convertido';

    public const STATUSES = [
        self::STATUS_RASCUNHO,
        self::STATUS_ENVIADO,
        self::STATUS_APROVADO,
        self::STATUS_RECUSADO,
        self::STATUS_CONVERTIDO,
    ];

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

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $leadNome = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $leadTelefone = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $leadEmail = null;

    #[ORM\Column(length: 24, options: ['default' => 'rascunho'])]
    private string $status = self::STATUS_RASCUNHO;

    #[ORM\Column(options: ['default' => 0])]
    private int $valorCentavos = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $descontoCentavos = 0;

    /** @var list<mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $itens = [];

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validade = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacoes = null;

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

    public function getTotalCentavos(): int
    {
        return max(0, $this->valorCentavos - $this->descontoCentavos);
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

    public function getLeadNome(): ?string
    {
        return $this->leadNome;
    }

    public function setLeadNome(?string $leadNome): static
    {
        $this->leadNome = $leadNome;

        return $this;
    }

    public function getLeadTelefone(): ?string
    {
        return $this->leadTelefone;
    }

    public function setLeadTelefone(?string $leadTelefone): static
    {
        $this->leadTelefone = $leadTelefone;

        return $this;
    }

    public function getLeadEmail(): ?string
    {
        return $this->leadEmail;
    }

    public function setLeadEmail(?string $leadEmail): static
    {
        $this->leadEmail = $leadEmail;

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

    public function getValorCentavos(): int
    {
        return $this->valorCentavos;
    }

    public function setValorCentavos(int $valorCentavos): static
    {
        $this->valorCentavos = $valorCentavos;

        return $this;
    }

    public function getDescontoCentavos(): int
    {
        return $this->descontoCentavos;
    }

    public function setDescontoCentavos(int $descontoCentavos): static
    {
        $this->descontoCentavos = $descontoCentavos;

        return $this;
    }

    /** @return list<mixed> */
    public function getItens(): array
    {
        return $this->itens;
    }

    /** @param list<mixed> $itens */
    public function setItens(array $itens): static
    {
        $this->itens = $itens;

        return $this;
    }

    public function getValidade(): ?\DateTimeImmutable
    {
        return $this->validade;
    }

    public function setValidade(?\DateTimeImmutable $validade): static
    {
        $this->validade = $validade;

        return $this;
    }

    public function getObservacoes(): ?string
    {
        return $this->observacoes;
    }

    public function setObservacoes(?string $observacoes): static
    {
        $this->observacoes = $observacoes;

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
