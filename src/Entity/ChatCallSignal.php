<?php

namespace App\Entity;

use App\Repository\ChatCallSignalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatCallSignalRepository::class)]
#[ORM\Table(name: 'chat_call_signal')]
class ChatCallSignal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ChatConversation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ChatConversation $conversation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $fromUser;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $toUser = null;

    #[ORM\Column(length: 24)]
    private string $signalType;

    #[ORM\Column(type: 'text')]
    private string $payload = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getConversation(): ChatConversation { return $this->conversation; }
    public function setConversation(ChatConversation $conversation): static { $this->conversation = $conversation; return $this; }

    public function getFromUser(): User { return $this->fromUser; }
    public function setFromUser(User $fromUser): static { $this->fromUser = $fromUser; return $this; }

    public function getToUser(): ?User { return $this->toUser; }
    public function setToUser(?User $toUser): static { $this->toUser = $toUser; return $this; }

    public function getSignalType(): string { return $this->signalType; }
    public function setSignalType(string $signalType): static { $this->signalType = $signalType; return $this; }

    public function getPayload(): string { return $this->payload; }
    public function setPayload(string $payload): static { $this->payload = $payload; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
