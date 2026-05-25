<?php

namespace App\Service;

use App\Entity\User;
use App\Security\ProductGrantAccess;

/**
 * Menu e layout por perfil principal + grants granulares quando existem no banco.
 * TENANT = operador da plataforma, acesso total (hubs + plataforma).
 */
class NavigationService
{
    /** @var list<string> */
    private const PROFILES_HUB_OPERACOES = ['SUPERVISOR_EQUIPE', 'SUPERVISOR', 'GESTOR_EQUIPE', 'GESTOR'];

    /** @var list<string> */
    private const PROFILES_GESTOR_HUBS = ['GESTOR_EQUIPE', 'GESTOR'];

    public function __construct(
        private ProductGrantAccess $grants,
    ) {
    }

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

    public function showModuloRh(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        if ($this->grants->usesConfiguredMatrix($user)) {
            return $this->grants->canViewAnyProductInScope($user, 'product_rh');
        }

        $hasGrant = $this->grants->canView($user, 'hub_operacoes', 'rh')
            || $this->grants->canViewAnyProductInScope($user, 'product_rh');

        return $this->resolveModuleVisibility($user, $hasGrant, self::PROFILES_HUB_OPERACOES);
    }

    public function showModuloPessoas(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        if ($this->grants->usesConfiguredMatrix($user)) {
            return $this->grants->canViewAnyProductInScope($user, 'product_pessoas');
        }

        $hasGrant = $this->grants->canView($user, 'hub_operacoes', 'pessoas')
            || $this->grants->canViewAnyProductInScope($user, 'product_pessoas');

        return $this->resolveModuleVisibility($user, $hasGrant, self::PROFILES_HUB_OPERACOES);
    }

    public function showHubOperacoes(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        return $this->showModuloRh($user)
            || $this->showModuloPessoas($user)
            || $this->showModuloEngenharia($user);
    }

    public function showHubTalentos(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        $hasGrant = $this->grants->canViewAnyProductInScope($user, 'hub_talentos');

        return $this->resolveModuleVisibility($user, $hasGrant, self::PROFILES_GESTOR_HUBS);
    }

    public function showHubMaturidade(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        $hasGrant = $this->grants->canViewAnyProductInScope($user, 'hub_maturidade')
            || $this->showModuloPublicidade($user);

        if ($this->grants->usesGranularGrants($user)) {
            return $hasGrant;
        }

        return \in_array($user->getPerfil(), self::PROFILES_GESTOR_HUBS, true);
    }

    public function showModuloEngenharia(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        if ($this->grants->usesConfiguredMatrix($user)) {
            return $this->grants->canViewAnyProductInScope($user, 'product_engenharia');
        }

        $hasGrant = $this->grants->canView($user, 'hub_operacoes', 'engenharia')
            || $this->grants->canViewAnyProductInScope($user, 'product_engenharia');

        return $this->resolveModuleVisibility($user, $hasGrant, self::PROFILES_GESTOR_HUBS);
    }

    public function showModuloPublicidade(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        $hasGrant = $this->grants->canViewAnyProductInScope($user, 'product_publicidade');

        return $this->resolveModuleVisibility($user, $hasGrant, self::PROFILES_GESTOR_HUBS);
    }

    /**
     * @param list<string> $legacyProfiles
     */
    private function resolveModuleVisibility(User $user, bool $hasGrant, array $legacyProfiles): bool
    {
        if ($this->grants->usesGranularGrants($user)) {
            return $hasGrant;
        }

        return \in_array($user->getPerfil(), $legacyProfiles, true);
    }

    public function hasAnyOperationalHub(User $user): bool
    {
        return $this->showHubOperacoes($user)
            || $this->showHubTalentos($user)
            || $this->showHubMaturidade($user);
    }

    /** Seção Plataforma (usuários, empresas, configurações) — somente TENANT */
    /** Quadro de desenvolvimento da plataforma (estilo Motion) — produto interno Unio. */
    public function showProjetosMetas(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        return \in_array($user->getPerfil(), ['GESTOR', 'GESTOR_EQUIPE', 'SUPERVISOR'], true);
    }

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

    /** Identificador do hub ativo pela rota atual (null = exibir lista de hubs). */
    public function getActiveHubId(?string $route): ?string
    {
        if (!$route) {
            return null;
        }

        if ($this->isHubOperacoesActive($route)) {
            return 'operacoes';
        }
        if ($this->isHubTalentosActive($route)) {
            return 'talentos';
        }
        if ($this->isHubMaturidadeActive($route)) {
            return 'maturidade';
        }
        if (str_starts_with($route, 'app_admin')) {
            return 'admin';
        }

        return null;
    }

    public function hasAnyHub(User $user): bool
    {
        return $this->showHubOperacoes($user)
            || $this->showHubTalentos($user)
            || $this->showHubMaturidade($user)
            || $this->showPlataforma($user);
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
            'nav_show_projetos_metas' => $this->showProjetosMetas($user),
            'nav_show_hub_operacoes' => $this->showHubOperacoes($user),
            'nav_show_hub_talentos' => $this->showHubTalentos($user),
            'nav_show_hub_maturidade' => $this->showHubMaturidade($user),
            'nav_show_modulo_rh' => $this->showModuloRh($user),
            'nav_show_modulo_pessoas' => $this->showModuloPessoas($user),
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
            'nav_active_hub' => $this->getActiveHubId($route),
            'nav_has_hubs' => $this->hasAnyHub($user),
        ];
    }
}
