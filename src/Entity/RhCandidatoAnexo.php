<?php

namespace App\Entity;

use App\Repository\RhCandidatoAnexoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhCandidatoAnexoRepository::class)]
#[ORM\Table(name: 'rh_candidato_anexo')]
class RhCandidatoAnexo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RhCandidato::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RhCandidato $candidato;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 255)]
    private string $nomeOriginal;

    #[ORM\Column(length: 255)]
    private string $caminho;

    #[ORM\Column(length: 120)]
    private string $mimeType;

    #[ORM\Column]
    private int $tamanho;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCandidato(): RhCandidato { return $this->candidato; }
    public function setCandidato(RhCandidato $candidato): static { $this->candidato = $candidato; return $this; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getNomeOriginal(): string { return $this->nomeOriginal; }
    public function setNomeOriginal(string $nomeOriginal): static { $this->nomeOriginal = $nomeOriginal; return $this; }
    public function getCaminho(): string { return $this->caminho; }
    public function setCaminho(string $caminho): static { $this->caminho = $caminho; return $this; }
    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $mimeType): static { $this->mimeType = $mimeType; return $this; }
    public function getTamanho(): int { return $this->tamanho; }
    public function setTamanho(int $tamanho): static { $this->tamanho = $tamanho; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
