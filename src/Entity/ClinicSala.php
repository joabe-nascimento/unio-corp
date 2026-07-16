<?php

namespace App\Entity;

use App\Repository\ClinicSalaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicSalaRepository::class)]
#[ORM\Table(name: 'clinic_sala')]
#[ORM\UniqueConstraint(name: 'UNIQ_CLINIC_SALA_CODIGO', columns: ['empresa_id', 'codigo'])]
#[ORM\Index(columns: ['empresa_id'], name: 'IDX_CLINIC_SALA_EMPRESA')]
class ClinicSala
{
    public const TIPO_CONSULTORIO = 'consultorio';
    public const TIPO_CENTRO_CIRURGICO = 'centro_cirurgico';
    public const TIPO_EXAME = 'exame';
    public const TIPO_OUTRO = 'outro';

    public const TIPOS = [
        self::TIPO_CONSULTORIO,
        self::TIPO_CENTRO_CIRURGICO,
        self::TIPO_EXAME,
        self::TIPO_OUTRO,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: ClinicUnidade::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ClinicUnidade $unidade = null;

    #[ORM\Column(length: 120)]
    private string $nome = '';

    #[ORM\Column(length: 16)]
    private string $codigo = '';

    #[ORM\Column(length: 24, options: ['default' => 'consultorio'])]
    private string $tipo = self::TIPO_CONSULTORIO;

    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private int $capacidade = 1;

    #[ORM\Column(options: ['default' => true])]
    private bool $ativo = true;

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

    public function getUnidade(): ?ClinicUnidade
    {
        return $this->unidade;
    }

    public function setUnidade(?ClinicUnidade $unidade): static
    {
        $this->unidade = $unidade;

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

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = strtoupper(trim($codigo));

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

    public function getCapacidade(): int
    {
        return $this->capacidade;
    }

    public function setCapacidade(int $capacidade): static
    {
        $this->capacidade = $capacidade;

        return $this;
    }

    public function isAtivo(): bool
    {
        return $this->ativo;
    }

    public function setAtivo(bool $ativo): static
    {
        $this->ativo = $ativo;

        return $this;
    }
}
