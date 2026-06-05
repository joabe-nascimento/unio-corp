<?php

namespace App\Entity;

use App\Repository\TiNotificacaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiNotificacaoRepository::class)]
#[ORM\Table(name: 'ti_notificacao')]
class TiNotificacao
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Empresa $empresa;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 32)]
    private string $tipo;

    #[ORM\Column(length: 180)]
    private string $titulo;

    #[ORM\Column(type: 'text')]
    private string $mensagem;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(type: 'boolean')]
    private bool $lida = false;

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
            'id' => $this->id,
            'type' => $this->tipo,
            'title' => $this->titulo,
            'message' => $this->mensagem,
            'link' => $this->link,
            'read' => $this->lida,
            'at' => $this->criadoEm->format('d/m H:i'),
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }
    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }
    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }
    public function getMensagem(): string { return $this->mensagem; }
    public function setMensagem(string $mensagem): static { $this->mensagem = $mensagem; return $this; }
    public function getLink(): ?string { return $this->link; }
    public function setLink(?string $link): static { $this->link = $link; return $this; }
    public function isLida(): bool { return $this->lida; }
    public function setLida(bool $lida): static { $this->lida = $lida; return $this; }
}
