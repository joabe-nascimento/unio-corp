<?php

namespace App\Entity;

use App\Repository\InovImpactEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InovImpactEntryRepository::class)]
#[ORM\Table(name: 'inov_impact_entry')]
class InovImpactEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: InovIdeia::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InovIdeia $ideia = null;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(length: 32)]
    private string $estagioLabel = 'Ideia';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $valorCapturado = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $roi = null;

    #[ORM\Column(length: 24)]
    private string $status = 'hypothesis';

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getIdeia(): ?InovIdeia { return $this->ideia; }
    public function setIdeia(?InovIdeia $ideia): static { $this->ideia = $ideia; return $this; }

    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }

    public function getEstagioLabel(): string { return $this->estagioLabel; }
    public function setEstagioLabel(string $estagioLabel): static { $this->estagioLabel = $estagioLabel; return $this; }

    public function getValorCapturado(): ?string { return $this->valorCapturado; }
    public function setValorCapturado(?string $valorCapturado): static { $this->valorCapturado = $valorCapturado; return $this; }

    public function getRoi(): ?string { return $this->roi; }
    public function setRoi(?string $roi): static { $this->roi = $roi; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
