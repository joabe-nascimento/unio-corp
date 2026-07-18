<?php

namespace App\Entity;

use App\Repository\ClinicAssinaturaDocumentoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicAssinaturaDocumentoRepository::class)]
#[ORM\Table(name: 'clinic_assinatura_documento')]
class ClinicAssinaturaDocumento
{
    public const STATUS_PENDENTE_MEDICO = 'pendente_medico';
    public const STATUS_PENDENTE_PACIENTE = 'pendente_paciente';
    public const STATUS_NA_FILA = 'na_fila';
    public const STATUS_CONCLUIDA = 'concluida';
    public const STATUS_CANCELADA = 'cancelada';

    public const TIPO_CONSENTIMENTO = 'consentimento';
    public const TIPO_CONTRATO = 'contrato';
    public const TIPO_ANAMNESE = 'anamnese';
    public const TIPO_ALTA = 'alta';

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

    #[ORM\Column(length: 180)]
    private string $titulo = '';

    #[ORM\Column(length: 32)]
    private string $tipo = self::TIPO_CONSENTIMENTO;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_PENDENTE_PACIENTE;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $assinadoEm = null;

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

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): static
    {
        $this->titulo = $titulo;

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

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getAssinadoEm(): ?\DateTimeImmutable
    {
        return $this->assinadoEm;
    }

    public function setAssinadoEm(?\DateTimeImmutable $assinadoEm): static
    {
        $this->assinadoEm = $assinadoEm;

        return $this;
    }
}
