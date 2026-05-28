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

        if (!isset(self::MANAGE_ROUTES[$routeName])) {
            return true;
        }

        $rule = self::MANAGE_ROUTES[$routeName];

        return $this->grantAtLeast($user, $rule['scope'], $rule['product'], $rule['min']);
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

        if (preg_match('/^app_(talentos|maturidade|engenharia|publicidade|comercial|beneficios|academy|parceiros|financeiro|compliance|analytics|juridico|clima|sst|comunicacao|hub_portal|hub_recrutamento|esg|suprimentos|ti|expansao|qualidade|facilities|patrimonio|conhecimento|integracoes|customer_success|inovacao|holdings)/', $routeName) === 1) {
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
}
