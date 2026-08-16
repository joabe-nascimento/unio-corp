<?php

namespace App\Entity;

use App\Repository\JuridicoWebhookEntregaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JuridicoWebhookEntregaRepository::class)]
#[ORM\Table(name: 'juridico_webhook_entrega')]
class JuridicoWebhookEntrega
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JuridicoWebhookSubscription::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JuridicoWebhookSubscription $subscription;

    #[ORM\Column(length: 64)]
    private string $evento;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(nullable: true)]
    private ?int $statusHttp = null;

    #[ORM\Column]
    private bool $sucesso = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resposta = null;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSubscription(): JuridicoWebhookSubscription { return $this->subscription; }
    public function setSubscription(JuridicoWebhookSubscription $subscription): static { $this->subscription = $subscription; return $this; }
    public function getEvento(): string { return $this->evento; }
    public function setEvento(string $evento): static { $this->evento = $evento; return $this; }
    /** @return array<string, mixed>|null */
    public function getPayload(): ?array { return $this->payload; }
    /** @param array<string, mixed>|null $payload */
    public function setPayload(?array $payload): static { $this->payload = $payload; return $this; }
    public function getStatusHttp(): ?int { return $this->statusHttp; }
    public function setStatusHttp(?int $statusHttp): static { $this->statusHttp = $statusHttp; return $this; }
    public function isSucesso(): bool { return $this->sucesso; }
    public function setSucesso(bool $sucesso): static { $this->sucesso = $sucesso; return $this; }
    public function getResposta(): ?string { return $this->resposta; }
    public function setResposta(?string $resposta): static { $this->resposta = $resposta; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
