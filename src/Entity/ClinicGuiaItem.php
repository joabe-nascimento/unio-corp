<?php

namespace App\Entity;

use App\Repository\ClinicGuiaItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicGuiaItemRepository::class)]
#[ORM\Table(name: 'clinic_guia_item')]
class ClinicGuiaItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClinicGuiaTiss::class, inversedBy: 'itens')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClinicGuiaTiss $guia = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codigoTuss = null;

    #[ORM\Column(length: 255)]
    private string $descricao = '';

    #[ORM\Column]
    private int $quantidade = 1;

    #[ORM\Column(nullable: true)]
    private ?int $valorCentavos = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGuia(): ?ClinicGuiaTiss
    {
        return $this->guia;
    }

    public function setGuia(?ClinicGuiaTiss $guia): static
    {
        $this->guia = $guia;

        return $this;
    }

    public function getCodigoTuss(): ?string
    {
        return $this->codigoTuss;
    }

    public function setCodigoTuss(?string $codigoTuss): static
    {
        $this->codigoTuss = $codigoTuss;

        return $this;
    }

    public function getDescricao(): string
    {
        return $this->descricao;
    }

    public function setDescricao(string $descricao): static
    {
        $this->descricao = $descricao;

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

    public function getValorCentavos(): ?int
    {
        return $this->valorCentavos;
    }

    public function setValorCentavos(?int $valorCentavos): static
    {
        $this->valorCentavos = $valorCentavos;

        return $this;
    }
}
