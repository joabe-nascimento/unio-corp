<?php

namespace App\Entity;

use App\Repository\ClinicCheckinRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicCheckinRepository::class)]
#[ORM\Table(name: 'clinic_checkin')]
class ClinicCheckin
{
    public const METODO_QR = 'qr';
    public const METODO_CPF = 'cpf';
    public const METODO_CODIGO = 'codigo';

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

    #[ORM\ManyToOne(targetEntity: ClinicUnidade::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicUnidade $unidade = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $recepcionista = null;

    #[ORM\Column(length: 16)]
    private string $metodo = self::METODO_QR;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $codigoUsado = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observacao = null;

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

    public function getUnidade(): ?ClinicUnidade
    {
        return $this->unidade;
    }

    public function setUnidade(?ClinicUnidade $unidade): static
    {
        $this->unidade = $unidade;

        return $this;
    }

    public function getRecepcionista(): ?User
    {
        return $this->recepcionista;
    }

    public function setRecepcionista(?User $recepcionista): static
    {
        $this->recepcionista = $recepcionista;

        return $this;
    }

    public function getMetodo(): string
    {
        return $this->metodo;
    }

    public function setMetodo(string $metodo): static
    {
        $this->metodo = $metodo;

        return $this;
    }

    public function getCodigoUsado(): ?string
    {
        return $this->codigoUsado;
    }

    public function setCodigoUsado(?string $codigoUsado): static
    {
        $this->codigoUsado = $codigoUsado;

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

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
