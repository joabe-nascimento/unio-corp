<?php

namespace App\Service\Clinic;

use App\Clinic\ClinicStaffRole;
use App\Entity\User;
use App\Security\ProductGrantRouteMap;

/**
 * Enforce RBAC clínico na Unio Saúde.
 * Na superfície clínica só valem RECEPCAO / ENFERMAGEM / MEDICO / COORDENACAO (+ platform).
 */
final class ClinicStaffAccess
{
    public function __construct(
        private ClinicPlatformScope $clinicScope,
    ) {
    }

    public function isActiveSurface(): bool
    {
        return $this->clinicScope->isActive();
    }

    /** Usuário operacional com um dos 4 perfis clínicos. */
    public function appliesTo(User $user): bool
    {
        if (!$this->isActiveSurface()) {
            return false;
        }

        if ($user->hasPlatformAccess()) {
            return false;
        }

        return ClinicStaffRole::isClinicStaffPerfil($user->getPerfil());
    }

    /**
     * Na clínica, todo mundo que não é platform entra na matriz clínica
     * (perfis legados GESTOR/MEMBRO etc. não passam).
     */
    public function mustEnforce(User $user): bool
    {
        return $this->isActiveSurface() && !$user->hasPlatformAccess();
    }

    public function allowsProduct(User $user, string $product): bool
    {
        if (!$this->mustEnforce($user)) {
            return true;
        }

        if (!ClinicStaffRole::isClinicStaffPerfil($user->getPerfil())) {
            return false;
        }

        return ClinicStaffRole::allowsProduct($user->getPerfil(), $product);
    }

    public function allowsFeature(User $user, string $featureId): bool
    {
        if (!$this->mustEnforce($user)) {
            return true;
        }

        if (!ClinicStaffRole::isClinicStaffPerfil($user->getPerfil())) {
            return false;
        }

        return ClinicStaffRole::allowsFeature($user->getPerfil(), $featureId);
    }

    public function allowsRoute(User $user, string $routeName): bool
    {
        if (!$this->mustEnforce($user)) {
            return true;
        }

        if (self::isClinicShellRoute($routeName)) {
            return true;
        }

        if (!ClinicStaffRole::isClinicStaffPerfil($user->getPerfil())) {
            // Perfil legado (GESTOR/MEMBRO/…) na clínica: bloqueia rotas do hub.
            if (isset(ProductGrantRouteMap::MAP[$routeName])
                && (ProductGrantRouteMap::MAP[$routeName]['scope'] ?? '') === ClinicStaffRole::SCOPE) {
                return false;
            }

            return true;
        }

        return self::routeAllowedByPerfil($user->getPerfil(), $routeName);
    }

    public static function routeAllowedByPerfil(string $perfil, string $routeName): bool
    {
        if (self::isClinicShellRoute($routeName)) {
            return true;
        }

        if (!isset(ProductGrantRouteMap::MAP[$routeName])) {
            return true;
        }

        $mapped = ProductGrantRouteMap::MAP[$routeName];
        $scope = (string) ($mapped['scope'] ?? '');

        // Coordenação opera o CRM (Núcleo Comercial) além do hub clínico.
        if ($scope === 'hub_comercial' && $perfil === ClinicStaffRole::COORDENACAO) {
            return true;
        }

        if ($scope !== ClinicStaffRole::SCOPE) {
            return false;
        }

        return ClinicStaffRole::allowsProduct($perfil, (string) $mapped['product']);
    }

    /**
     * @param list<array<string, mixed>> $features
     *
     * @return list<array<string, mixed>>
     */
    public function filterFeatures(User $user, array $features): array
    {
        if (!$this->mustEnforce($user)) {
            return $features;
        }

        if (!ClinicStaffRole::isClinicStaffPerfil($user->getPerfil())) {
            return [];
        }

        return array_values(array_filter(
            $features,
            fn (array $feature): bool => $this->allowsFeature($user, (string) ($feature['id'] ?? '')),
        ));
    }

    private static function isClinicShellRoute(string $routeName): bool
    {
        return str_starts_with($routeName, 'app_dashboard')
            || str_starts_with($routeName, 'app_pulso')
            || str_starts_with($routeName, 'app_welcome')
            || str_starts_with($routeName, 'app_workspace')
            || str_starts_with($routeName, 'app_notifications')
            || str_starts_with($routeName, 'app_security')
            || str_starts_with($routeName, 'app_logout')
            || str_starts_with($routeName, 'app_login')
            || $routeName === 'app_pos_operatorio';
    }
}
