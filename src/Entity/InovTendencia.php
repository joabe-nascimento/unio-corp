<?php

namespace App\Entity;

use App\Repository\InovTendenciaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InovTendenciaRepository::class)]
#[ORM\Table(name: 'inov_tendencia')]
class InovTendencia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 120)]
    private string $label;

    #[ORM\Column(type: 'smallint')]
    private int $valor = 50;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $hint = null;

    #[ORM\Column(length: 24)]
    private string $status = 'stable';

    #[ORM\Column(type: 'smallint')]
    private int $ordem = 0;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->atualizadoEm = $this->criadoEm;
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): static { $this->label = $label; return $this; }

    public function getValor(): int { return $this->valor; }
    public function setValor(int $valor): static { $this->valor = max(0, min(100, $valor)); return $this; }

    public function getHint(): ?string { return $this->hint; }
    public function setHint(?string $hint): static { $this->hint = $hint; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getOrdem(): int { return $this->ordem; }
    public function setOrdem(int $ordem): static { $this->ordem = $ordem; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): void { $this->atualizadoEm = new \DateTimeImmutable(); }
}
