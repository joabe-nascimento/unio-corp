<?php

namespace App\Twig;

use App\Rh\RhModuleCatalog;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RhModuleExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('rh_module_catalog', [$this, 'catalog']),
            new TwigFunction('rh_module_sidebar_label', [$this, 'sidebarLabel']),
            new TwigFunction('rh_module_route_active', [$this, 'isRouteActive']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function catalog(): array
    {
        return RhModuleCatalog::all();
    }

    /** @param array<string, mixed> $mod */
    public function sidebarLabel(array $mod): string
    {
        return RhModuleCatalog::sidebarLabel($mod);
    }

    public function isRouteActive(?string $currentRoute, string $moduleRoute): bool
    {
        return RhModuleCatalog::isRouteActive($currentRoute, $moduleRoute);
    }
}
