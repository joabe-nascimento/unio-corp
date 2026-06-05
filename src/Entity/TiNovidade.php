<?php

namespace App\Entity;

use App\Repository\TiNovidadeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiNovidadeRepository::class)]
#[ORM\Table(name: 'ti_novidade')]
class TiNovidade
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
    private string $icon = 'fa-bullhorn';

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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'db_id' => $this->id,
            'title' => $this->titulo,
            'titulo' => $this->titulo,
            'summary' => $this->resumo,
            'resumo' => $this->resumo,
            'icon' => $this->icon,
            'badge' => $this->badge ?? 'Comunicado',
            'variant' => $this->variant,
            'date' => $this->publicadoEm->format('d/m/Y'),
            'publicado_em' => $this->publicadoEm->format('Y-m-d'),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getAutor(): ?User
    {
        return $this->autor;
    }

    public function setAutor(?User $autor): static
    {
        $this->autor = $autor;

        return $this;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): static
    {
        $this->titulo = $titulo;

        return $this;
    }

    public function getResumo(): string
    {
        return $this->resumo;
    }

    public function setResumo(string $resumo): static
    {
        $this->resumo = $resumo;

        return $this;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function setBadge(?string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function getVariant(): string
    {
        return $this->variant;
    }

    public function setVariant(string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getPublicadoEm(): \DateTimeImmutable
    {
        return $this->publicadoEm;
    }

    public function setPublicadoEm(\DateTimeImmutable $publicadoEm): static
    {
        $this->publicadoEm = $publicadoEm;

        return $this;
    }
}
