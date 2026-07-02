<?php

namespace App\Entity;

use App\Repository\PosOperatorioProtocoloRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Protocolo pós-operatório por tipo de procedimento.
 */
#[ORM\Entity(repositoryClass: PosOperatorioProtocoloRepository::class)]
#[ORM\Table(name: 'pos_operatorio_protocolo')]
class PosOperatorioProtocolo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 120)]
    private string $nome = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $tipoProcedimento = null;

    #[ORM\Column(type: 'smallint')]
    private int $duracaoDias = 14;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $checklist = [];

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $perguntas = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $regrasAlerta = [];

    #[ORM\Column(type: 'boolean')]
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

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = $nome;

        return $this;
    }

    public function getTipoProcedimento(): ?string
    {
        return $this->tipoProcedimento;
    }

    public function setTipoProcedimento(?string $tipoProcedimento): static
    {
        $this->tipoProcedimento = $tipoProcedimento;

        return $this;
    }

    public function getDuracaoDias(): int
    {
        return $this->duracaoDias;
    }

    public function setDuracaoDias(int $duracaoDias): static
    {
        $this->duracaoDias = $duracaoDias;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getChecklist(): array
    {
        return $this->checklist;
    }

    /** @param list<array<string, mixed>> $checklist */
    public function setChecklist(array $checklist): static
    {
        $this->checklist = $checklist;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getPerguntas(): array
    {
        return $this->perguntas;
    }

    /** @param list<array<string, mixed>> $perguntas */
    public function setPerguntas(array $perguntas): static
    {
        $this->perguntas = $perguntas;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getRegrasAlerta(): array
    {
        return $this->regrasAlerta;
    }

    /** @param array<string, mixed> $regrasAlerta */
    public function setRegrasAlerta(array $regrasAlerta): static
    {
        $this->regrasAlerta = $regrasAlerta;

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
