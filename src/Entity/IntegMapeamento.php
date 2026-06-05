<?php

namespace App\Entity;

use App\Repository\IntegMapeamentoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegMapeamentoRepository::class)]
#[ORM\Table(name: 'integ_mapeamento')]
class IntegMapeamento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: IntegConector::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private IntegConector $conector;

    #[ORM\Column(length: 120)]
    private string $nome;

    #[ORM\Column(length: 80)]
    private string $campoOrigem;

    #[ORM\Column(length: 80)]
    private string $campoDestino;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $transformacao = null;

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
            'db_id' => $this->id,
            'nome' => $this->nome,
            'conector' => $this->conector->getNome(),
            'conector_id' => $this->conector->getId(),
            'campo_origem' => $this->campoOrigem,
            'campo_destino' => $this->campoDestino,
            'transformacao' => $this->transformacao,
            'ativo' => $this->ativo,
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

    public function getConector(): IntegConector
    {
        return $this->conector;
    }

    public function setConector(IntegConector $conector): static
    {
        $this->conector = $conector;

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

    public function getCampoOrigem(): string
    {
        return $this->campoOrigem;
    }

    public function setCampoOrigem(string $campoOrigem): static
    {
        $this->campoOrigem = $campoOrigem;

        return $this;
    }

    public function getCampoDestino(): string
    {
        return $this->campoDestino;
    }

    public function setCampoDestino(string $campoDestino): static
    {
        $this->campoDestino = $campoDestino;

        return $this;
    }

    public function getTransformacao(): ?string
    {
        return $this->transformacao;
    }

    public function setTransformacao(?string $transformacao): static
    {
        $this->transformacao = $transformacao;

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
