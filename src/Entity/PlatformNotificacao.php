<?php

namespace App\Entity;

use App\Repository\PlatformNotificacaoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlatformNotificacaoRepository::class)]
#[ORM\Table(name: 'platform_notificacao')]
#[ORM\Index(name: 'IDX_PLATFORM_NOTIF_USER', columns: ['empresa_id', 'user_id', 'lida', 'criado_em'])]
class PlatformNotificacao
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
    private string $modulo = 'sistema';

    #[ORM\Column(length: 48)]
    private string $tipo = '';

    #[ORM\Column(length: 180)]
    private string $titulo = '';

    #[ORM\Column(type: 'text')]
    private string $mensagem = '';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $routeName = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $routeParams = null;

    #[ORM\Column(length: 48)]
    private string $icon = 'fa-bell';

    #[ORM\Column(length: 16)]
    private string $severidade = 'info';

    #[ORM\Column(type: 'boolean')]
    private bool $lida = false;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmpresa(): Empresa { return $this->empresa; }
    public function setEmpresa(Empresa $empresa): static { $this->empresa = $empresa; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }
    public function getModulo(): string { return $this->modulo; }
    public function setModulo(string $modulo): static { $this->modulo = $modulo; return $this; }
    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): static { $this->tipo = $tipo; return $this; }
    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): static { $this->titulo = $titulo; return $this; }
    public function getMensagem(): string { return $this->mensagem; }
    public function setMensagem(string $mensagem): static { $this->mensagem = $mensagem; return $this; }
    public function getRouteName(): ?string { return $this->routeName; }
    public function setRouteName(?string $routeName): static { $this->routeName = $routeName; return $this; }
    /** @return array<string, mixed>|null */
    public function getRouteParams(): ?array { return $this->routeParams; }
    /** @param array<string, mixed>|null $routeParams */
    public function setRouteParams(?array $routeParams): static { $this->routeParams = $routeParams; return $this; }
    public function getIcon(): string { return $this->icon; }
    public function setIcon(string $icon): static { $this->icon = $icon; return $this; }
    public function getSeveridade(): string { return $this->severidade; }
    public function setSeveridade(string $severidade): static { $this->severidade = $severidade; return $this; }
    public function isLida(): bool { return $this->lida; }
    public function setLida(bool $lida): static { $this->lida = $lida; return $this; }
    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }
}
