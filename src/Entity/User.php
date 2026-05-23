<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_TENANT     = 'ROLE_TENANT';
    public const ROLE_ADMIN      = 'ROLE_ADMIN';
    public const ROLE_GESTOR     = 'ROLE_GESTOR';
    public const ROLE_SUPERVISOR = 'ROLE_SUPERVISOR';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $nome = null;

    #[ORM\Column(length: 20)]
    private string $perfil = 'SUPERVISOR';

    #[ORM\Column(nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    #[ORM\ManyToOne(targetEntity: Empresa::class, inversedBy: 'usuarios')]
    private ?Empresa $empresa = null;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function eraseCredentials(): void {}

    public function getNome(): ?string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }

    public function getPerfil(): string { return $this->perfil; }
    public function setPeril(string $perfil): static { $this->perfil = $perfil; return $this; }

    public function getAvatar(): ?string { return $this->avatar; }
    public function setAvatar(?string $avatar): static { $this->avatar = $avatar; return $this; }

    public function isAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    public function getEmpresa(): ?Empresa { return $this->empresa; }
    public function setEmpresa(?Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    public function isTenant(): bool  { return in_array(self::ROLE_TENANT, $this->getRoles()); }
    public function isAdmin(): bool   { return in_array(self::ROLE_ADMIN, $this->getRoles()); }
    public function isGestor(): bool  { return in_array(self::ROLE_GESTOR, $this->getRoles()); }

    public function getPerfilLabel(): string
    {
        return match ($this->perfil) {
            'TENANT'     => 'Tenant',
            'ADMIN'      => 'Administrador',
            'GESTOR'     => 'Gestor',
            'SUPERVISOR' => 'Supervisor',
            default      => $this->perfil,
        };
    }

    public function getInitials(): string
    {
        $parts = explode(' ', $this->nome ?? '');
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        return $initials ?: 'US';
    }
}
