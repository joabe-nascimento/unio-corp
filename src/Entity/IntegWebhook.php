<?php

namespace App\Entity;

use App\Repository\IntegWebhookRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegWebhookRepository::class)]
#[ORM\Table(name: 'integ_webhook')]
class IntegWebhook
{
    public const DIR_IN = 'in';
    public const DIR_OUT = 'out';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: IntegConector::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?IntegConector $conector = null;

    #[ORM\Column(length: 120)]
    private string $nome;

    #[ORM\Column(length: 8)]
    private string $direcao = self::DIR_OUT;

    #[ORM\Column(length: 80)]
    private string $evento;

    #[ORM\Column(length: 255)]
    private string $url;

    #[ORM\Column(type: 'boolean')]
    private bool $ativo = true;

    #[ORM\Column(type: 'integer')]
    private int $falhasConsecutivas = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimoDisparo = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'db_id' => $this->id,
            'nome' => $this->nome,
            'direcao' => $this->direcao,
            'evento' => $this->evento,
            'url' => $this->url,
            'ativo' => $this->ativo,
            'falhas' => $this->falhasConsecutivas,
            'ultimo_disparo' => $this->ultimoDisparo?->format('d/m/Y H:i'),
            'conector' => $this->conector?->getNome(),
            'conector_id' => $this->conector?->getId(),
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

    public function getConector(): ?IntegConector
    {
        return $this->conector;
    }

    public function setConector(?IntegConector $conector): static
    {
        $this->conector = $conector;

        return $this;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = $nome;

        return $this;
    }

    public function getDirecao(): string
    {
        return $this->direcao;
    }

    public function setDirecao(string $direcao): static
    {
        $this->direcao = $direcao;

        return $this;
    }

    public function getEvento(): string
    {
        return $this->evento;
    }

    public function setEvento(string $evento): static
    {
        $this->evento = $evento;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function isAtivo(): bool
    {
        return $this->ativo;
    }

    public function setAtivo(bool $ativo): static
    {
        $this->ativo = $ativo;

        return $this;
    }

    public function getFalhasConsecutivas(): int
    {
        return $this->falhasConsecutivas;
    }

    public function setFalhasConsecutivas(int $falhasConsecutivas): static
    {
        $this->falhasConsecutivas = $falhasConsecutivas;

        return $this;
    }

    public function getUltimoDisparo(): ?\DateTimeImmutable
    {
        return $this->ultimoDisparo;
    }

    public function setUltimoDisparo(?\DateTimeImmutable $ultimoDisparo): static
    {
        $this->ultimoDisparo = $ultimoDisparo;

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
