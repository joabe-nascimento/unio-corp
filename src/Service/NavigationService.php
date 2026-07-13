<?php

namespace App\Service;

use App\Config\HubMaturity;
use App\Config\PlannedHubRegistry;
use App\Entity\User;
use App\PosOperatorio\PosOperatorioModuleCatalog;
use App\Security\ProductGrantAccess;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Organismo\OrganismoFeature;
use Symfony\Component\Routing\RouterInterface;

/**
 * Menu e layout por perfil principal + grants granulares quando existem no banco.
 * PLATFORM_OWNER = conta pessoal do dono (acima do tenant).
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
        private OrganismoFeature $organismoFeature,
        private OrganismoCopyService $organismoCopy,
    ) {
    }

    public function isTenant(User $user): bool
    {
        return $user->getPerfil() === 'TENANT';
    }

    public function getLayout(User $user): string
    {
        if ($user->isPlatformOwner()) {
            return 'platform_owner';
        }

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
        if ($user->hasPlatformAccess()) {
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
        if ($user->hasPlatformAccess()) {
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
        if ($user->hasPlatformAccess()) {
            return true;
        }

        return $this->showModuloRh($user)
            || $this->showModuloPessoas($user)
            || $this->showModuloEngenharia($user);
    }

    public function showHubTalentos(User $user): bool
    {
        if ($user->hasPlatformAccess()) {
            return true;
        }

        $hasGrant = $this->grants->canViewAnyProductInScope($user, 'hub_talentos');

        return $this->resolveModuleVisibility($user, $hasGrant, self::PROFILES_GESTOR_HUBS);
    }

    public function showHubMaturidade(User $user): bool
    {
        if ($user->hasPlatformAccess()) {
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
        if ($user->hasPlatformAccess()) {
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
            $visible[] = HubMaturity::enrichHub($hub);
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
        if ($user->hasPlatformAccess()) {
            return true;
        }

        if ($this->grants->usesConfiguredMatrix($user)) {
            return $this->grants->canViewAnyProductInScope($user, $scope);
        }

        return \in_array($user->getPerfil(), self::PROFILES_GESTOR_HUBS, true);
    }

    public function showModuloEngenharia(User $user): bool
    {
        if ($user->hasPlatformAccess()) {
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
        if ($user->hasPlatformAccess()) {
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

    /** Seção Plataforma (usuários, empresas, configurações) — tenant ou dono pessoal. */
    /** Quadro de desenvolvimento da plataforma (estilo Motion) — produto interno Unio. */
    public function showProjetosMetas(User $user): bool
    {
        if ($user->hasPlatformAccess()) {
            return true;
        }

        if ($this->grants->usesGranularGrants($user)) {
            return $this->grants->canView($user, 'product_core', 'projetos')
                || $this->grants->canView($user, 'product_core', 'metas');
        }

        return \in_array($user->getPerfil(), ['GESTOR', 'GESTOR_EQUIPE', 'SUPERVISOR'], true);
    }

    /** Guia visual de componentes — mesma audiência de ferramentas internas. */
    public function showDevComponents(User $user): bool
    {
        return $this->showProjetosMetas($user);
    }

    /** Unio Cortex — disponível para qualquer usuário autenticado. */
    public function showCortex(User $user): bool
    {
        return $user->getUserIdentifier() !== '';
    }

    public function showPlataforma(User $user): bool
    {
        return $user->hasPlatformAccess();
    }

    public function showTenantEmpresas(User $user): bool
    {
        return $user->hasPlatformAccess();
    }

    /** Logs, deploy e saúde do ambiente — somente PLATFORM_OWNER. */
    public function showPlatformOperacoes(User $user): bool
    {
        return $user->isPlatformOwner();
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
        ], true);
    }

    public function isModuloPosOpClinicaActive(?string $route): bool
    {
        return PosOperatorioModuleCatalog::isGroupActive(
            PosOperatorioModuleCatalog::GROUP_CLINICA,
            $route,
        );
    }

    public function isModuloPosOpMonitoramentoActive(?string $route): bool
    {
        return PosOperatorioModuleCatalog::isGroupActive(
            PosOperatorioModuleCatalog::GROUP_MONITORAMENTO,
            $route,
        );
    }

    public function isModuloPosOpPacienteActive(?string $route): bool
    {
        return PosOperatorioModuleCatalog::isGroupActive(
            PosOperatorioModuleCatalog::GROUP_PACIENTE,
            $route,
        );
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
            'nav_show_dev_components' => $this->showDevComponents($user),
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
            'nav_show_platform_operacoes' => $this->showPlatformOperacoes($user),
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
            'nav_modulo_pos_op_clinica_open' => $this->isModuloPosOpClinicaActive($route),
            'nav_modulo_pos_op_monitoramento_open' => $this->isModuloPosOpMonitoramentoActive($route),
            'nav_modulo_pos_op_paciente_open' => $this->isModuloPosOpPacienteActive($route),
            'nav_active_hub' => $this->getActiveHubId($route),
            'nav_has_hubs' => $this->hasAnyHub($user),
            'nav_pulso_active' => $route === 'app_pulso' || str_starts_with((string) $route, 'app_pulso_'),
        ];
    }

    /**
     * Módulos principais para dashboard e launcher de apps no header.
     *
     * @return list<array{id: string, icon: string, title: string, subtitle: string, route: string, maturity_level?: string, maturity_label?: string, maturity_badge?: string}>
     */
    public function getPlatformModules(User $user): array
    {
        $modules = [];

        if ($this->showCortex($user)) {
            $modules[] = HubMaturity::enrichHub([
                'id' => 'cortex',
                'icon' => 'fa-brain',
                'title' => 'Unio Cortex',
                'subtitle' => 'Malha neural e insights',
                'route' => 'app_cortex',
            ]);
        }

        if ($this->showHubOperacoes($user)) {
            $modules[] = HubMaturity::enrichHub([
                'id' => 'operacoes',
                'icon' => 'fa-briefcase',
                'title' => 'Núcleo de Operações',
                'subtitle' => 'RH e Gestão de Pessoas',
                'route' => 'app_hub_operacoes',
            ]);
        }
        if ($this->showHubTalentos($user)) {
            $modules[] = HubMaturity::enrichHub([
                'id' => 'talentos',
                'icon' => 'fa-gem',
                'title' => 'Núcleo de Talentos',
                'subtitle' => 'Desenvolvimento e trilhas',
                'route' => 'app_talentos',
            ]);
        }
        if ($this->showHubMaturidade($user)) {
            $modules[] = HubMaturity::enrichHub([
                'id' => 'maturidade',
                'icon' => 'fa-gauge-high',
                'title' => 'Núcleo de Maturidade',
                'subtitle' => 'Radar e plano de ação',
                'route' => 'app_maturidade',
            ]);
        }
        if ($this->showHubRecrutamento($user)) {
            $modules[] = HubMaturity::enrichHub([
                'id' => 'recrutamento',
                'icon' => 'fa-user-tie',
                'title' => 'Núcleo de Recrutamento',
                'subtitle' => 'Vagas e pipeline',
                'route' => 'app_recrutamento',
            ]);
        }
        foreach ($this->getVisiblePlannedHubs($user) as $hub) {
            $modules[] = $hub;
        }
        if ($this->showPlataforma($user)) {
            $modules[] = HubMaturity::enrichHub([
                'id' => 'admin',
                'icon' => 'fa-shield-halved',
                'title' => 'Plataforma',
                'subtitle' => 'Usuários, empresas e configurações',
                'route' => 'app_admin',
            ]);
        }

        return $modules;
    }

    /**
     * Até 5 atalhos fixos na barra inferior (somente mobile).
     *
     * @return list<array{id: string, icon: string, label: string, type: string, route?: string, action?: string, badge?: int, active: bool}>
     */
    public function getMobileShellNav(
        User $user,
        ?string $route,
        int $chatUnread = 0,
        int $notificationsUnread = 0,
        int $clinicAlertBadge = 0,
    ): array {
        if ($this->organismoFeature->isEnabled()) {
            if ($this->organismoCopy->isClinicProfile()) {
                return $this->getClinicMobileShellNav($user, $route, $clinicAlertBadge);
            }

            return $this->getStudioMobileShellNav($route, $clinicAlertBadge);
        }

        $items = [
            $this->buildMobileShellLink('dashboard', 'fa-house', 'Início', 'app_dashboard', $route, static fn (?string $r): bool => (bool) $r && str_starts_with($r, 'app_dashboard')),
            $this->buildMobileShellLink('chat', 'fa-comments', 'Chat', 'app_chat', $route, static fn (?string $r): bool => (bool) $r && str_starts_with($r, 'app_chat'), $chatUnread),
        ];

        $hub = $this->resolveMobileShellHub($user);
        if ($hub !== null) {
            $hubId = $hub['id'];
            $items[] = $this->buildMobileShellLink(
                $hubId,
                $hub['icon'],
                $this->mobileShellHubLabel($hub),
                $hub['route'],
                $route,
                fn (?string $r): bool => $hubId === 'cortex'
                    ? (bool) $r && str_starts_with($r, 'app_cortex')
                    : $this->getActiveHubId($r) === $hubId,
            );
        }

        $items[] = $this->buildMobileShellLink(
            'notifications',
            'fa-bell',
            'Alertas',
            'app_notifications',
            $route,
            static fn (?string $r): bool => (bool) $r && str_starts_with($r, 'app_notifications'),
            $notificationsUnread,
        );

        $items[] = $this->mobileShellMenuAction();

        return $items;
    }

    /**
     * Barra inferior do Unio Studio (uniowork) — alinhada à sidebar atual.
     *
     * @return list<array{id: string, icon: string, label: string, type: string, route?: string, action?: string, badge?: int, active: bool}>
     */
    private function getStudioMobileShellNav(?string $route, int $alertBadge = 0): array
    {
        $copy = $this->organismoCopy->getGlobals();
        $homeRoute = $this->organismoFeature->isPulsoHome() ? 'app_pulso' : 'app_dashboard';
        $clientesLabel = $this->shortMobileLabel((string) ($copy['nav_pacientes'] ?? 'Clientes'));
        $alertasLabel = $this->shortMobileLabel((string) ($copy['nav_alertas'] ?? 'Alertas'));

        return [
            $this->buildMobileShellLink(
                'dashboard',
                'fa-house',
                'Início',
                $homeRoute,
                $route,
                static fn (?string $r): bool => (bool) $r && (
                    str_starts_with($r, 'app_dashboard')
                    || $r === 'app_pulso'
                ),
            ),
            $this->buildMobileShellLink(
                'clientes',
                'fa-handshake',
                $clientesLabel,
                'app_admin_empresas',
                $route,
                static fn (?string $r): bool => (bool) $r && (
                    str_starts_with($r, 'app_admin_empresa')
                    || $r === 'app_publicidade_clientes'
                ),
            ),
            $this->buildMobileShellLink(
                'projetos',
                'fa-diagram-project',
                'Projetos',
                'app_core_projetos',
                $route,
                static fn (?string $r): bool => (bool) $r && (
                    str_starts_with($r, 'app_core_projetos')
                    || $r === 'app_core_metas_nova'
                    || $r === 'app_core_tarefa_mover'
                ),
            ),
            $this->buildMobileShellLink(
                'notifications',
                'fa-triangle-exclamation',
                $alertasLabel,
                'app_notifications',
                $route,
                static fn (?string $r): bool => (bool) $r && str_starts_with($r, 'app_notifications'),
                $alertBadge,
            ),
            $this->mobileShellMenuAction(),
        ];
    }

    private function shortMobileLabel(string $label, int $max = 11): string
    {
        $label = trim($label);
        if ($label === '') {
            return 'Atalho';
        }

        return mb_strlen($label) > $max ? mb_substr($label, 0, $max - 1).'…' : $label;
    }

    /**
     * Barra inferior da Unio Saúde — fluxo clínico, sem Chat/Operações/RH.
     *
     * @return list<array{id: string, icon: string, label: string, type: string, route?: string, action?: string, badge?: int, active: bool}>
     */
    private function getClinicMobileShellNav(User $user, ?string $route, int $alertBadge = 0): array
    {
        $homeRoute = $this->organismoFeature->isPulsoHome() ? 'app_pulso' : 'app_dashboard';

        $candidates = [
            $this->buildMobileShellLink(
                'dashboard',
                'fa-house-medical',
                'Início',
                $homeRoute,
                $route,
                static fn (?string $r): bool => (bool) $r && (
                    str_starts_with($r, 'app_dashboard')
                    || $r === 'app_pulso'
                ),
            ),
            $this->buildMobileShellLink(
                'agenda',
                'fa-calendar-alt',
                'Agenda',
                'app_pos_operatorio_agenda',
                $route,
                static fn (?string $r): bool => (bool) $r && str_starts_with($r, 'app_pos_operatorio_agenda'),
            ),
            $this->buildMobileShellLink(
                'pacientes',
                'fa-user-injured',
                'Pacientes',
                'app_pos_operatorio_pacientes',
                $route,
                static fn (?string $r): bool => (bool) $r && (
                    str_starts_with($r, 'app_pos_operatorio_paciente')
                    || str_starts_with($r, 'app_pos_operatorio_carteirinha')
                ),
            ),
            $this->buildMobileShellLink(
                'questionarios',
                'fa-clipboard-list',
                'Triagem',
                'app_pos_operatorio_questionarios',
                $route,
                static fn (?string $r): bool => (bool) $r && (
                    str_starts_with($r, 'app_pos_operatorio_questionario')
                    || str_starts_with($r, 'app_pos_operatorio_lembrete')
                ),
            ),
            $this->buildMobileShellLink(
                'alertas',
                'fa-bell',
                'Alertas',
                'app_pos_operatorio_alertas',
                $route,
                static fn (?string $r): bool => (bool) $r && (
                    str_starts_with($r, 'app_pos_operatorio_alerta')
                    || str_starts_with($r, 'app_pos_operatorio_sala_critica')
                ),
                $alertBadge,
            ),
            $this->buildMobileShellLink(
                'relatorios',
                'fa-file-export',
                'Relatórios',
                'app_pos_operatorio_relatorios',
                $route,
                static fn (?string $r): bool => (bool) $r && str_starts_with($r, 'app_pos_operatorio_relatorios'),
            ),
            $this->buildMobileShellLink(
                'config',
                'fa-gear',
                'Config',
                'app_pos_operatorio_config',
                $route,
                static fn (?string $r): bool => (bool) $r && str_starts_with($r, 'app_pos_operatorio_config'),
            ),
        ];

        $items = [];
        foreach ($candidates as $item) {
            $itemRoute = $item['route'] ?? null;
            if (!\is_string($itemRoute) || $itemRoute === $homeRoute || $this->grants->isRouteAllowed($user, $itemRoute)) {
                $items[] = $item;
            }
            if (\count($items) >= 4) {
                break;
            }
        }

        $items[] = $this->mobileShellMenuAction();

        return $items;
    }

    /** @return array{id: string, icon: string, label: string, type: string, action: string, active: bool} */
    private function mobileShellMenuAction(): array
    {
        return [
            'id' => 'menu',
            'icon' => 'fa-bars',
            'label' => 'Menu',
            'type' => 'action',
            'action' => 'open-sidebar',
            'active' => false,
        ];
    }

    /**
     * @param callable(?string): bool $isActive
     *
     * @return array{id: string, icon: string, label: string, type: string, route: string, active: bool, badge?: int}
     */
    private function buildMobileShellLink(
        string $id,
        string $icon,
        string $label,
        string $routeName,
        ?string $currentRoute,
        callable $isActive,
        int $badge = 0,
    ): array {
        $item = [
            'id' => $id,
            'icon' => $icon,
            'label' => $label,
            'type' => 'link',
            'route' => $routeName,
            'active' => $isActive($currentRoute),
        ];

        if ($badge > 0) {
            $item['badge'] = $badge;
        }

        return $item;
    }

    /**
     * @return array{id: string, icon: string, title: string, route: string}|null
     */
    private function resolveMobileShellHub(User $user): ?array
    {
        $modules = $this->getPlatformModules($user);
        if ($modules === []) {
            return null;
        }

        foreach ($modules as $module) {
            if (($module['id'] ?? '') === 'operacoes') {
                return $module;
            }
        }

        return $modules[0];
    }

    /**
     * @param array{id?: string, title?: string} $hub
     */
    private function mobileShellHubLabel(array $hub): string
    {
        $id = $hub['id'] ?? '';
        $short = match ($id) {
            'cortex' => 'Cortex',
            'operacoes' => 'Operações',
            'talentos' => 'Talentos',
            'maturidade' => 'Maturidade',
            'recrutamento' => 'Recrutamento',
            'admin' => 'Plataforma',
            default => null,
        };

        if ($short !== null) {
            return $short;
        }

        $title = trim((string) ($hub['title'] ?? ''));
        if ($title === '') {
            return 'Núcleo';
        }

        $title = preg_replace('/^Núcleo de\s+/iu', '', $title) ?? $title;

        return mb_strlen($title) > 11 ? mb_substr($title, 0, 10).'…' : $title;
    }
}
