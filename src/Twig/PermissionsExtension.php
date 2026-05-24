<?php

namespace App\Twig;

use App\Service\PermissionMockService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PermissionsExtension extends AbstractExtension
{
    public function __construct(
        private PermissionMockService $permissions,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('permissions_scope', [$this, 'getScopeData']),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getScopeData(string $scope): ?array
    {
        return $this->permissions->getScopeData($scope);
    }
}
