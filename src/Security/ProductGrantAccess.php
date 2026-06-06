<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserProductGrantRepository;
use App\Security\Voter\ProductGrantVoter;
use App\Service\PermissionService;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Verificação de acesso por grant (reutiliza ProductGrantVoter).
 * Com grants no banco, o acesso é elevado/restrito por produto — independente da role global.
 */
final class ProductGrantAccess
{
    /** Rotas de índice que também aceitam grants no escopo de produto do módulo. */
    private const MODULE_PRODUCT_SCOPES = [
        'app_hub_operacoes' => ['hub_operacoes', 'product_rh', 'product_pessoas', 'product_engenharia'],
        'app_rh' => ['product_rh'],
        'app_pessoas' => ['product_pessoas'],
        'app_engenharia' => ['hub_obras', 'product_engenharia', 'hub_operacoes'],
        'app_publicidade' => ['hub_publicidade', 'product_publicidade'],
        'app_ti' => ['hub_ti'],
        'app_integracoes' => ['hub_integracoes'],
        'app_recrutamento' => ['hub_recrutamento', 'product_rh'],
        'app_recrutamento_analytics' => ['hub_recrutamento', 'product_rh'],
    ];

    /** Hub Recrutamento ↔ módulo RH Recrutamento (rotas espelhadas). */
    private const RECRUTAMENTO_ROUTE_FALLBACK = [
        'app_recrutamento' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_analytics' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_vagas' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_pipeline' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_carreiras' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_talentos' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_integracoes' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidato' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidato_etapa' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidato_reprovar' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_vaga_status' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_vagas_show' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_vaga_edit' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidatos' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidatos_show' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidato_edit' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_vaga_publicar' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_vaga_despublicar' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_talento_inscrever' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidato_entrevista' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidato_scorecard' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidato_curriculo' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidato_banco_talentos' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_aprovacao_decidir' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidato_avaliacao' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_recrutamento_candidatos_export' => ['scope' => 'product_rh', 'product' => 'recrutamento'],
        'app_rh_recrutamento' => ['scope' => 'hub_recrutamento', 'product' => 'vagas'],
        'app_rh_recrutamento_vaga_status' => ['scope' => 'hub_recrutamento', 'product' => 'vagas'],
        'app_rh_recrutamento_candidato' => ['scope' => 'hub_recrutamento', 'product' => 'vagas'],
        'app_rh_recrutamento_pipeline' => ['scope' => 'hub_recrutamento', 'product' => 'pipeline'],
        'app_rh_recrutamento_candidato_etapa' => ['scope' => 'hub_recrutamento', 'product' => 'pipeline'],
        'app_rh_recrutamento_candidato_reprovar' => ['scope' => 'hub_recrutamento', 'product' => 'pipeline'],
    ];

    /** Rotas TI com regra própria (além do produto primário no mapa). */
    private const TI_ROUTE_FALLBACK = [
        'app_ti_chamado_show' => 'view_chamado',
        'app_ti_chamado_novo_submit' => 'create_chamado',
        'app_ti_helia_analyze' => 'create_chamado',
    ];

    /** Rotas de criação/edição — exigem perfil mínimo no grant do produto. */
    private const MANAGE_ROUTES = [
        // Pessoas
        'app_pessoas_membro_novo' => ['scope' => 'product_pessoas', 'product' => 'membros', 'min' => 'GESTOR_EQUIPE'],
        'app_pessoas_equipe_nova' => ['scope' => 'product_pessoas', 'product' => 'equipes', 'min' => 'GESTOR_EQUIPE'],
        'app_rh_funcionario_novo' => ['scope' => 'product_rh', 'product' => 'funcionarios', 'min' => 'GESTOR_EQUIPE'],
        'app_rh_funcionario_editar' => ['scope' => 'product_rh', 'product' => 'funcionarios', 'min' => 'GESTOR_EQUIPE'],
        'app_rh_admissoes_nova' => ['scope' => 'product_rh', 'product' => 'admissoes', 'min' => 'GESTOR_EQUIPE'],
        'app_rh_demissoes_nova' => ['scope' => 'product_rh', 'product' => 'admissoes', 'min' => 'GESTOR_EQUIPE'],
        'app_rh_ferias_nova' => ['scope' => 'product_rh', 'product' => 'ferias', 'min' => 'GESTOR_EQUIPE'],
        'app_rh_folha_gerar' => ['scope' => 'product_rh', 'product' => 'folha', 'min' => 'GESTOR'],
        // Recrutamento
        'app_recrutamento_vaga_status' => ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_vaga_edit' => ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_candidato' => ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_candidato_etapa' => ['scope' => 'hub_recrutamento', 'product' => 'pipeline', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_candidato_reprovar' => ['scope' => 'hub_recrutamento', 'product' => 'pipeline', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_candidato_edit' => ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_vaga_publicar' => ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_vaga_despublicar' => ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_talento_inscrever' => ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_candidato_entrevista' => ['scope' => 'hub_recrutamento', 'product' => 'pipeline', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_candidato_scorecard' => ['scope' => 'hub_recrutamento', 'product' => 'pipeline', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_candidato_curriculo' => ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_candidato_banco_talentos' => ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'min' => 'GESTOR_EQUIPE'],
        'app_recrutamento_aprovacao_decidir' => ['scope' => 'hub_recrutamento', 'product' => 'pipeline', 'min' => 'GESTOR_EQUIPE'],
        // Núcleo TI — chamados
        'app_ti_chamado_novo_submit' => ['scope' => 'hub_ti', 'product' => 'meus_chamados', 'min' => 'MEMBRO'],
        'app_ti_chamado_status' => ['scope' => 'hub_ti', 'product' => 'chamados', 'min' => 'SUPERVISOR_EQUIPE'],
        'app_ti_chamado_atribuir' => ['scope' => 'hub_ti', 'product' => 'chamados', 'min' => 'SUPERVISOR_EQUIPE'],
        'app_ti_chamado_nota' => ['scope' => 'hub_ti', 'product' => 'chamados', 'min' => 'SUPERVISOR_EQUIPE'],
        'app_ti_chamado_prioridade' => ['scope' => 'hub_ti', 'product' => 'chamados', 'min' => 'SUPERVISOR_EQUIPE'],
        'app_ti_chamado_helia_aplicar' => ['scope' => 'hub_ti', 'product' => 'chamados', 'min' => 'SUPERVISOR_EQUIPE'],
        'app_ti_chamado_helia_revisar' => ['scope' => 'hub_ti', 'product' => 'chamados', 'min' => 'SUPERVISOR_EQUIPE'],
        'app_ti_chamado_helia_feedback' => ['scope' => 'hub_ti', 'product' => 'chamados', 'min' => 'SUPERVISOR_EQUIPE'],
        'app_ti_chamado_sla_pausa' => ['scope' => 'hub_ti', 'product' => 'chamados', 'min' => 'SUPERVISOR'],
        'app_ti_chamado_problema' => ['scope' => 'hub_ti', 'product' => 'chamados', 'min' => 'SUPERVISOR'],
        'app_ti_chamado_excluir' => ['scope' => 'hub_ti', 'product' => 'chamados', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_helia_analyze' => ['scope' => 'hub_ti', 'product' => 'chamados', 'min' => 'SUPERVISOR_EQUIPE'],
        // Núcleo TI — KB, problemas, infra, novidades, analytics
        'app_ti_kb_novo_submit' => ['scope' => 'hub_ti', 'product' => 'kb', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_kb_editar_submit' => ['scope' => 'hub_ti', 'product' => 'kb', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_kb_excluir' => ['scope' => 'hub_ti', 'product' => 'kb', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_problema_novo_submit' => ['scope' => 'hub_ti', 'product' => 'problemas', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_problema_editar_submit' => ['scope' => 'hub_ti', 'product' => 'problemas', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_problema_excluir' => ['scope' => 'hub_ti', 'product' => 'problemas', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_ativo_novo_submit' => ['scope' => 'hub_ti', 'product' => 'ativos', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_ativo_editar_submit' => ['scope' => 'hub_ti', 'product' => 'ativos', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_ativo_excluir' => ['scope' => 'hub_ti', 'product' => 'ativos', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_licenca_novo_submit' => ['scope' => 'hub_ti', 'product' => 'licencas', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_licenca_editar_submit' => ['scope' => 'hub_ti', 'product' => 'licencas', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_licenca_excluir' => ['scope' => 'hub_ti', 'product' => 'licencas', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_integracao_novo_submit' => ['scope' => 'hub_ti', 'product' => 'integracoes', 'min' => 'GESTOR'],
        'app_ti_integracao_editar_submit' => ['scope' => 'hub_ti', 'product' => 'integracoes', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_integracao_excluir' => ['scope' => 'hub_ti', 'product' => 'integracoes', 'min' => 'GESTOR'],
        'app_ti_manutencao_novo_submit' => ['scope' => 'hub_ti', 'product' => 'manutencoes', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_manutencao_editar_submit' => ['scope' => 'hub_ti', 'product' => 'manutencoes', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_manutencao_excluir' => ['scope' => 'hub_ti', 'product' => 'manutencoes', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_novidade_novo_submit' => ['scope' => 'hub_ti', 'product' => 'novidades', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_novidade_editar_submit' => ['scope' => 'hub_ti', 'product' => 'novidades', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_novidade_excluir' => ['scope' => 'hub_ti', 'product' => 'novidades', 'min' => 'GESTOR_EQUIPE'],
        'app_ti_analytics_export' => ['scope' => 'hub_ti', 'product' => 'analytics', 'min' => 'GESTOR_EQUIPE'],
        // Núcleo Integrações
        'app_integracoes_catalogo_ativar' => ['scope' => 'hub_integracoes', 'product' => 'conectores', 'min' => 'GESTOR_EQUIPE'],
        'app_integracoes_conector_novo_submit' => ['scope' => 'hub_integracoes', 'product' => 'conectores', 'min' => 'GESTOR_EQUIPE'],
        'app_integracoes_conector_editar_submit' => ['scope' => 'hub_integracoes', 'product' => 'conectores', 'min' => 'GESTOR_EQUIPE'],
        'app_integracoes_conector_excluir' => ['scope' => 'hub_integracoes', 'product' => 'conectores', 'min' => 'GESTOR'],
        'app_integracoes_webhook_novo_submit' => ['scope' => 'hub_integracoes', 'product' => 'webhooks', 'min' => 'GESTOR_EQUIPE'],
        'app_integracoes_webhook_editar_submit' => ['scope' => 'hub_integracoes', 'product' => 'webhooks', 'min' => 'GESTOR_EQUIPE'],
        'app_integracoes_webhook_excluir' => ['scope' => 'hub_integracoes', 'product' => 'webhooks', 'min' => 'GESTOR'],
        'app_integracoes_api_nova_submit' => ['scope' => 'hub_integracoes', 'product' => 'api_keys', 'min' => 'GESTOR'],
        'app_integracoes_api_revogar' => ['scope' => 'hub_integracoes', 'product' => 'api_keys', 'min' => 'GESTOR'],
        'app_integracoes_mapeamento_novo_submit' => ['scope' => 'hub_integracoes', 'product' => 'mapeamentos', 'min' => 'GESTOR_EQUIPE'],
        'app_integracoes_mapeamento_editar_submit' => ['scope' => 'hub_integracoes', 'product' => 'mapeamentos', 'min' => 'GESTOR_EQUIPE'],
        'app_integracoes_mapeamento_excluir' => ['scope' => 'hub_integracoes', 'product' => 'mapeamentos', 'min' => 'GESTOR_EQUIPE'],
        // Engenharia / Publicidade — idem
    ];

    /** Escopos de operação (supervisor+ na role global, sem grants). */
    private const OPERACOES_SCOPES = ['hub_operacoes', 'product_rh', 'product_pessoas'];

    public function __construct(
        private Security $security,
        private UserProductGrantRepository $grantRepo,
    ) {
    }

    public function usesGranularGrants(User $user): bool
    {
        if ($user->isTenant()) {
            return false;
        }

        return $this->grantRepo->userHasConfiguredMatrix($user)
            || $this->grantRepo->userHasAnyGrant($user);
    }

    /** Matriz salva explicitamente (inclui "sem acesso" total). */
    public function usesConfiguredMatrix(User $user): bool
    {
        if ($user->isTenant()) {
            return false;
        }

        return $this->grantRepo->userHasConfiguredMatrix($user);
    }

    public function canView(User $user, string $scope, string $product): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        return $this->security->isGranted(ProductGrantVoter::VIEW, [
            'scope' => $scope,
            'product' => $product,
        ]);
    }

    /** UI/nav: grant granular ou role global quando ainda não há matriz no banco. */
    public function canViewProductForUi(User $user, string $scope, string $product): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        if ($this->usesGranularGrants($user)) {
            return $this->canView($user, $scope, $product);
        }

        return $this->roleAllowsScopeFamily($user, $scope);
    }

    /**
     * Perfil efetivo no produto (grant ou role global se sem matriz granular).
     */
    public function effectiveProfileLevel(User $user, string $scope, string $product): int
    {
        if ($user->isTenant()) {
            return 99;
        }

        if ($this->usesGranularGrants($user)) {
            $grant = $this->grantRepo->findOneForUserScopeProduct($user, $scope, $product);
            if (!$grant || $grant->getPerfilGrant() === '') {
                return 0;
            }

            return PermissionService::profileNivel($grant->getPerfilGrant());
        }

        return $user->getNivel();
    }

    /** Criar/editar no produto — exige perfil mínimo (ex.: GESTOR_EQUIPE para novo membro). */
    public function grantAtLeast(User $user, string $scope, string $product, string $minProfileId): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        $min = PermissionService::profileNivel($minProfileId);
        if ($min === 0) {
            return false;
        }

        return $this->effectiveProfileLevel($user, $scope, $product) >= $min;
    }

    public function canManageRoute(User $user, string $routeName): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        if ($routeName === 'app_ti_chamado_novo_submit' || $routeName === 'app_ti_helia_analyze') {
            return $this->tiCanCreateChamado($user);
        }

        if (!isset(self::MANAGE_ROUTES[$routeName])) {
            return true;
        }

        $rule = self::MANAGE_ROUTES[$routeName];

        if ($this->grantAtLeast($user, $rule['scope'], $rule['product'], $rule['min'])) {
            return true;
        }

        if (str_starts_with($routeName, 'app_recrutamento_')) {
            return $this->grantAtLeast($user, 'product_rh', 'recrutamento', $rule['min']);
        }

        return false;
    }

    /**
     * @param list<string> $productIds
     */
    public function canViewAnyInScope(User $user, string $scope, array $productIds): bool
    {
        foreach ($productIds as $productId) {
            if ($this->canViewProductForUi($user, $scope, $productId)) {
                return true;
            }
        }

        return false;
    }

    public function canViewAnyProductInScope(User $user, string $scope): bool
    {
        if (!isset(PermissionService::SCOPES[$scope])) {
            return false;
        }

        $productIds = array_column(PermissionService::SCOPES[$scope]['products'], 'id');

        return $this->canViewAnyInScope($user, $scope, $productIds);
    }

    public function canAccessRoute(User $user, string $routeName): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        if (!isset(ProductGrantRouteMap::MAP[$routeName])) {
            return true;
        }

        $primary = ProductGrantRouteMap::MAP[$routeName];
        $productIds = self::productIdsForScope($primary['scope']);
        if ($productIds === []) {
            if ($this->usesGranularGrants($user)) {
                return false;
            }

            return $this->roleAllowsRoute($user, $routeName);
        }

        $moduleScopes = self::MODULE_PRODUCT_SCOPES[$routeName] ?? null;

        if ($this->usesConfiguredMatrix($user) && \is_array($moduleScopes)) {
            if ($routeName === 'app_hub_operacoes') {
                return $this->canAccessHubOperacoesViaProducts($user);
            }

            foreach ($moduleScopes as $scope) {
                if ($scope === 'hub_operacoes') {
                    continue;
                }
                if ($this->canViewAnyProductInScope($user, $scope)) {
                    return true;
                }
            }

            return false;
        }

        $primary = ProductGrantRouteMap::MAP[$routeName];
        if ($this->canView($user, $primary['scope'], $primary['product'])) {
            return true;
        }

        if (isset(self::TI_ROUTE_FALLBACK[$routeName])) {
            return $this->tiRouteAllowed($user, self::TI_ROUTE_FALLBACK[$routeName]);
        }

        if (isset(self::RECRUTAMENTO_ROUTE_FALLBACK[$routeName])) {
            $fallback = self::RECRUTAMENTO_ROUTE_FALLBACK[$routeName];

            return $this->canView($user, $fallback['scope'], $fallback['product']);
        }

        foreach ($moduleScopes ?? [] as $scope) {
            if ($this->canViewAnyProductInScope($user, $scope)) {
                return true;
            }
        }

        return false;
    }

    private function canAccessHubOperacoesViaProducts(User $user): bool
    {
        foreach (['product_rh', 'product_pessoas', 'product_engenharia'] as $scope) {
            if ($this->canViewAnyProductInScope($user, $scope)) {
                return true;
            }
        }

        return false;
    }

    public function roleAllowsRoute(User $user, string $routeName): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        if (str_starts_with($routeName, 'app_admin')) {
            return false;
        }

        if (preg_match('/^app_(talentos|maturidade|recrutamento|engenharia|publicidade|comercial|beneficios|academy|parceiros|financeiro|compliance|analytics|juridico|clima|sst|comunicacao|hub_portal|esg|suprimentos|ti|expansao|qualidade|facilities|patrimonio|conhecimento|integracoes|customer_success|inovacao|holdings)/', $routeName) === 1) {
            return $this->security->isGranted('ROLE_GESTOR_EQUIPE');
        }

        if (preg_match('/^app_(hub_operacoes|rh|pessoas)/', $routeName) === 1) {
            return $this->security->isGranted('ROLE_SUPERVISOR_EQUIPE');
        }

        return true;
    }

    public function isRouteAllowed(User $user, string $routeName): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        if (!$this->canManageRoute($user, $routeName)) {
            return false;
        }

        if ($this->usesGranularGrants($user)) {
            return $this->canAccessRoute($user, $routeName);
        }

        return $this->roleAllowsRoute($user, $routeName);
    }

    private function roleAllowsScopeFamily(User $user, string $scope): bool
    {
        if (\in_array($scope, self::OPERACOES_SCOPES, true)) {
            return $this->security->isGranted('ROLE_SUPERVISOR_EQUIPE');
        }

        return $this->security->isGranted('ROLE_GESTOR_EQUIPE');
    }

    /**
     * @return list<string>
     */
    public static function productIdsForScope(string $scope): array
    {
        if (!isset(PermissionService::SCOPES[$scope])) {
            return [];
        }

        return array_column(PermissionService::SCOPES[$scope]['products'], 'id');
    }

    /**
     * Badge de perfil na UI — eleva quando grants granulares superam a role global.
     *
     * @return array{label: string, class: string, elevated: bool, global_label: string}
     */
    public function getDisplayProfileBadge(User $user): array
    {
        $globalLabel = $user->getPerfilLabel();
        $result = [
            'label' => $globalLabel,
            'class' => $user->getPerfilClass(),
            'elevated' => false,
            'global_label' => $globalLabel,
        ];

        if ($user->isTenant()) {
            return $result;
        }

        $highestGrant = $this->highestGrantProfileId($user);
        if ($highestGrant === null) {
            return $result;
        }

        $grantLevel = PermissionService::profileNivel($highestGrant);
        if ($grantLevel > $user->getNivel()) {
            $result['label'] = PermissionService::profileLabel($highestGrant);
            $result['class'] = PermissionService::profileClass($highestGrant);
            $result['elevated'] = true;
        }

        return $result;
    }

    private function highestGrantProfileId(User $user): ?string
    {
        if (!$this->usesGranularGrants($user)) {
            return null;
        }

        $bestLevel = 0;
        $bestId = null;
        foreach ($this->grantRepo->findAllGrantKeysForUser($user) as $profileId) {
            $level = PermissionService::profileNivel($profileId);
            if ($level > $bestLevel) {
                $bestLevel = $level;
                $bestId = $profileId;
            }
        }

        return $bestId;
    }

    private function tiRouteAllowed(User $user, string $action): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        return match ($action) {
            'view_chamado' => $this->grantAtLeast($user, TiGrantPolicy::SCOPE, 'chamados', TiGrantPolicy::OPERATE_CHAMADOS)
                || $this->grantAtLeast($user, TiGrantPolicy::SCOPE, 'meus_chamados', TiGrantPolicy::PORTAL_CHAMADO),
            'create_chamado' => $this->tiCanCreateChamado($user),
            default => false,
        };
    }

    private function tiCanCreateChamado(User $user): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        return $this->grantAtLeast($user, TiGrantPolicy::SCOPE, 'meus_chamados', TiGrantPolicy::PORTAL_CHAMADO)
            || $this->grantAtLeast($user, TiGrantPolicy::SCOPE, 'catalogo', TiGrantPolicy::PORTAL_CHAMADO)
            || $this->grantAtLeast($user, TiGrantPolicy::SCOPE, 'chamados', TiGrantPolicy::OPERATE_CHAMADOS);
    }
}
