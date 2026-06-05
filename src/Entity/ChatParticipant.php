<?php

namespace App\Entity;

use App\Repository\ChatParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatParticipantRepository::class)]
#[ORM\Table(name: 'chat_participant')]
#[ORM\UniqueConstraint(name: 'uniq_chat_participant', columns: ['conversation_id', 'user_id'])]
class ChatParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ChatConversation::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ChatConversation $conversation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastReadAt = null;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getConversation(): ChatConversation { return $this->conversation; }
    public function setConversation(ChatConversation $conversation): static { $this->conversation = $conversation; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getJoinedAt(): \DateTimeImmutable { return $this->joinedAt; }

    public function getLastReadAt(): ?\DateTimeImmutable { return $this->lastReadAt; }
    public function setLastReadAt(?\DateTimeImmutable $lastReadAt): static { $this->lastReadAt = $lastReadAt; return $this; }
}
