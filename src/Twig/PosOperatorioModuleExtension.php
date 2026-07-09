<?php

namespace App\Twig;

use App\Entity\PosOperatorioPaciente;
use App\PosOperatorio\PosOperatorioDisplay;
use App\PosOperatorio\PosOperatorioModuleCatalog;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class PosOperatorioModuleExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('pos_op_paciente_nome', [$this, 'pacienteNome']),
            new TwigFilter('pos_op_status_label', [$this, 'statusLabel']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pos_op_module_catalog', [$this, 'catalog']),
            new TwigFunction('pos_op_sidebar_groups', [$this, 'sidebarGroups']),
            new TwigFunction('pos_op_modules_for_group', [$this, 'modulesForGroup']),
            new TwigFunction('pos_op_module_sidebar_label', [$this, 'sidebarLabel']),
            new TwigFunction('pos_op_module_route_active', [$this, 'isRouteActive']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function catalog(): array
    {
        return PosOperatorioModuleCatalog::all();
    }

    /** @return list<array{key: string, label: string, icon: string, storage_key: string}> */
    public function sidebarGroups(): array
    {
        return PosOperatorioModuleCatalog::sidebarGroups();
    }

    /** @return list<array<string, mixed>> */
    public function modulesForGroup(string $group): array
    {
        return PosOperatorioModuleCatalog::forGroup($group);
    }

    /** @param array<string, mixed> $mod */
    public function sidebarLabel(array $mod): string
    {
        return PosOperatorioModuleCatalog::sidebarLabel($mod);
    }

    /** @param array<string, mixed> $mod */
    public function isRouteActive(?string $currentRoute, array $mod): bool
    {
        return PosOperatorioModuleCatalog::isRouteActive($currentRoute, $mod);
    }

    public function pacienteNome(PosOperatorioPaciente $paciente): string
    {
        return PosOperatorioDisplay::pacienteNome($paciente);
    }

    public function statusLabel(string $status): string
    {
        return PosOperatorioDisplay::statusLabel($status);
    }
}
