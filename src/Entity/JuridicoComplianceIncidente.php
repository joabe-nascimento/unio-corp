<?php

namespace App\Entity;

use App\Repository\JuridicoComplianceIncidenteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoComplianceIncidenteRepository::class)]
#[ORM\Table(name: 'juridico_compliance_incidente')]
class JuridicoComplianceIncidente
{
    public const STATUS_ABERTO = 'aberto';
    public const STATUS_EM_ANALISE = 'em_analise';
    public const STATUS_RESOLVIDO = 'resolvido';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(length: 40)]
    private string $categoria = 'lgpd';

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_ABERTO;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvidoEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }
    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): static { $this->descricao = $descricao; return $this; }
    public function getCategoria(): string { return $this->categoria; }
    public function setCategoria(string $categoria): static { $this->categoria = $categoria; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getResolvidoEm(): ?\DateTimeImmutable { return $this->resolvidoEm; }
    public function setResolvidoEm(?\DateTimeImmutable $resolvidoEm): static { $this->resolvidoEm = $resolvidoEm; return $this; }
}
