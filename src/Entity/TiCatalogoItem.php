<?php

namespace App\Entity;

use App\Repository\TiCatalogoItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiCatalogoItemRepository::class)]
#[ORM\Table(name: 'ti_catalogo_item')]
class TiCatalogoItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 64)]
    private string $itemId;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(length: 32)]
    private string $categoria = 'sistema';

    #[ORM\Column(length: 4)]
    private string $prioridadePadrao = 'P3';

    #[ORM\Column(type: 'smallint')]
    private int $slaHoras = 8;

    #[ORM\Column(type: 'boolean')]
    private bool $ativo = true;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->itemId,
            'titulo' => $this->titulo,
            'title' => $this->titulo,
            'descricao' => $this->descricao,
            'desc' => $this->descricao ?? '',
            'icon' => 'fa-cog',
            'categoria' => $this->categoria,
            'category' => $this->categoria,
            'prioridade_padrao' => $this->prioridadePadrao,
            'priority' => $this->prioridadePadrao,
            'sla_horas' => $this->slaHoras,
            'sla' => $this->slaHoras . ' h',
            'ativo' => $this->ativo,
            'is_custom' => true,
        ];
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

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function setItemId(string $itemId): static
    {
        $this->itemId = $itemId;

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

    public function getDescricao(): ?string
    {
        return $this->descricao;
    }

    public function setDescricao(?string $descricao): static
    {
        $this->descricao = $descricao;

        return $this;
    }

    public function getCategoria(): string
    {
        return $this->categoria;
    }

    public function setCategoria(string $categoria): static
    {
        $this->categoria = $categoria;

        return $this;
    }

    public function getPrioridadePadrao(): string
    {
        return $this->prioridadePadrao;
    }

    public function setPrioridadePadrao(string $prioridadePadrao): static
    {
        $this->prioridadePadrao = $prioridadePadrao;

        return $this;
    }

    public function getSlaHoras(): int
    {
        return $this->slaHoras;
    }

    public function setSlaHoras(int $slaHoras): static
    {
        $this->slaHoras = $slaHoras;

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

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
