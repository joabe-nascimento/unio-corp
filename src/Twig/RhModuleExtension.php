<?php

namespace App\Twig;

use App\Rh\RhModuleCatalog;
use App\Rh\RhProcessDisplay;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
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

    public function getFilters(): array
    {
        return [
            new TwigFilter('rh_colaborador_nome', [$this, 'colaboradorNome']),
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

    public function colaboradorNome(string $nome, ?string $email = null, ?string $empresaNome = null): string
    {
        return RhProcessDisplay::colaboradorNome($nome, $email, $empresaNome);
    }
}
