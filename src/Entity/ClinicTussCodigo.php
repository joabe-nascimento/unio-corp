<?php

namespace App\Entity;

use App\Repository\ClinicTussCodigoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicTussCodigoRepository::class)]
#[ORM\Table(name: 'clinic_tuss_codigo')]
#[ORM\UniqueConstraint(name: 'UNIQ_CLINIC_TUSS_CODIGO', columns: ['codigo'])]
#[ORM\Index(columns: ['ativo'], name: 'IDX_CLINIC_TUSS_ATIVO')]
class ClinicTussCodigo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $codigo = '';

    #[ORM\Column(length: 255)]
    private string $descricao = '';

    /** Tabela TUSS (22 = procedimentos e eventos em saúde). */
    #[ORM\Column(length: 8, options: ['default' => '22'])]
    private string $tabela = '22';

    #[ORM\Column(nullable: true)]
    private ?int $valorSugeridoCentavos = null;

    #[ORM\Column]
    private bool $ativo = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = $codigo;

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

    public function getTabela(): string
    {
        return $this->tabela;
    }

    public function setTabela(string $tabela): static
    {
        $this->tabela = $tabela;

        return $this;
    }

    public function getValorSugeridoCentavos(): ?int
    {
        return $this->valorSugeridoCentavos;
    }

    public function setValorSugeridoCentavos(?int $valorSugeridoCentavos): static
    {
        $this->valorSugeridoCentavos = $valorSugeridoCentavos;

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
