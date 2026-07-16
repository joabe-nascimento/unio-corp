<?php

namespace App\Entity;

use App\Repository\ClinicEstoqueItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicEstoqueItemRepository::class)]
#[ORM\Table(name: 'clinic_estoque_item')]
#[ORM\Index(columns: ['empresa_id'], name: 'IDX_CLINIC_ESTOQUE_ITEM_EMPRESA')]
class ClinicEstoqueItem
{
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

    #[ORM\Column(length: 160)]
    private string $nome = '';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(length: 16, options: ['default' => 'un'])]
    private string $unidadeMedida = 'un';

    #[ORM\Column(options: ['default' => 0])]
    private int $quantidade = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $minimo = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $ativo = true;

    public function isAbaixoMinimo(): bool
    {
        return $this->quantidade < $this->minimo;
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

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function getUnidadeMedida(): string
    {
        return $this->unidadeMedida;
    }

    public function setUnidadeMedida(string $unidadeMedida): static
    {
        $this->unidadeMedida = $unidadeMedida;

        return $this;
    }

    public function getQuantidade(): int
    {
        return $this->quantidade;
    }

    public function setQuantidade(int $quantidade): static
    {
        $this->quantidade = $quantidade;

        return $this;
    }

    public function getMinimo(): int
    {
        return $this->minimo;
    }

    public function setMinimo(int $minimo): static
    {
        $this->minimo = $minimo;

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
