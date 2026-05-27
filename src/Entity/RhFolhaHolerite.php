<?php

namespace App\Entity;

use App\Repository\RhFolhaHoleriteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhFolhaHoleriteRepository::class)]
#[ORM\Table(name: 'rh_folha_holerite')]
#[ORM\UniqueConstraint(name: 'UNIQ_RH_HOL_COMP_FUNC', fields: ['competencia', 'funcionario'])]
class RhFolhaHolerite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RhFolhaCompetencia::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RhFolhaCompetencia $competencia;

    #[ORM\ManyToOne(targetEntity: Funcionario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Funcionario $funcionario;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $salarioBruto = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $inss = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $irrf = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $fgts = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $liquido = '0';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pdfPath = null;

    #[ORM\Column]
    private \DateTimeImmutable $geradoEm;

    public function __construct()
    {
        $this->geradoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCompetencia(): RhFolhaCompetencia { return $this->competencia; }
    public function setCompetencia(RhFolhaCompetencia $competencia): static { $this->competencia = $competencia; return $this; }

    public function getFuncionario(): Funcionario { return $this->funcionario; }
    public function setFuncionario(Funcionario $funcionario): static { $this->funcionario = $funcionario; return $this; }

    public function getSalarioBruto(): string { return $this->salarioBruto; }
    public function setSalarioBruto(string $salarioBruto): static { $this->salarioBruto = $salarioBruto; return $this; }

    public function getInss(): string { return $this->inss; }
    public function setInss(string $inss): static { $this->inss = $inss; return $this; }

    public function getIrrf(): string { return $this->irrf; }
    public function setIrrf(string $irrf): static { $this->irrf = $irrf; return $this; }

    public function getFgts(): string { return $this->fgts; }
    public function setFgts(string $fgts): static { $this->fgts = $fgts; return $this; }

    public function getLiquido(): string { return $this->liquido; }
    public function setLiquido(string $liquido): static { $this->liquido = $liquido; return $this; }

    public function getPdfPath(): ?string { return $this->pdfPath; }
    public function setPdfPath(?string $pdfPath): static { $this->pdfPath = $pdfPath; return $this; }

    public function getGeradoEm(): \DateTimeImmutable { return $this->geradoEm; }
}
