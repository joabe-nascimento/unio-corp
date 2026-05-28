<?php

namespace App\Entity;

use App\Repository\WelcomeNewsReadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WelcomeNewsReadRepository::class)]
#[ORM\Table(name: 'welcome_news_read')]
#[ORM\UniqueConstraint(name: 'UNIQ_WELCOME_NEWS_READ', columns: ['user_id', 'article_key'])]
class WelcomeNewsRead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 120)]
    private string $articleKey;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Empresa $empresa = null;

    #[ORM\Column]
    private \DateTimeImmutable $readAt;

    public function __construct()
    {
        $this->readAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getArticleKey(): string
    {
        return $this->articleKey;
    }

    public function setArticleKey(string $articleKey): static
    {
        $this->articleKey = $articleKey;

        return $this;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getReadAt(): \DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }
}
