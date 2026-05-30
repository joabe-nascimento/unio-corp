<?php

namespace App\Entity;

use App\Repository\InovDecisaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InovDecisaoRepository::class)]
#[ORM\Table(name: 'inov_decisao')]
class InovDecisao
{
    public const TIPO_KILL = 'kill';
    public const TIPO_PIVOT = 'pivot';
    public const TIPO_SCALE = 'scale';

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

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $autor = null;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(length: 16)]
    private string $tipo;

    #[ORM\Column(type: 'text')]
    private string $motivo;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $ownerNome = null;

    #[ORM\Column]
    private \DateTimeImmutable $decididoEm;

    public function __construct()
    {
        $this->decididoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getIdeia(): ?InovIdeia { return $this->ideia; }
    public function setIdeia(?InovIdeia $ideia): static { $this->ideia = $ideia; return $this; }

    public function getAutor(): ?User { return $this->autor; }
    public function setAutor(?User $autor): static { $this->autor = $autor; return $this; }

    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }

    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }

    public function getMotivo(): string { return $this->motivo; }
    public function setMotivo(string $motivo): static { $this->motivo = $motivo; return $this; }

    public function getOwnerNome(): ?string { return $this->ownerNome; }
    public function setOwnerNome(?string $ownerNome): static { $this->ownerNome = $ownerNome; return $this; }

    public function getDecididoEm(): \DateTimeImmutable { return $this->decididoEm; }
    public function setDecididoEm(\DateTimeImmutable $decididoEm): static { $this->decididoEm = $decididoEm; return $this; }
}
