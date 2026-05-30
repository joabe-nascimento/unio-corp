<?php

namespace App\Entity;

use App\Repository\InovNovidadeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InovNovidadeRepository::class)]
#[ORM\Table(name: 'inov_novidade')]
class InovNovidade
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $autor = null;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(type: 'text')]
    private string $resumo;

    #[ORM\Column(length: 32)]
    private string $icon = 'fa-lightbulb';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $routeName = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $badge = null;

    #[ORM\Column(length: 16)]
    private string $variant = 'info';

    #[ORM\Column]
    private \DateTimeImmutable $publicadoEm;

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

    public function getResumo(): string { return $this->resumo; }
    public function setResumo(string $resumo): static { $this->resumo = $resumo; return $this; }

    public function getIcon(): string { return $this->icon; }
    public function setIcon(string $icon): static { $this->icon = $icon; return $this; }

    public function getRouteName(): ?string { return $this->routeName; }
    public function setRouteName(?string $routeName): static { $this->routeName = $routeName; return $this; }

    public function getBadge(): ?string { return $this->badge; }
    public function setBadge(?string $badge): static { $this->badge = $badge; return $this; }

    public function getVariant(): string { return $this->variant; }
    public function setVariant(string $variant): static { $this->variant = $variant; return $this; }

    public function getPublicadoEm(): \DateTimeImmutable { return $this->publicadoEm; }
    public function setPublicadoEm(\DateTimeImmutable $publicadoEm): static { $this->publicadoEm = $publicadoEm; return $this; }
}
