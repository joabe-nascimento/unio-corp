<?php

namespace App\Entity;

use App\Repository\RhProvisaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhProvisaoRepository::class)]
#[ORM\Table(name: 'rh_provisao')]
#[ORM\UniqueConstraint(name: 'UNIQ_RH_PROV_EMP_REF_TIPO', fields: ['empresa', 'referencia', 'tipo'])]
class RhProvisao
{
    public const STATUS_ABERTA = 'ABERTA';
    public const STATUS_FECHADA = 'FECHADA';

    public const TIPO_FERIAS = 'FERIAS';
    public const TIPO_DECIMO = 'DECIMO_TERCEIRO';
    public const TIPO_ENCARGOS = 'ENCARGOS';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 7)]
    private string $referencia;

    #[ORM\Column(length: 32)]
    private string $tipo;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    private string $valor = '0';

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ABERTA;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getReferencia(): string { return $this->referencia; }
    public function setReferencia(string $referencia): static { $this->referencia = $referencia; return $this; }

    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }

    public function getValor(): string { return $this->valor; }
    public function setValor(string $valor): static { $this->valor = $valor; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    public function getTipoLabel(): string
    {
        return match ($this->tipo) {
            self::TIPO_FERIAS => 'Férias',
            self::TIPO_DECIMO => '13º salário',
            self::TIPO_ENCARGOS => 'Encargos',
            default => $this->tipo,
        };
    }
}
