<?php

namespace App\Entity;

use App\Repository\RhWorkflowTemplateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhWorkflowTemplateRepository::class)]
#[ORM\Table(name: 'rh_workflow_template')]
#[ORM\UniqueConstraint(name: 'UNIQ_RH_WF_EMP_COD', fields: ['empresa', 'codigo'])]
class RhWorkflowTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 48)]
    private string $codigo;

    #[ORM\Column(length: 120)]
    private string $nome;

    #[ORM\Column(length: 32)]
    private string $tipoProcesso;

    /** @var list<array{id: string, label: string, done?: bool}> */
    #[ORM\Column(type: 'json')]
    private array $checklist = [];

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getCodigo(): string { return $this->codigo; }
    public function setCodigo(string $codigo): static { $this->codigo = $codigo; return $this; }

    public function getNome(): string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }

    public function getTipoProcesso(): string { return $this->tipoProcesso; }
    public function setTipoProcesso(string $tipoProcesso): static { $this->tipoProcesso = $tipoProcesso; return $this; }

    public function getChecklist(): array { return $this->checklist; }
    public function setChecklist(array $checklist): static { $this->checklist = $checklist; return $this; }

    public function isAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
