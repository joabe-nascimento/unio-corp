<?php

namespace App\Entity;

use App\Repository\ClinicPacoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicPacoteRepository::class)]
#[ORM\Table(name: 'clinic_pacote')]
#[ORM\Index(columns: ['empresa_id'], name: 'IDX_CLINIC_PACOTE_EMPRESA')]
class ClinicPacote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 160)]
    private string $nome = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(nullable: true)]
    private ?int $valorCentavos = null;

    /** @var list<mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $itens = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $ativo = true;

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

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = $nome;

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

    public function getValorCentavos(): ?int
    {
        return $this->valorCentavos;
    }

    public function setValorCentavos(?int $valorCentavos): static
    {
        $this->valorCentavos = $valorCentavos;

        return $this;
    }

    /** @return list<mixed> */
    public function getItens(): array
    {
        return $this->itens;
    }

    /** @param list<mixed> $itens */
    public function setItens(array $itens): static
    {
        $this->itens = $itens;

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
