<?php

namespace App\Service;

use App\Entity\User;

/**
 * Menu e layout por perfil principal (não pela hierarquia Symfony).
 * TENANT = operador da plataforma, acesso total (hubs + plataforma).
 */
class NavigationService
{
    public function isTenant(User $user): bool
    {
        return $user->getPerfil() === 'TENANT';
    }

    public function getLayout(User $user): string
    {
        if ($this->isTenant($user)) {
            return 'tenant';
        }

        return match ($user->getPerfil()) {
            'GESTOR', 'GESTOR_EQUIPE' => 'gestor',
            'SUPERVISOR', 'SUPERVISOR_EQUIPE' => 'supervisor',
            default => 'membro',
        };
    }

    public function showHubOperacoes(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        return \in_array($user->getPerfil(), [
            'SUPERVISOR_EQUIPE', 'SUPERVISOR', 'GESTOR_EQUIPE', 'GESTOR',
        ], true);
    }

    public function showHubTalentos(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        return \in_array($user->getPerfil(), ['GESTOR_EQUIPE', 'GESTOR'], true);
    }

    public function showHubMaturidade(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        return \in_array($user->getPerfil(), ['GESTOR_EQUIPE', 'GESTOR'], true);
    }

    /** Seção Plataforma (usuários, empresas, configurações) — somente TENANT */
    public function showPlataforma(User $user): bool
    {
        return $this->isTenant($user);
    }

    public function showTenantEmpresas(User $user): bool
    {
        return $this->isTenant($user);
    }

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
