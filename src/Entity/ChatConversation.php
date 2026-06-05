<?php

namespace App\Entity;

use App\Repository\ChatConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatConversationRepository::class)]
#[ORM\Table(name: 'chat_conversation')]
class ChatConversation
{
    public const TYPE_DIRECT = 'direct';
    public const TYPE_GROUP = 'group';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\Column(length: 16)]
    private string $type = self::TYPE_DIRECT;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $name = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, ChatParticipant> */
    #[ORM\OneToMany(targetEntity: ChatParticipant::class, mappedBy: 'conversation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participants;

    /** @var Collection<int, ChatMessage> */
    #[ORM\OneToMany(targetEntity: ChatMessage::class, mappedBy: 'conversation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->participants = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, ChatParticipant> */
    public function getParticipants(): Collection { return $this->participants; }

    public function addParticipant(ChatParticipant $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
            $participant->setConversation($this);
        }

        return $this;
    }

    /** @return Collection<int, ChatMessage> */
    public function getMessages(): Collection { return $this->messages; }

    public function addMessage(ChatMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }

        return $this;
    }
}
