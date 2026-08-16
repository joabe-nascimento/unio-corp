<?php

namespace App\Entity;

use App\Repository\JuridicoWebhookSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoWebhookSubscriptionRepository::class)]
#[ORM\Table(name: 'juridico_webhook_subscription')]
class JuridicoWebhookSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 500)]
    private string $url;

    #[ORM\Column(length: 120)]
    private string $secret;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $eventos = [];

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\Column]
    private int $falhasConsecutivas = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimoEnvioEm = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->secret = bin2hex(random_bytes(16));
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getUrl(): string { return $this->url; }
    public function setUrl(string $url): static { $this->url = $url; return $this; }
    public function getSecret(): string { return $this->secret; }
    public function setSecret(string $secret): static { $this->secret = $secret; return $this; }
    /** @return list<string> */
    public function getEventos(): array { return $this->eventos; }
    /** @param list<string> $eventos */
    public function setEventos(array $eventos): static { $this->eventos = $eventos; return $this; }
    public function isAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }
    public function getFalhasConsecutivas(): int { return $this->falhasConsecutivas; }
    public function setFalhasConsecutivas(int $falhasConsecutivas): static { $this->falhasConsecutivas = $falhasConsecutivas; return $this; }
    public function getUltimoEnvioEm(): ?\DateTimeImmutable { return $this->ultimoEnvioEm; }
    public function setUltimoEnvioEm(?\DateTimeImmutable $ultimoEnvioEm): static { $this->ultimoEnvioEm = $ultimoEnvioEm; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
