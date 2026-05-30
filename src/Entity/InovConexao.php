<?php

namespace App\Entity;

use App\Repository\InovConexaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InovConexaoRepository::class)]
#[ORM\Table(name: 'inov_conexao')]
class InovConexao
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPLORE = 'explore';

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

    #[ORM\Column(length: 64)]
    private string $hub;

    #[ORM\Column(length: 32)]
    private string $icon = 'fa-share-nodes';

    #[ORM\Column(type: 'smallint')]
    private int $sinergia = 50;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_EXPLORE;

    #[ORM\Column(type: 'text')]
    private string $oportunidade;

    #[ORM\Column(length: 180)]
    private string $acao;

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

    public function getAutor(): ?User { return $this->autor; }
    public function setAutor(?User $autor): static { $this->autor = $autor; return $this; }

    public function getHub(): string { return $this->hub; }
    public function setHub(string $hub): static { $this->hub = $hub; return $this; }

    public function getIcon(): string { return $this->icon; }
    public function setIcon(string $icon): static { $this->icon = $icon; return $this; }

    public function getSinergia(): int { return $this->sinergia; }
    public function setSinergia(int $sinergia): static { $this->sinergia = max(0, min(100, $sinergia)); return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getOportunidade(): string { return $this->oportunidade; }
    public function setOportunidade(string $oportunidade): static { $this->oportunidade = $oportunidade; return $this; }

    public function getAcao(): string { return $this->acao; }
    public function setAcao(string $acao): static { $this->acao = $acao; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
    public function getAtualizadoEm(): \DateTimeImmutable { return $this->atualizadoEm; }
    public function touch(): void { $this->atualizadoEm = new \DateTimeImmutable(); }
}
