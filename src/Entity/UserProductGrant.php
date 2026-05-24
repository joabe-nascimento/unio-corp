<?php

namespace App\Entity;

use App\Repository\UserProductGrantRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Grant de perfil de um usuário em um produto dentro de um escopo (hub ou produto).
 */
#[ORM\Entity(repositoryClass: UserProductGrantRepository::class)]
#[ORM\Table(name: 'user_product_grant')]
#[ORM\UniqueConstraint(name: 'uniq_user_scope_product', fields: ['user', 'scope', 'productId'])]
class UserProductGrant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'productGrants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Ex.: hub_operacoes, product_rh */
    #[ORM\Column(length: 64)]
    private string $scope = '';

    /** Ex.: rh, funcionarios, membros */
    #[ORM\Column(length: 64)]
    private string $productId = '';

    /** Perfil assignável: MEMBRO, SUPERVISOR_EQUIPE, … GESTOR */
    #[ORM\Column(length: 32)]
    private string $perfilGrant = '';

    #[ORM\Column]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct()
    {
        $this->atualizadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): static
    {
        $this->productId = $productId;

        return $this;
    }

    public function getPerfilGrant(): string
    {
        return $this->perfilGrant;
    }

    public function setPerfilGrant(string $perfilGrant): static
    {
        $this->perfilGrant = $perfilGrant;
        $this->atualizadoEm = new \DateTimeImmutable();

        return $this;
    }

    public function getAtualizadoEm(): \DateTimeImmutable
    {
        return $this->atualizadoEm;
    }

    public function getGrantKey(): string
    {
        return $this->scope . ':' . $this->productId;
    }
}
