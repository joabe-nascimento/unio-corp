<?php

namespace App\Entity;

use App\Repository\RhFolhaLancamentoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhFolhaLancamentoRepository::class)]
#[ORM\Table(name: 'rh_folha_lancamento')]
class RhFolhaLancamento
{
    public const TIPO_PROVENTO = 'PROVENTO';
    public const TIPO_DESCONTO = 'DESCONTO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RhFolhaCompetencia::class, inversedBy: 'lancamentos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RhFolhaCompetencia $competencia;

    #[ORM\ManyToOne(targetEntity: Funcionario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Funcionario $funcionario;

    #[ORM\Column(length: 16)]
    private string $tipo;

    #[ORM\Column(length: 32)]
    private string $codigo;

    #[ORM\Column(length: 150)]
    private string $descricao;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $valor;

    public function getId(): ?int { return $this->id; }

    public function getCompetencia(): RhFolhaCompetencia { return $this->competencia; }
    public function setCompetencia(RhFolhaCompetencia $competencia): static { $this->competencia = $competencia; return $this; }

    public function getFuncionario(): Funcionario { return $this->funcionario; }
    public function setFuncionario(Funcionario $funcionario): static { $this->funcionario = $funcionario; return $this; }

    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }

    public function getCodigo(): string { return $this->codigo; }
    public function setCodigo(string $codigo): static { $this->codigo = $codigo; return $this; }

    public function getDescricao(): string { return $this->descricao; }
    public function setDescricao(string $descricao): static { $this->descricao = $descricao; return $this; }

    public function getValor(): string { return $this->valor; }
    public function setValor(string $valor): static { $this->valor = $valor; return $this; }

    public function getValorFloat(): float
    {
        return (float) $this->valor;
    }
}
