<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_PLATFORM_OWNER    = 'ROLE_PLATFORM_OWNER';
    public const ROLE_TENANT            = 'ROLE_TENANT';
    public const ROLE_GESTOR            = 'ROLE_GESTOR';
    public const ROLE_GESTOR_EQUIPE     = 'ROLE_GESTOR_EQUIPE';
    public const ROLE_SUPERVISOR        = 'ROLE_SUPERVISOR';
    public const ROLE_SUPERVISOR_EQUIPE = 'ROLE_SUPERVISOR_EQUIPE';
    public const ROLE_MEMBRO            = 'ROLE_MEMBRO';

    /** Ordem de hierarquia (maior = mais permissão) */
    private const PERFIL_NIVEL = [
        'MEMBRO'            => 1,
        'SUPERVISOR_EQUIPE' => 2,
        'SUPERVISOR'        => 3,
        'GESTOR_EQUIPE'     => 4,
        'GESTOR'            => 5,
        'TENANT'            => 7,
        'PLATFORM_OWNER'    => 9,
    ];

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

    #[ORM\Column(length: 30)]
    private string $perfil = 'MEMBRO';

    #[ORM\Column(nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\Column]
    private \DateTimeImmutable $criadoEm;

    /** @var list<string> IDs concluídos no checklist de primeiros passos (persistente por usuário). */
    #[ORM\Column(type: 'json')]
    private array $onboardingCompletedSteps = [];

    #[ORM\ManyToOne(targetEntity: Empresa::class, inversedBy: 'usuarios')]
    private ?Empresa $empresa = null;

    /** @var Collection<int, UserProductGrant> */
    #[ORM\OneToMany(targetEntity: UserProductGrant::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $productGrants;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->productGrants = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles !== [] ? $this->roles : [$this->getRolePrincipal()];
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function eraseCredentials(): void {}

    public function getNome(): ?string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }

    public function getPerfil(): string { return $this->perfil; }
    public function setPerfil(string $perfil): static { $this->perfil = $perfil; return $this; }

    public function getAvatar(): ?string { return $this->avatar; }
    public function setAvatar(?string $avatar): static { $this->avatar = $avatar; return $this; }

    public function isAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): static { $this->ativo = $ativo; return $this; }

    public function getCriadoEm(): \DateTimeImmutable { return $this->criadoEm; }

    /** @return list<string> */
    public function getOnboardingCompletedSteps(): array
    {
        return $this->onboardingCompletedSteps;
    }

    /** @param list<string> $steps */
    public function setOnboardingCompletedSteps(array $steps): static
    {
        $this->onboardingCompletedSteps = array_values(array_unique(array_values(array_filter(
            $steps,
            static fn (mixed $step): bool => \is_string($step) && $step !== '',
        ))));

        return $this;
    }

    public function isOnboardingStepComplete(string $stepId): bool
    {
        return \in_array($stepId, $this->onboardingCompletedSteps, true);
    }

    public function addOnboardingCompletedStep(string $stepId): static
    {
        if ($stepId === '' || $this->isOnboardingStepComplete($stepId)) {
            return $this;
        }

        $this->onboardingCompletedSteps[] = $stepId;

        return $this;
    }

    public function getEmpresa(): ?Empresa { return $this->empresa; }
    public function setEmpresa(?Empresa $empresa): static { $this->empresa = $empresa; return $this; }

    /** @return Collection<int, UserProductGrant> */
    public function getProductGrants(): Collection
    {
        return $this->productGrants;
    }

    public function addProductGrant(UserProductGrant $grant): static
    {
        if (!$this->productGrants->contains($grant)) {
            $this->productGrants->add($grant);
            $grant->setUser($this);
        }

        return $this;
    }

    public function removeProductGrant(UserProductGrant $grant): static
    {
        if ($this->productGrants->removeElement($grant) && $grant->getUser() === $this) {
            $grant->setUser(null);
        }

        return $this;
    }

    // ── Helpers de perfil ──────────────────────────────────────────────

    public function isPlatformOwner(): bool    { return $this->perfil === 'PLATFORM_OWNER'; }
    public function isTenant(): bool           { return $this->perfil === 'TENANT'; }

    /** Tenant operacional ou dono pessoal da plataforma (acesso global). */
    public function hasPlatformAccess(): bool
    {
        return $this->isPlatformOwner() || $this->isTenant();
    }
    public function isGestor(): bool           { return in_array(self::ROLE_GESTOR, $this->getRoles()); }
    public function isGestorEquipe(): bool     { return in_array(self::ROLE_GESTOR_EQUIPE, $this->getRoles()); }
    public function isSupervisor(): bool       { return in_array(self::ROLE_SUPERVISOR, $this->getRoles()); }
    public function isSupervisorEquipe(): bool { return in_array(self::ROLE_SUPERVISOR_EQUIPE, $this->getRoles()); }
    public function isMembro(): bool           { return in_array(self::ROLE_MEMBRO, $this->getRoles()); }

    public function getNivel(): int
    {
        return self::PERFIL_NIVEL[$this->perfil] ?? 0;
    }

    public function getPerfilLabel(): string
    {
        return match ($this->perfil) {
            'PLATFORM_OWNER'    => 'Dono da plataforma',
            'TENANT'            => 'Tenant',
            'ADMIN'             => 'Gestor',
            'GESTOR'            => 'Gestor',
            'GESTOR_EQUIPE'     => 'Gestor de Equipe',
            'SUPERVISOR'        => 'Supervisor Geral',
            'SUPERVISOR_EQUIPE' => 'Supervisor de Equipe',
            'MEMBRO'            => 'Membro',
            default             => $this->perfil,
        };
    }

    /** CSS class para o badge de perfil */
    public function getPerfilClass(): string
    {
        return match ($this->perfil) {
            'PLATFORM_OWNER'    => 'platform-owner',
            'TENANT'            => 'tenant',
            'ADMIN'             => 'gestor',
            'GESTOR'            => 'gestor',
            'GESTOR_EQUIPE'     => 'gestor-equipe',
            'SUPERVISOR'        => 'supervisor',
            'SUPERVISOR_EQUIPE' => 'supervisor-equipe',
            'MEMBRO'            => 'membro',
            default             => 'default',
        };
    }

    /** Role Symfony correspondente ao perfil */
    public function getRolePrincipal(): string
    {
        return match ($this->perfil) {
            'PLATFORM_OWNER'    => self::ROLE_PLATFORM_OWNER,
            'TENANT'            => self::ROLE_TENANT,
            'ADMIN'             => self::ROLE_GESTOR,
            'GESTOR'            => self::ROLE_GESTOR,
            'GESTOR_EQUIPE'     => self::ROLE_GESTOR_EQUIPE,
            'SUPERVISOR'        => self::ROLE_SUPERVISOR,
            'SUPERVISOR_EQUIPE' => self::ROLE_SUPERVISOR_EQUIPE,
            'MEMBRO'            => self::ROLE_MEMBRO,
            default             => 'ROLE_USER',
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
