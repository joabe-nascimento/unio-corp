<?php

namespace App\Twig;

use App\Entity\PosOperatorioPaciente;
use App\PosOperatorio\PosOperatorioDisplay;
use App\PosOperatorio\PosOperatorioModuleCatalog;
use App\Support\BrPersonFormat;
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
            new TwigFilter('br_phone', [$this, 'formatPhone']),
            new TwigFilter('br_initials', [$this, 'initials']),
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

    public function formatPhone(?string $value): string
    {
        return BrPersonFormat::formatPhone($value);
    }

    public function initials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        $first = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1));
        $last = count($parts) > 1
            ? mb_strtoupper(mb_substr($parts[array_key_last($parts)], 0, 1))
            : '';

        return $first.$last;
    }
}
