<?php

namespace App\Entity;

use App\Repository\ChatMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Table(name: 'chat_message')]
class ChatMessage
{
    public const TYPE_TEXT = 'text';
    public const TYPE_VOICE = 'voice';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_IMAGE = 'image';
    public const TYPE_FILE = 'file';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ChatConversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ChatConversation $conversation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column(length: 16)]
    private string $messageType = self::TYPE_TEXT;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $voicePath = null;

    #[ORM\Column(nullable: true)]
    private ?int $voiceDurationMs = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $fileMime = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $replyTo = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getConversation(): ChatConversation { return $this->conversation; }
    public function setConversation(ChatConversation $conversation): static { $this->conversation = $conversation; return $this; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): static { $this->author = $author; return $this; }

    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $body): static { $this->body = $body; return $this; }

    public function getMessageType(): string { return $this->messageType; }
    public function setMessageType(string $messageType): static { $this->messageType = $messageType; return $this; }

    public function getVoicePath(): ?string { return $this->voicePath; }
    public function setVoicePath(?string $voicePath): static { $this->voicePath = $voicePath; return $this; }

    public function getVoiceDurationMs(): ?int { return $this->voiceDurationMs; }
    public function setVoiceDurationMs(?int $voiceDurationMs): static { $this->voiceDurationMs = $voiceDurationMs; return $this; }

    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(?string $filePath): static { $this->filePath = $filePath; return $this; }

    public function getFileName(): ?string { return $this->fileName; }
    public function setFileName(?string $fileName): static { $this->fileName = $fileName; return $this; }

    public function getFileMime(): ?string { return $this->fileMime; }
    public function setFileMime(?string $fileMime): static { $this->fileMime = $fileMime; return $this; }

    public function getReplyTo(): ?self { return $this->replyTo; }
    public function setReplyTo(?self $replyTo): static { $this->replyTo = $replyTo; return $this; }

    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static { $this->deletedAt = $deletedAt; return $this; }

    public function isDeleted(): bool { return $this->deletedAt !== null; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
