<?php

namespace App\Entity;

use App\Repository\JuridicoAtendimentoTemplateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoAtendimentoTemplateRepository::class)]
#[ORM\Table(name: 'juridico_atendimento_template')]
#[ORM\Index(columns: ['empresa_id'], name: 'IDX_JUR_ATEND_TPL_EMPRESA')]
class JuridicoAtendimentoTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 120)]
    private string $titulo;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $area = null;

    #[ORM\Column(type: 'text')]
    private string $corpo;

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $atualizadoEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }
    public function getArea(): ?string { return $this->area; }
    public function setArea(?string $area): static { $this->area = $area; return $this; }
    public function getCorpo(): string { return $this->corpo; }
    public function setCorpo(string $corpo): static { $this->corpo = $corpo; return $this; }
    public function isAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTimeImmutable { return $this->atualizadoEm; }

    public function touch(): static
    {
        $this->atualizadoEm = new \DateTimeImmutable();

        return $this;
    }
}
