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

    /** Obras e projetos — dentro do Hub Operações (gestor+) */
    public function showModuloEngenharia(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        return \in_array($user->getPerfil(), ['GESTOR_EQUIPE', 'GESTOR'], true);
    }

    /** Marca e comunicação — dentro do Hub de Maturidade (gestor+) */
    public function showModuloPublicidade(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        return \in_array($user->getPerfil(), ['GESTOR_EQUIPE', 'GESTOR'], true);
    }

    public function hasAnyOperationalHub(User $user): bool
    {
        return $this->showHubOperacoes($user)
            || $this->showHubTalentos($user)
            || $this->showHubMaturidade($user);
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
            || str_starts_with($route, 'app_pessoas')
            || str_starts_with($route, 'app_engenharia');
    }

    public function isHubTalentosActive(?string $route): bool
    {
        return (bool) $route && str_starts_with($route, 'app_talentos');
    }

    public function isHubMaturidadeActive(?string $route): bool
    {
        if (!$route) {
            return false;
        }

        return str_starts_with($route, 'app_maturidade')
            || str_starts_with($route, 'app_publicidade');
    }

    public function isModuloRhActive(?string $route): bool
    {
        return (bool) $route && str_starts_with($route, 'app_rh');
    }

    public function isModuloPessoasActive(?string $route): bool
    {
        return (bool) $route && str_starts_with($route, 'app_pessoas');
    }

    public function isModuloEngenhariaActive(?string $route): bool
    {
        return (bool) $route && str_starts_with($route, 'app_engenharia');
    }

    public function isModuloPublicidadeActive(?string $route): bool
    {
        return (bool) $route && str_starts_with($route, 'app_publicidade');
    }

    /**
     * Globais Twig de navegação (uma passagem por request).
     *
     * @return array<string, mixed>
     */
    public function getNavGlobals(User $user, ?string $route): array
    {
        $showPlataforma = $this->showPlataforma($user);

        return [
            'nav_layout' => $this->getLayout($user),
            'nav_show_hub_operacoes' => $this->showHubOperacoes($user),
            'nav_show_hub_talentos' => $this->showHubTalentos($user),
            'nav_show_hub_maturidade' => $this->showHubMaturidade($user),
            'nav_show_modulo_engenharia' => $this->showModuloEngenharia($user),
            'nav_show_modulo_publicidade' => $this->showModuloPublicidade($user),
            'nav_show_plataforma' => $showPlataforma,
            'nav_show_admin' => $showPlataforma,
            'nav_show_tenant_empresas' => $this->showTenantEmpresas($user),
            'nav_hub_operacoes_active' => $this->isHubOperacoesActive($route),
            'nav_hub_talentos_active' => $this->isHubTalentosActive($route),
            'nav_hub_maturidade_active' => $this->isHubMaturidadeActive($route),
            'nav_modulo_rh_active' => $this->isModuloRhActive($route),
            'nav_modulo_pessoas_active' => $this->isModuloPessoasActive($route),
            'nav_modulo_engenharia_active' => $this->isModuloEngenhariaActive($route),
            'nav_modulo_publicidade_active' => $this->isModuloPublicidadeActive($route),
        ];
    }
}
