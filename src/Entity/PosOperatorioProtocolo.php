<?php

namespace App\Entity;

use App\Repository\PosOperatorioProtocoloRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Protocolo pós-operatório por tipo de procedimento (migration pendente).
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
}
