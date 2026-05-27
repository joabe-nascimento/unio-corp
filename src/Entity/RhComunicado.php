<?php

namespace App\Entity;

use App\Repository\RhComunicadoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RhComunicadoRepository::class)]
#[ORM\Table(name: 'rh_comunicado')]
class RhComunicado
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'autor_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $autor = null;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(type: 'text')]
    private string $corpo;

    #[ORM\Column]
    private \DateTimeImmutable $publicadoEm;

    #[ORM\Column]
    private bool $ativo = true;

    public function __construct()
    {
        $this->publicadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getAutor(): ?User { return $this->autor; }
    public function setAutor(?User $autor): static { $this->autor = $autor; return $this; }

    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }

    public function getCorpo(): string { return $this->corpo; }
    public function setCorpo(string $corpo): static { $this->corpo = $corpo; return $this; }

    public function getPublicadoEm(): \DateTimeImmutable { return $this->publicadoEm; }
    public function setPublicadoEm(\DateTimeImmutable $publicadoEm): static { $this->publicadoEm = $publicadoEm; return $this; }

    public function isAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }
}
