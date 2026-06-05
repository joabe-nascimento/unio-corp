<?php

namespace App\Entity;

use App\Repository\TiManutencaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiManutencaoRepository::class)]
#[ORM\Table(name: 'ti_manutencao')]
class TiManutencao
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DONE = 'done';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(length: 120)]
    private string $janela;

    #[ORM\Column(length: 180)]
    private string $impacto;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_SCHEDULED;

    #[ORM\Column(length: 64)]
    private string $owner;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $servicosAfetados = [];

    #[ORM\Column(type: 'boolean')]
    private bool $aprovada = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $aprovadaEm = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $aprovadaPor = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'db_id' => $this->id,
            'title' => $this->titulo,
            'titulo' => $this->titulo,
            'window' => $this->janela,
            'janela' => $this->janela,
            'impact' => $this->impacto,
            'impacto' => $this->impacto,
            'status' => $this->status,
            'owner' => $this->owner,
            'servicos_afetados' => $this->servicosAfetados,
            'aprovada' => $this->aprovada,
            'aprovada_em' => $this->aprovadaEm?->format('d/m/Y H:i'),
            'aprovada_por' => $this->aprovadaPor,
        ];
    }

    /** @return list<string> */
    public function getServicosAfetados(): array
    {
        return $this->servicosAfetados;
    }

    /** @param list<string> $servicosAfetados */
    public function setServicosAfetados(array $servicosAfetados): static
    {
        $this->servicosAfetados = $servicosAfetados;

        return $this;
    }

    public function isAprovada(): bool
    {
        return $this->aprovada;
    }

    public function setAprovada(bool $aprovada): static
    {
        $this->aprovada = $aprovada;

        return $this;
    }

    public function getAprovadaEm(): ?\DateTimeImmutable
    {
        return $this->aprovadaEm;
    }

    public function setAprovadaEm(?\DateTimeImmutable $aprovadaEm): static
    {
        $this->aprovadaEm = $aprovadaEm;

        return $this;
    }

    public function getAprovadaPor(): ?string
    {
        return $this->aprovadaPor;
    }

    public function setAprovadaPor(?string $aprovadaPor): static
    {
        $this->aprovadaPor = $aprovadaPor;

        return $this;
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

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): static
    {
        $this->titulo = $titulo;

        return $this;
    }

    public function getJanela(): string
    {
        return $this->janela;
    }

    public function setJanela(string $janela): static
    {
        $this->janela = $janela;

        return $this;
    }

    public function getImpacto(): string
    {
        return $this->impacto;
    }

    public function setImpacto(string $impacto): static
    {
        $this->impacto = $impacto;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function setOwner(string $owner): static
    {
        $this->owner = $owner;

        return $this;
    }
}
