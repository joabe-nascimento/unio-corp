<?php

namespace App\Service;

use App\Config\PlannedHubRegistry;
use App\Entity\User;
use App\Security\ProductGrantAccess;
use Symfony\Component\Routing\RouterInterface;

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
        private RouterInterface $router,
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

    public function showHubRecrutamento(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        $hasGrant = $this->grants->canViewAnyProductInScope($user, 'hub_recrutamento')
            || $this->grants->canViewAnyProductInScope($user, 'product_rh');

        if ($this->grants->usesGranularGrants($user)) {
            return $hasGrant;
        }

        return \in_array($user->getPerfil(), self::PROFILES_GESTOR_HUBS, true);
    }

    public function showHubComercial(User $user): bool
    {
        return $this->showFutureHub($user, 'hub_comercial');
    }

    public function showHubBeneficios(User $user): bool
    {
        return $this->showFutureHub($user, 'hub_beneficios');
    }

    public function showHubAcademy(User $user): bool
    {
        return $this->showFutureHub($user, 'hub_academy');
    }

    public function showHubParceiros(User $user): bool
    {
        return $this->showFutureHub($user, 'hub_parceiros');
    }

    public function showHubFinanceiro(User $user): bool
    {
        return $this->showFutureHub($user, 'hub_financeiro');
    }

    public function showHubCompliance(User $user): bool
    {
        return $this->showFutureHub($user, 'hub_compliance');
    }

    public function showHubAnalytics(User $user): bool
    {
        return $this->showFutureHub($user, 'hub_analytics');
    }

    public function showHubJuridico(User $user): bool
    {
        return $this->showFutureHub($user, 'hub_juridico');
    }

    public function showHubClima(User $user): bool
    {
        return $this->showFutureHub($user, 'hub_clima');
    }

    public function showHubSst(User $user): bool
    {
        return $this->showFutureHub($user, 'hub_sst');
    }

    public function showHubComunicacao(User $user): bool
    {
        return $this->showFutureHub($user, 'hub_comunicacao');
    }

    public function showAnyPlannedHub(User $user): bool
    {
        foreach (PlannedHubRegistry::HUBS as $hub) {
            if ($this->showPlannedHubEntry($user, $hub)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, string>>
     */
    public function getVisiblePlannedHubs(User $user): array
    {
        $visible = [];
        foreach (PlannedHubRegistry::HUBS as $hub) {
            if (!$this->showPlannedHubEntry($user, $hub)) {
                continue;
            }
            if (!$this->isRouteRegistered($hub['route'])) {
                continue;
            }
            $visible[] = $hub;
        }

        return $visible;
    }

    /**
     * Hubs planejados agrupados para o picker da sidebar.
     *
     * @return list<array{key: string, label: string, hubs: list<array<string, string>>}>
     */
    public function getVisiblePlannedHubGroups(User $user): array
    {
        return PlannedHubRegistry::groupHubs($this->getVisiblePlannedHubs($user));
    }

    private function isRouteRegistered(string $routeName): bool
    {
        return $this->router->getRouteCollection()->get($routeName) !== null;
    }

    private function showPlannedHubEntry(User $user, array $hub): bool
    {
        if ($this->showFutureHub($user, $hub['scope'])) {
            return true;
        }

        return match ($hub['id']) {
            'publicidade' => $this->showModuloPublicidade($user),
            'obras' => $this->showModuloEngenharia($user),
            default => false,
        };
    }

    private function showFutureHub(User $user, string $scope): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        if ($this->grants->usesConfiguredMatrix($user)) {
            return $this->grants->canViewAnyProductInScope($user, $scope);
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
            || $this->showHubMaturidade($user)
            || $this->showAnyPlannedHub($user);
    }

    /** Seção Plataforma (usuários, empresas, configurações) — somente TENANT */
    /** Quadro de desenvolvimento da plataforma (estilo Motion) — produto interno Unio. */
    public function showProjetosMetas(User $user): bool
    {
        if ($this->isTenant($user)) {
            return true;
        }

        if ($this->grants->usesGranularGrants($user)) {
            return $this->grants->canView($user, 'product_core', 'projetos')
                || $this->grants->canView($user, 'product_core', 'metas');
        }

        return \in_array($user->getPerfil(), ['GESTOR', 'GESTOR_EQUIPE', 'SUPERVISOR'], true);
    }

    /** Unio Cortex — disponível para qualquer usuário autenticado. */
    public function showCortex(User $user): bool
    {
        return $user->getUserIdentifier() !== '';
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
            || (str_starts_with($route, 'app_rh') && !str_starts_with($route, 'app_rh_recrutamento'))
            || str_starts_with($route, 'app_pessoas')
            || str_starts_with($route, 'app_engenharia');
    }

    public function isHubRecrutamentoActive(?string $route): bool
    {
        if (!$route) {
            return false;
        }

        return str_starts_with($route, 'app_recrutamento')
            || str_starts_with($route, 'app_rh_recrutamento');
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

    /** Identificador do hub ativo pela rota atual (null = exibir lista de núcleos). */
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
        if ($this->isHubRecrutamentoActive($route)) {
            return 'recrutamento';
        }
        $planned = PlannedHubRegistry::findByRoute($route);
        if ($planned !== null) {
            return $planned['id'];
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
            || $this->showHubRecrutamento($user)
            || $this->showAnyPlannedHub($user)
            || $this->showPlataforma($user);
    }

    public function isModuloRhActive(?string $route): bool
    {
        return (bool) $route
            && str_starts_with($route, 'app_rh')
            && !str_starts_with($route, 'app_rh_recrutamento');
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

    public function isModuloInovacaoFluxoActive(?string $route): bool
    {
        return (bool) $route && \in_array($route, [
            'app_inovacao_pipeline',
            'app_inovacao_laboratorio',
            'app_inovacao_experimentos',
        ], true);
    }

    public function isModuloInovacaoIdeacaoActive(?string $route): bool
    {
        return (bool) $route && \in_array($route, [
            'app_inovacao_backlog',
            'app_inovacao_nova_ideia',
            'app_inovacao_nova_ideia_submit',
        ], true);
    }

    public function isModuloInovacaoInteligenciaActive(?string $route): bool
    {
        return (bool) $route && \in_array($route, [
            'app_inovacao_analytics',
            'app_inovacao_conexoes',
            'app_inovacao_impact',
            'app_inovacao_tendencias',
            'app_inovacao_portfolio',
            'app_inovacao_novidades',
        ], true);
    }

    public function isModuloIntegracoesConexoesActive(?string $route): bool
    {
        return (bool) $route && \in_array($route, [
            'app_integracoes_catalogo',
            'app_integracoes_conectores',
            'app_integracoes_webhooks',
            'app_integracoes_catalogo_ativar',
            'app_integracoes_conector_novo_submit',
            'app_integracoes_conector_editar_submit',
            'app_integracoes_conector_excluir',
            'app_integracoes_conector_testar',
            'app_integracoes_conector_pausar',
            'app_integracoes_webhook_novo_submit',
            'app_integracoes_webhook_editar_submit',
            'app_integracoes_webhook_excluir',
            'app_integracoes_webhook_toggle',
        ], true);
    }

    public function isModuloIntegracoesDadosActive(?string $route): bool
    {
        return (bool) $route && \in_array($route, [
            'app_integracoes_mapeamentos',
            'app_integracoes_api',
            'app_integracoes_mapeamento_novo_submit',
            'app_integracoes_mapeamento_editar_submit',
            'app_integracoes_mapeamento_excluir',
            'app_integracoes_api_nova_submit',
            'app_integracoes_api_revogar',
        ], true);
    }

    public function isModuloIntegracoesObsActive(?string $route): bool
    {
        return (bool) $route && \in_array($route, [
            'app_integracoes_logs',
            'app_integracoes_logs_export',
            'app_integracoes_observatorio',
            'app_integracoes_observatorio_shadow',
            'app_integracoes_drift_resolver',
            'app_integracoes_playbooks',
            'app_integracoes_dead_letter',
            'app_integracoes_dead_letter_retry',
            'app_integracoes_dead_letter_descartar',
            'app_integracoes_slo',
        ], true);
    }

    public function isModuloRecrutamentoSelecaoActive(?string $route): bool
    {
        if (!$route) {
            return false;
        }

        return str_starts_with($route, 'app_recrutamento_vaga')
            || str_starts_with($route, 'app_recrutamento_candidato')
            || $route === 'app_recrutamento_pipeline'
            || $route === 'app_recrutamento_carreiras'
            || $route === 'app_recrutamento_talentos'
            || $route === 'app_recrutamento_integracoes'
            || str_starts_with($route, 'app_rh_recrutamento');
    }

    public function isModuloTiOpsActive(?string $route): bool
    {
        return (bool) $route && \in_array($route, [
            'app_ti_chamados',
            'app_ti_chamado_novo',
            'app_ti_chamado_novo_submit',
            'app_ti_chamado_show',
            'app_ti_chamado_status',
            'app_ti_chamado_excluir',
            'app_ti_chamado_atribuir',
            'app_ti_chamado_nota',
            'app_ti_chamado_prioridade',
            'app_ti_chamado_helia_aplicar',
            'app_ti_chamado_helia_revisar',
            'app_ti_catalogo',
            'app_ti_sla',
            'app_ti_manutencoes',
            'app_ti_manutencao_novo_submit',
            'app_ti_manutencao_editar_submit',
            'app_ti_manutencao_excluir',
        ], true);
    }

    public function isModuloTiInfraActive(?string $route): bool
    {
        return (bool) $route && \in_array($route, [
            'app_ti_ativos',
            'app_ti_ativo_novo_submit',
            'app_ti_ativo_editar_submit',
            'app_ti_ativo_excluir',
            'app_ti_licencas',
            'app_ti_licenca_novo_submit',
            'app_ti_licenca_editar_submit',
            'app_ti_licenca_excluir',
            'app_ti_integracoes',
            'app_ti_integracao_novo_submit',
            'app_ti_integracao_editar_submit',
            'app_ti_integracao_excluir',
        ], true);
    }

    public function isModuloTiIntelActive(?string $route): bool
    {
        return (bool) $route && \in_array($route, [
            'app_ti_cortex',
            'app_ti_analytics',
            'app_ti_novidades',
            'app_ti_novidade_novo_submit',
            'app_ti_novidade_editar_submit',
            'app_ti_novidade_excluir',
            'app_ti_war_room',
        ], true);
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
            'nav_show_cortex' => $this->showCortex($user),
            'nav_show_projetos_metas' => $this->showProjetosMetas($user),
            'nav_show_hub_operacoes' => $this->showHubOperacoes($user),
            'nav_show_hub_talentos' => $this->showHubTalentos($user),
            'nav_show_hub_maturidade' => $this->showHubMaturidade($user),
            'nav_show_hub_recrutamento' => $this->showHubRecrutamento($user),
            'nav_planned_hubs' => $this->getVisiblePlannedHubs($user),
            'nav_planned_hub_groups' => $this->getVisiblePlannedHubGroups($user),
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
            'nav_hub_recrutamento_active' => $this->isHubRecrutamentoActive($route),
            'nav_modulo_rh_active' => $this->isModuloRhActive($route),
            'nav_modulo_pessoas_active' => $this->isModuloPessoasActive($route),
            'nav_modulo_engenharia_active' => $this->isModuloEngenhariaActive($route),
            'nav_modulo_publicidade_active' => $this->isModuloPublicidadeActive($route),
            'nav_modulo_inovacao_fluxo_active' => $this->isModuloInovacaoFluxoActive($route),
            'nav_modulo_inovacao_ideacao_active' => $this->isModuloInovacaoIdeacaoActive($route),
            'nav_modulo_inovacao_inteligencia_active' => $this->isModuloInovacaoInteligenciaActive($route),
            'nav_modulo_integracoes_conexoes_active' => $this->isModuloIntegracoesConexoesActive($route),
            'nav_modulo_integracoes_dados_active' => $this->isModuloIntegracoesDadosActive($route),
            'nav_modulo_integracoes_obs_active' => $this->isModuloIntegracoesObsActive($route),
            'nav_modulo_ti_ops_open' => $this->isModuloTiOpsActive($route),
            'nav_modulo_recrutamento_selecao_active' => $this->isModuloRecrutamentoSelecaoActive($route),
            'nav_modulo_ti_infra_open' => $this->isModuloTiInfraActive($route),
            'nav_modulo_ti_intel_open' => $this->isModuloTiIntelActive($route),
            'nav_active_hub' => $this->getActiveHubId($route),
            'nav_has_hubs' => $this->hasAnyHub($user),
        ];
    }

    /**
     * Módulos principais para dashboard e launcher de apps no header.
     *
     * @return list<array{id: string, icon: string, title: string, subtitle: string, route: string}>
     */
    public function getPlatformModules(User $user): array
    {
        $modules = [];

        if ($this->showCortex($user)) {
            $modules[] = [
                'id' => 'cortex',
                'icon' => 'fa-brain',
                'title' => 'Unio Cortex',
                'subtitle' => 'Malha neural e insights',
                'route' => 'app_cortex',
            ];
        }

        if ($this->showHubOperacoes($user)) {
            $modules[] = [
                'id' => 'operacoes',
                'icon' => 'fa-briefcase',
                'title' => 'Núcleo de Operações',
                'subtitle' => 'RH e Gestão de Pessoas',
                'route' => 'app_hub_operacoes',
            ];
        }
        if ($this->showHubTalentos($user)) {
            $modules[] = [
                'id' => 'talentos',
                'icon' => 'fa-gem',
                'title' => 'Núcleo de Talentos',
                'subtitle' => 'Banco, vagas e trilhas',
                'route' => 'app_talentos',
            ];
        }
        if ($this->showHubMaturidade($user)) {
            $modules[] = [
                'id' => 'maturidade',
                'icon' => 'fa-gauge-high',
                'title' => 'Núcleo de Maturidade',
                'subtitle' => 'Radar e plano de ação',
                'route' => 'app_maturidade',
            ];
        }
        if ($this->showHubRecrutamento($user)) {
            $modules[] = [
                'id' => 'recrutamento',
                'icon' => 'fa-user-tie',
                'title' => 'Núcleo de Recrutamento',
                'subtitle' => 'Vagas e pipeline',
                'route' => 'app_recrutamento',
            ];
        }
        foreach ($this->getVisiblePlannedHubs($user) as $hub) {
            $modules[] = [
                'id' => $hub['id'],
                'icon' => $hub['icon'],
                'title' => $hub['label'],
                'subtitle' => $hub['subtitle'],
                'route' => $hub['route'],
            ];
        }
        if ($this->showPlataforma($user)) {
            $modules[] = [
                'id' => 'admin',
                'icon' => 'fa-shield-halved',
                'title' => 'Plataforma',
                'subtitle' => 'Usuários, empresas e configurações',
                'route' => 'app_admin',
            ];
        }

        return $modules;
    }
}
