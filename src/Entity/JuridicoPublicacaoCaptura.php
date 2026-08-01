<?php

namespace App\Entity;

use App\Repository\JuridicoPublicacaoCapturaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoPublicacaoCapturaRepository::class)]
#[ORM\Table(name: 'juridico_publicacao_captura')]
#[ORM\UniqueConstraint(name: 'UNIQ_JUR_PUB_CAP_OAB', columns: ['empresa_id', 'numero_oab', 'uf_oab'])]
class JuridicoPublicacaoCaptura
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 12)]
    private string $numeroOab;

    #[ORM\Column(length: 2)]
    private string $ufOab;

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimaCapturaEm = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getNumeroOab(): string { return $this->numeroOab; }
    public function setNumeroOab(string $numeroOab): static { $this->numeroOab = $numeroOab; return $this; }
    public function getUfOab(): string { return $this->ufOab; }
    public function setUfOab(string $ufOab): static { $this->ufOab = $ufOab; return $this; }
    public function isAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }
    public function getUltimaCapturaEm(): ?\DateTimeImmutable { return $this->ultimaCapturaEm; }
    public function setUltimaCapturaEm(?\DateTimeImmutable $ultimaCapturaEm): static { $this->ultimaCapturaEm = $ultimaCapturaEm; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    public function labelOab(): string
    {
        return $this->numeroOab . '/' . strtoupper($this->ufOab);
    }
}
