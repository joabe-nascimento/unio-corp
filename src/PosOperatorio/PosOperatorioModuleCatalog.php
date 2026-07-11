<?php

namespace App\PosOperatorio;

/**
 * Catálogo de módulos da UNIO SAÚDE — sidebar e navegação.
 */
final class PosOperatorioModuleCatalog
{
    public const GROUP_CLINICA = 'clinica';
    public const GROUP_MONITORAMENTO = 'monitoramento';
    public const GROUP_PACIENTE = 'paciente';

    /** @return list<array{key: string, label: string, icon: string, storage_key: string}> */
    public static function sidebarGroups(): array
    {
        return [
            [
                'key' => self::GROUP_CLINICA,
                'label' => 'Clínica',
                'icon' => 'fa-user-doctor',
                'storage_key' => 'pos-op-clinica-sidebar-collapsed',
            ],
            [
                'key' => self::GROUP_MONITORAMENTO,
                'label' => 'Monitoramento',
                'icon' => 'fa-heart-pulse',
                'storage_key' => 'pos-op-monitoramento-sidebar-collapsed',
            ],
            [
                'key' => self::GROUP_PACIENTE,
                'label' => 'Paciente',
                'icon' => 'fa-mobile-screen',
                'storage_key' => 'pos-op-paciente-sidebar-collapsed',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            [
                'id' => 'pacientes',
                'grant' => 'pacientes',
                'route' => 'app_pos_operatorio_pacientes',
                'route_prefix' => 'app_pos_operatorio_paciente',
                'title' => 'Pacientes',
                'short' => 'Pacientes',
                'group' => self::GROUP_CLINICA,
            ],
            [
                'id' => 'protocolos',
                'grant' => 'protocolos',
                'route' => 'app_pos_operatorio_protocolos',
                'route_prefix' => 'app_pos_operatorio_protocolo',
                'title' => 'Protocolos',
                'short' => 'Protocolos',
                'group' => self::GROUP_CLINICA,
            ],
            [
                'id' => 'questionarios',
                'grant' => 'questionarios',
                'route' => 'app_pos_operatorio_questionarios',
                'route_prefix' => 'app_pos_operatorio_questionario',
                'title' => 'Questionários',
                'short' => 'Questionários',
                'group' => self::GROUP_CLINICA,
            ],
            [
                'id' => 'alertas',
                'grant' => 'alertas',
                'route' => 'app_pos_operatorio_alertas',
                'route_prefix' => 'app_pos_operatorio_alerta',
                'title' => 'Fila de alertas',
                'short' => 'Fila de alertas',
                'group' => self::GROUP_MONITORAMENTO,
            ],
            [
                'id' => 'sala_critica',
                'grant' => 'alertas',
                'route' => 'app_pos_operatorio_sala_critica',
                'route_prefix' => 'app_pos_operatorio_sala_critica',
                'title' => 'Sala crítica',
                'short' => 'Sala crítica',
                'group' => self::GROUP_MONITORAMENTO,
            ],
            [
                'id' => 'portal_paciente',
                'grant' => 'portal_paciente',
                'route' => 'app_pos_operatorio_portal',
                'route_prefix' => 'app_pos_operatorio_portal',
                'title' => 'Portal do paciente',
                'short' => 'Portal do paciente',
                'group' => self::GROUP_PACIENTE,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function forGroup(string $group): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $mod): bool => ($mod['group'] ?? '') === $group,
        ));
    }

    public static function isGroupActive(string $group, ?string $currentRoute): bool
    {
        if ($currentRoute === null || $currentRoute === '') {
            return false;
        }

        foreach (self::forGroup($group) as $mod) {
            if (self::isRouteActive($currentRoute, $mod)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $mod */
    public static function sidebarLabel(array $mod): string
    {
        return (string) ($mod['short'] ?? $mod['title'] ?? '');
    }

    /** @param array<string, mixed> $mod */
    public static function isRouteActive(?string $currentRoute, array $mod): bool
    {
        if ($currentRoute === null || $currentRoute === '') {
            return false;
        }

        $moduleRoute = (string) ($mod['route'] ?? '');
        if ($moduleRoute === 'app_pos_operatorio') {
            return $currentRoute === 'app_pos_operatorio';
        }

        $prefix = (string) ($mod['route_prefix'] ?? $moduleRoute);

        return $currentRoute === $moduleRoute || str_starts_with($currentRoute, $prefix);
    }
}
