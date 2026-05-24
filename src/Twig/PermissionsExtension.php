<?php

namespace App\Twig;

use App\Entity\User;
use App\Security\ProductGrantAccess;
use App\Service\PermissionService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PermissionsExtension extends AbstractExtension
{
    public function __construct(
        private PermissionService $permissions,
        private ProductGrantAccess $grants,
        private Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('permissions_scope', [$this, 'getScopeData']),
            new TwigFunction('product_grant', [$this, 'canViewProduct']),
            new TwigFunction('grant_at_least', [$this, 'grantAtLeast']),
            new TwigFunction('can_manage_permissions', [$this, 'canManagePermissions']),
            new TwigFunction('scope_has_products', [$this, 'scopeHasProducts']),
            new TwigFunction('user_access_badge', [$this, 'getUserAccessBadge']),
        ];
    }

    /**
     * @return array{label: string, class: string, elevated: bool, global_label: string}
     */
    public function getUserAccessBadge(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return ['label' => '—', 'class' => 'default', 'elevated' => false, 'global_label' => '—'];
        }

        return $this->grants->getDisplayProfileBadge($user);
    }

    public function scopeHasProducts(string $scope): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->grants->canViewAnyProductInScope($user, $scope);
    }

    public function canManagePermissions(?string $scope = null): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->permissions->canManagePermissions($user, $scope);
    }

    public function canViewProduct(string $scope, string $product): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->grants->canViewProductForUi($user, $scope, $product);
    }

    public function grantAtLeast(string $scope, string $product, string $minProfileId): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->grants->grantAtLeast($user, $scope, $product, $minProfileId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getScopeData(string $scope): ?array
    {
        return $this->permissions->getScopeData($scope);
    }
}
