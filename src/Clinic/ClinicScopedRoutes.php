<?php

namespace App\Clinic;

/**
 * Rotas e caminhos exclusivos do deploy Unio Saúde / UnioClínica.
 */
final class ClinicScopedRoutes
{
    /** @var list<string> */
    public const PUBLIC_PATH_PREFIXES = [
        '/verificar',
        '/wallet',
        '/comprovante-procedimento',
        '/carteirinha-digital',
        '/carterinha-digital',
        '/guia-medico',
        '/paciente',
        '/clinica/portal',
    ];

    /** @var list<string> */
    public const ROUTE_PREFIXES = [
        'app_verificar_documento',
        'app_wallet_',
        'app_carteirinha_',
        'app_comprovante_',
        'app_guia_medico_beneficiario',
        'app_paciente_hub',
        'app_clinica_portal',
        'app_portal_patient_',
        'app_pos_operatorio_carteirinha',
        'app_pos_operatorio_comprovante',
        'app_pos_operatorio_guia_medico',
        'app_pos_operatorio_portal',
        'app_pos_operatorio_paciente_convite_portal',
        'app_medico',
        'app_marketing_modulo_show',
        'api_marketing_modulo_',
    ];

    /** IDs de /modulo/{id} e /api/modulo/{id} da landing clínica. */
    public const MARKETING_MODULE_IDS = [
        'pacientes',
        'sala-critica',
        'carteirinha-digital',
        'guia-medico',
        'alertas',
        'relatorios-lgpd',
    ];

    public static function isRestricted(?string $routeName, string $path): bool
    {
        $path = rtrim($path, '/') ?: '/';

        foreach (self::PUBLIC_PATH_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        if ($routeName !== null && $routeName !== '') {
            foreach (self::ROUTE_PREFIXES as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    return self::isMarketingRouteAllowed($routeName, $path);
                }
            }
        }

        return self::isClinicMarketingPath($path);
    }

    private static function isMarketingRouteAllowed(string $routeName, string $path): bool
    {
        if (!str_starts_with($routeName, 'app_marketing_modulo_')
            && !str_starts_with($routeName, 'api_marketing_modulo_')) {
            return true;
        }

        return self::isClinicMarketingPath($path);
    }

    private static function isClinicMarketingPath(string $path): bool
    {
        if (preg_match('#^/modulo/([^/]+)$#', $path, $matches) === 1) {
            return \in_array($matches[1], self::MARKETING_MODULE_IDS, true);
        }

        if (preg_match('#^/api/modulo/([^/]+)/#', $path, $matches) === 1) {
            return \in_array($matches[1], self::MARKETING_MODULE_IDS, true);
        }

        return false;
    }
}
