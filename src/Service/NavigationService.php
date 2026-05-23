<?php

namespace App\Service;

use App\Entity\User;

/**
 * Define visibilidade do menu por perfil principal (não pela hierarquia Symfony).
 */
class NavigationService
{
    public function getLayout(User $user): string
    {
        return match ($user->getPerfil()) {
            'TENANT', 'ADMIN' => 'admin',
            'GESTOR', 'GESTOR_EQUIPE' => 'gestor',
            'SUPERVISOR', 'SUPERVISOR_EQUIPE' => 'supervisor',
            default => 'membro',
        };
    }

    public function isAdminLayout(User $user): bool
    {
        return \in_array($user->getPerfil(), ['ADMIN', 'TENANT'], true);
    }

    public function showHubOperacoes(User $user): bool
    {
        return \in_array($user->getPerfil(), [
            'SUPERVISOR_EQUIPE', 'SUPERVISOR', 'GESTOR_EQUIPE', 'GESTOR',
        ], true);
    }

    public function showHubTalentos(User $user): bool
    {
        return \in_array($user->getPerfil(), ['GESTOR_EQUIPE', 'GESTOR'], true);
    }

    public function showHubMaturidade(User $user): bool
    {
        return \in_array($user->getPerfil(), ['GESTOR_EQUIPE', 'GESTOR'], true);
    }

    public function showAdmin(User $user): bool
    {
        return \in_array($user->getPerfil(), ['ADMIN', 'TENANT'], true);
    }

    public function showTenantEmpresas(User $user): bool
    {
        return $user->getPerfil() === 'TENANT';
    }

    /** Rotas do hub operações (RH + Pessoas) para menu-open */
    public function isHubOperacoesActive(?string $route): bool
    {
        if (!$route) {
            return false;
        }

        return str_starts_with($route, 'app_hub_operacoes')
            || str_starts_with($route, 'app_rh')
            || str_starts_with($route, 'app_pessoas');
    }
}
