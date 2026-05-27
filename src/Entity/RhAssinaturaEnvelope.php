<?php

namespace App\Entity;

use App\Repository\RhAssinaturaEnvelopeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhAssinaturaEnvelopeRepository::class)]
#[ORM\Table(name: 'rh_assinatura_envelope')]
class RhAssinaturaEnvelope
{
    public const STATUS_RASCUNHO = 'RASCUNHO';
    public const STATUS_PENDENTE = 'PENDENTE';
    public const STATUS_CONCLUIDO = 'CONCLUIDO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_RASCUNHO;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentoPath = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $concluidoEm = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getDocumentoPath(): ?string { return $this->documentoPath; }
    public function setDocumentoPath(?string $documentoPath): static { $this->documentoPath = $documentoPath; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    public function getConcluidoEm(): ?\DateTimeImmutable { return $this->concluidoEm; }
    public function setConcluidoEm(?\DateTimeImmutable $concluidoEm): static { $this->concluidoEm = $concluidoEm; return $this; }
}
