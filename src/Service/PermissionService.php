<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use App\Entity\UserProductGrant;
use App\Repository\UserProductGrantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Escopos de permissão por hub/produto.
 * Membros vêm do banco (empresa ativa); grants do banco com fallback em DEFAULT_GRANTS.
 * Tenant não aparece na matriz: acesso total implícito.
 */
class PermissionService
{
    /** @var list<array{id: string, label: string, class: string, nivel: int, description: string}> */
    public const ASSIGNABLE_PROFILES = [
        ['id' => 'MEMBRO', 'label' => 'Membro', 'class' => 'membro', 'nivel' => 1, 'description' => 'Acesso de participação: visualiza e usa o produto, sem gerenciar pessoas ou configurações.'],
        ['id' => 'SUPERVISOR_EQUIPE', 'label' => 'Supervisor de Equipe', 'class' => 'supervisor-equipe', 'nivel' => 2, 'description' => 'Coordena a equipe no produto: acompanha entregas, aprova ações do time e orienta o dia a dia.'],
        ['id' => 'SUPERVISOR', 'label' => 'Supervisor Geral', 'class' => 'supervisor', 'nivel' => 3, 'description' => 'Supervisiona várias equipes ou frentes do hub, com visão ampla de processos e indicadores.'],
        ['id' => 'GESTOR_EQUIPE', 'label' => 'Gestor de Equipe', 'class' => 'gestor-equipe', 'nivel' => 4, 'description' => 'Gerencia membros e permissões da equipe nos produtos em que atua.'],
        ['id' => 'GESTOR', 'label' => 'Gestor', 'class' => 'gestor', 'nivel' => 5, 'description' => 'Controle amplo do produto ou módulo: configurações, acessos e operação completa da área.'],
    ];

    /** Nível numérico do perfil assignável (para comparar grants). */
    public static function profileNivel(string $profileId): int
    {
        foreach (self::ASSIGNABLE_PROFILES as $profile) {
            if ($profile['id'] === $profileId) {
                return $profile['nivel'];
            }
        }

        return 0;
    }

    public static function profileLabel(string $profileId): string
    {
        foreach (self::ASSIGNABLE_PROFILES as $profile) {
            if ($profile['id'] === $profileId) {
                return $profile['label'];
            }
        }

        return $profileId;
    }

    public static function profileClass(string $profileId): string
    {
        foreach (self::ASSIGNABLE_PROFILES as $profile) {
            if ($profile['id'] === $profileId) {
                return $profile['class'];
            }
        }

        return 'default';
    }

    /** Painel/aba Permissões — perfil global ou grant ≥ Gestor de Equipe no escopo. */
    public function canManagePermissions(User $user, ?string $scope = null): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        if (\in_array($user->getPerfil(), ['GESTOR', 'GESTOR_EQUIPE'], true)) {
            return true;
        }

        if ($scope !== null) {
            return $this->userHasManageGrantInScope($user, $scope);
        }

        foreach (array_keys(self::SCOPES) as $scopeId) {
            if ($this->userHasManageGrantInScope($user, $scopeId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Editor com grant granular só pode salvar escopos em que é gestor.
     *
     * @param array<string, string> $grantsMap keys "scope:productId" => perfil_id
     */
    public function canEditorSaveGrants(User $editor, array $grantsMap): bool
    {
        if ($this->canManagePermissions($editor)) {
            if ($editor->isTenant() || \in_array($editor->getPerfil(), ['GESTOR', 'GESTOR_EQUIPE'], true)) {
                return true;
            }
        } else {
            return false;
        }

        $scopesTouched = [];
        foreach ($grantsMap as $key => $perfilGrant) {
            if (!\is_string($key) || !str_contains($key, ':')) {
                continue;
            }
            [$scope] = explode(':', $key, 2);
            $scopesTouched[$scope] = true;
        }

        foreach (array_keys($scopesTouched) as $scope) {
            if (!$this->userHasManageGrantInScope($editor, $scope)) {
                return false;
            }
        }

        return $scopesTouched !== [];
    }

    private function userHasManageGrantInScope(User $user, string $scope): bool
    {
        if (!isset(self::SCOPES[$scope])) {
            return false;
        }

        $minLevel = self::profileNivel('GESTOR_EQUIPE');
        foreach (self::SCOPES[$scope]['products'] as $product) {
            $grant = $this->grantRepo->findOneForUserScopeProduct($user, $scope, $product['id']);
            if ($grant && $grant->getPerfilGrant() !== '' && self::profileNivel($grant->getPerfilGrant()) >= $minLevel) {
                return true;
            }
        }

        return false;
    }

    private const NO_ACCESS_DESCRIPTION = 'Sem permissão neste produto ou hub — o membro não consegue acessar a área.';

    /** @var array<string, array{equipe: string, cargo: string}> */
    private const MEMBER_META = [
        'gestor@huplex.dev' => ['equipe' => 'PMO', 'cargo' => 'Gestor de Operações'],
        'gestor.eq@huplex.dev' => ['equipe' => 'Squad Backend', 'cargo' => 'Gestor de Equipe'],
        'supervisor@huplex.dev' => ['equipe' => '—', 'cargo' => 'Supervisor Geral'],
        'sup.eq@huplex.dev' => ['equipe' => 'Obras e Projetos', 'cargo' => 'Supervisor de Campo'],
        'membro@huplex.dev' => ['equipe' => 'Design & Marca', 'cargo' => 'Analista'],
    ];

    /** @var array<string, array{label: string, subtitle: string, products: list<array{id: string, label: string}>}> */
    public const SCOPES = [
        'hub_operacoes' => [
            'label' => 'Hub Operações',
            'subtitle' => 'Permissões por produto deste hub',
            'products' => [
                ['id' => 'rh', 'label' => 'Recursos Humanos'],
                ['id' => 'pessoas', 'label' => 'Gestão de Pessoas'],
                ['id' => 'engenharia', 'label' => 'Obras e Projetos'],
            ],
        ],
        'hub_talentos' => [
            'label' => 'Hub de Talentos',
            'subtitle' => 'Permissões por produto deste hub',
            'products' => [
                ['id' => 'banco', 'label' => 'Banco de Talentos'],
                ['id' => 'vagas', 'label' => 'Vagas'],
                ['id' => 'trilhas', 'label' => 'Trilhas de Carreira'],
                ['id' => 'mentorias', 'label' => 'Mentorias'],
            ],
        ],
        'hub_maturidade' => [
            'label' => 'Hub de Maturidade',
            'subtitle' => 'Permissões por produto deste hub',
            'products' => [
                ['id' => 'avaliacao', 'label' => 'Avaliação'],
                ['id' => 'plano', 'label' => 'Plano de Ação'],
                ['id' => 'historico', 'label' => 'Histórico'],
                ['id' => 'radar', 'label' => 'Radar'],
            ],
        ],
        'hub_admin' => [
            'label' => 'Administração',
            'subtitle' => 'Permissões da plataforma',
            'products' => [
                ['id' => 'usuarios', 'label' => 'Usuários'],
                ['id' => 'empresas', 'label' => 'Empresas'],
                ['id' => 'configuracoes', 'label' => 'Configurações'],
            ],
        ],
        'product_rh' => [
            'label' => 'Recursos Humanos',
            'subtitle' => 'Permissões por área do módulo',
            'products' => [
                ['id' => 'funcionarios', 'label' => 'Funcionários'],
                ['id' => 'admissoes', 'label' => 'Admissões'],
                ['id' => 'ferias', 'label' => 'Férias'],
                ['id' => 'folha', 'label' => 'Folha'],
            ],
        ],
        'product_pessoas' => [
            'label' => 'Gestão de Pessoas',
            'subtitle' => 'Permissões por área do módulo',
            'products' => [
                ['id' => 'membros', 'label' => 'Membros'],
                ['id' => 'equipes', 'label' => 'Equipes'],
                ['id' => 'cargos', 'label' => 'Cargos'],
                ['id' => 'avaliacao', 'label' => 'Avaliação'],
            ],
        ],
        'product_engenharia' => [
            'label' => 'Obras e Projetos',
            'subtitle' => 'Permissões por área do módulo',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos'],
                ['id' => 'cronograma', 'label' => 'Cronograma'],
                ['id' => 'orcamentos', 'label' => 'Orçamentos'],
                ['id' => 'equipes', 'label' => 'Equipes de Campo'],
            ],
        ],
        'product_publicidade' => [
            'label' => 'Marca e Comunicação',
            'subtitle' => 'Permissões por área do módulo',
            'products' => [
                ['id' => 'campanhas', 'label' => 'Campanhas'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'criativos', 'label' => 'Criativos'],
                ['id' => 'metricas', 'label' => 'Métricas'],
            ],
        ],
        'product_core' => [
            'label' => 'Projetos e Metas',
            'subtitle' => 'Quadro de desenvolvimento da plataforma Unio',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos e Kanban'],
                ['id' => 'metas', 'label' => 'Metas'],
            ],
        ],
    ];

    /** @var list<array{id: string, label: string, scope: string, products: list<array{id: string, label: string}>}> */
    public const ALL_HUB_GROUPS = [
        [
            'id' => 'hub_operacoes',
            'label' => 'Hub Operações',
            'scope' => 'hub_operacoes',
            'products' => [
                ['id' => 'rh', 'label' => 'Recursos Humanos'],
                ['id' => 'pessoas', 'label' => 'Gestão de Pessoas'],
                ['id' => 'engenharia', 'label' => 'Obras e Projetos'],
            ],
        ],
        [
            'id' => 'hub_talentos',
            'label' => 'Hub de Talentos',
            'scope' => 'hub_talentos',
            'products' => [
                ['id' => 'banco', 'label' => 'Banco de Talentos'],
                ['id' => 'vagas', 'label' => 'Vagas'],
                ['id' => 'trilhas', 'label' => 'Trilhas de Carreira'],
                ['id' => 'mentorias', 'label' => 'Mentorias'],
            ],
        ],
        [
            'id' => 'hub_maturidade',
            'label' => 'Hub de Maturidade',
            'scope' => 'hub_maturidade',
            'products' => [
                ['id' => 'avaliacao', 'label' => 'Avaliação'],
                ['id' => 'plano', 'label' => 'Plano de Ação'],
                ['id' => 'historico', 'label' => 'Histórico'],
                ['id' => 'radar', 'label' => 'Radar'],
            ],
        ],
        [
            'id' => 'hub_admin',
            'label' => 'Administração',
            'scope' => 'hub_admin',
            'products' => [
                ['id' => 'usuarios', 'label' => 'Usuários'],
                ['id' => 'empresas', 'label' => 'Empresas'],
                ['id' => 'configuracoes', 'label' => 'Configurações'],
            ],
        ],
        [
            'id' => 'product_rh',
            'label' => 'Recursos Humanos',
            'scope' => 'product_rh',
            'products' => [
                ['id' => 'funcionarios', 'label' => 'Funcionários'],
                ['id' => 'admissoes', 'label' => 'Admissões'],
                ['id' => 'ferias', 'label' => 'Férias'],
                ['id' => 'folha', 'label' => 'Folha'],
            ],
        ],
        [
            'id' => 'product_pessoas',
            'label' => 'Gestão de Pessoas',
            'scope' => 'product_pessoas',
            'products' => [
                ['id' => 'membros', 'label' => 'Membros'],
                ['id' => 'equipes', 'label' => 'Equipes'],
                ['id' => 'cargos', 'label' => 'Cargos'],
                ['id' => 'avaliacao', 'label' => 'Avaliação'],
            ],
        ],
        [
            'id' => 'product_engenharia',
            'label' => 'Obras e Projetos',
            'scope' => 'product_engenharia',
            'products' => [
                ['id' => 'projetos', 'label' => 'Projetos'],
                ['id' => 'cronograma', 'label' => 'Cronograma'],
                ['id' => 'orcamentos', 'label' => 'Orçamentos'],
                ['id' => 'equipes', 'label' => 'Equipes de Campo'],
            ],
        ],
        [
            'id' => 'product_publicidade',
            'label' => 'Marca e Comunicação',
            'scope' => 'product_publicidade',
            'products' => [
                ['id' => 'campanhas', 'label' => 'Campanhas'],
                ['id' => 'clientes', 'label' => 'Clientes'],
                ['id' => 'criativos', 'label' => 'Criativos'],
                ['id' => 'metricas', 'label' => 'Métricas'],
            ],
        ],
    ];

    /** @var array<string, array<string, array<string, string>>> scope => member_id => product_id => perfil_id */
    public const DEFAULT_GRANTS = [
        'hub_operacoes' => [
            'gestor' => ['rh' => 'GESTOR', 'pessoas' => 'GESTOR', 'engenharia' => 'GESTOR_EQUIPE'],
            'gestor-eq' => ['rh' => 'GESTOR_EQUIPE', 'pessoas' => 'GESTOR_EQUIPE', 'engenharia' => 'SUPERVISOR'],
            'supervisor' => ['rh' => 'SUPERVISOR', 'pessoas' => 'SUPERVISOR_EQUIPE', 'engenharia' => 'MEMBRO'],
            'sup-eq' => ['rh' => 'SUPERVISOR_EQUIPE', 'pessoas' => 'MEMBRO', 'engenharia' => 'MEMBRO'],
            'membro' => ['rh' => 'MEMBRO', 'pessoas' => 'MEMBRO'],
        ],
        'hub_talentos' => [
            'gestor' => ['banco' => 'GESTOR', 'vagas' => 'GESTOR', 'trilhas' => 'GESTOR_EQUIPE', 'mentorias' => 'GESTOR_EQUIPE'],
            'gestor-eq' => ['banco' => 'GESTOR_EQUIPE', 'vagas' => 'SUPERVISOR', 'trilhas' => 'SUPERVISOR_EQUIPE'],
            'supervisor' => ['banco' => 'SUPERVISOR', 'vagas' => 'SUPERVISOR_EQUIPE'],
            'membro' => ['banco' => 'MEMBRO'],
        ],
        'hub_maturidade' => [
            'gestor' => ['avaliacao' => 'GESTOR', 'plano' => 'GESTOR', 'historico' => 'GESTOR_EQUIPE', 'radar' => 'GESTOR_EQUIPE'],
            'supervisor' => ['avaliacao' => 'SUPERVISOR', 'plano' => 'SUPERVISOR_EQUIPE'],
            'membro' => ['avaliacao' => 'MEMBRO'],
        ],
        'hub_admin' => [
            'gestor' => ['usuarios' => 'GESTOR', 'empresas' => 'GESTOR', 'configuracoes' => 'GESTOR_EQUIPE'],
        ],
        'product_rh' => [
            'gestor' => ['funcionarios' => 'GESTOR', 'admissoes' => 'GESTOR', 'ferias' => 'GESTOR_EQUIPE', 'folha' => 'GESTOR'],
            'supervisor' => ['funcionarios' => 'SUPERVISOR', 'ferias' => 'SUPERVISOR_EQUIPE'],
            'membro' => ['funcionarios' => 'MEMBRO'],
        ],
        'product_pessoas' => [
            'gestor' => ['membros' => 'GESTOR', 'equipes' => 'GESTOR', 'cargos' => 'GESTOR_EQUIPE', 'avaliacao' => 'GESTOR_EQUIPE'],
            'gestor-eq' => ['membros' => 'GESTOR_EQUIPE', 'equipes' => 'SUPERVISOR'],
            'membro' => ['membros' => 'MEMBRO'],
        ],
        'product_engenharia' => [
            'gestor' => ['projetos' => 'GESTOR', 'cronograma' => 'GESTOR_EQUIPE', 'orcamentos' => 'GESTOR', 'equipes' => 'SUPERVISOR'],
            'supervisor' => ['projetos' => 'SUPERVISOR_EQUIPE', 'equipes' => 'SUPERVISOR_EQUIPE'],
        ],
        'product_publicidade' => [
            'gestor' => ['campanhas' => 'GESTOR', 'clientes' => 'GESTOR', 'criativos' => 'GESTOR_EQUIPE', 'metricas' => 'GESTOR_EQUIPE'],
        ],
        'product_core' => [
            'gestor' => ['projetos' => 'GESTOR', 'metas' => 'GESTOR'],
            'gestor-eq' => ['projetos' => 'GESTOR_EQUIPE', 'metas' => 'GESTOR_EQUIPE'],
            'supervisor' => ['projetos' => 'SUPERVISOR', 'metas' => 'SUPERVISOR'],
        ],
    ];

    public function __construct(
        private UserRepository $userRepo,
        private UserProductGrantRepository $grantRepo,
        private WorkspaceService $workspace,
        private Security $security,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getScopeData(string $scope): ?array
    {
        if (!isset(self::SCOPES[$scope])) {
            return null;
        }

        $def = self::SCOPES[$scope];
        $profiles = self::ASSIGNABLE_PROFILES;
        $profileMap = [];
        foreach ($profiles as $p) {
            $profileMap[$p['id']] = $p;
        }

        $empresa = $this->getActiveEmpresa();
        $members = $this->getMembers($empresa);

        $rows = [];
        foreach ($members as $member) {
            $grants = $this->getGrantsForMemberScope($member['id'], $scope, $empresa);
            $allGrants = $this->getAllGrantsForMember($member['id'], $empresa);
            $rows[] = [
                'member' => $member,
                'grants' => $grants,
                'all_grants' => $allGrants,
                'grant_count' => \count($allGrants),
                'scope_summary' => $this->buildScopeGrantSummary($scope, $grants),
            ];
        }

        return [
            'scope' => $scope,
            'label' => $def['label'],
            'subtitle' => $def['subtitle'],
            'products' => $def['products'],
            'profiles' => $profiles,
            'profile_map' => $profileMap,
            'rows' => $rows,
            'all_hubs' => self::ALL_HUB_GROUPS,
            'no_access_description' => self::NO_ACCESS_DESCRIPTION,
        ];
    }

    /**
     * @return array<string, string> keys "scope:productId" => perfil_id
     */
    public function getAllGrantsForMember(string $memberId, ?Empresa $empresa = null): array
    {
        $empresa ??= $this->getActiveEmpresa();
        $user = $this->resolveUserForMemberId($memberId, $empresa);
        if ($user && $this->grantRepo->userHasConfiguredMatrix($user)) {
            return $this->grantRepo->findAllGrantKeysForUser($user);
        }

        if ($user) {
            $dbGrants = $this->grantRepo->findAllGrantKeysForUser($user);
            if ($dbGrants !== []) {
                return $dbGrants;
            }
        }

        $grants = [];
        foreach (self::DEFAULT_GRANTS as $scope => $members) {
            foreach ($members[$memberId] ?? [] as $productId => $profile) {
                $grants[$scope . ':' . $productId] = $profile;
            }
        }

        return $grants;
    }

    /**
     * @return array<string, string> productId => perfil_id
     */
    public function getGrantsForMemberScope(string $memberId, string $scope, ?Empresa $empresa = null): array
    {
        $empresa ??= $this->getActiveEmpresa();
        $user = $this->resolveUserForMemberId($memberId, $empresa);
        if ($user && $this->grantRepo->userHasConfiguredMatrix($user)) {
            return $this->grantRepo->findGrantMapForUserAndScope($user, $scope);
        }

        if ($user) {
            $dbGrants = $this->grantRepo->findGrantMapForUserAndScope($user, $scope);
            if ($dbGrants !== []) {
                return $dbGrants;
            }
        }

        return self::DEFAULT_GRANTS[$scope][$memberId] ?? [];
    }

    /**
     * Persiste grants granulares de um membro (substitui todos os registros do usuário).
     *
     * @param array<string, string> $grantsMap keys "scope:productId" => perfil_id (vazio = sem acesso)
     *
     * @return int número de grants gravados
     */
    public function saveMemberGrants(string $memberId, array $grantsMap, User $editor): int
    {
        if (!$this->canEditorSaveGrants($editor, $grantsMap)) {
            throw new AccessDeniedException('Sem permissão para alterar grants.');
        }

        $empresa = $this->workspace->getActiveEmpresa($editor) ?? $editor->getEmpresa();
        $target = $this->resolveUserForMemberId($memberId, $empresa);
        if (!$target) {
            throw new \InvalidArgumentException('Membro não encontrado nesta empresa.');
        }

        if ($target->getPerfil() === 'TENANT') {
            throw new \InvalidArgumentException('Permissões de tenant não são editáveis.');
        }

        $grantsMap = $this->syncOperacoesHubGrants($grantsMap);

        $this->grantRepo->deleteAllForUser($target);
        $target->getProductGrants()->clear();

        $saved = 0;
        foreach ($grantsMap as $key => $perfilGrant) {
            if (!$perfilGrant) {
                continue;
            }

            if (!\is_string($key) || !str_contains($key, ':')) {
                continue;
            }

            [$scope, $productId] = explode(':', $key, 2);
            if (!$this->isValidGrantTarget($scope, $productId)) {
                continue;
            }

            if (!\in_array($perfilGrant, array_column(self::ASSIGNABLE_PROFILES, 'id'), true)) {
                continue;
            }

            $grant = (new UserProductGrant())
                ->setScope($scope)
                ->setProductId($productId)
                ->setPerfilGrant($perfilGrant);
            $target->addProductGrant($grant);
            $this->em->persist($grant);
            ++$saved;
        }

        $this->em->flush();

        $this->grantRepo->ensureConfiguredMarker($target);

        return $saved;
    }

    /**
     * Alinha hub_operacoes:rh|pessoas|engenharia com os grants de product_* (fonte da verdade na UI).
     *
     * @param array<string, string> $grantsMap
     *
     * @return array<string, string>
     */
    private function syncOperacoesHubGrants(array $grantsMap): array
    {
        $map = $grantsMap;
        $links = [
            'rh' => 'product_rh',
            'pessoas' => 'product_pessoas',
            'engenharia' => 'product_engenharia',
        ];

        foreach ($links as $hubProduct => $productScope) {
            $bestProfile = $this->highestProfileInScope($map, $productScope);
            $hubKey = 'hub_operacoes:' . $hubProduct;
            if ($bestProfile !== null) {
                $map[$hubKey] = $bestProfile;
            } else {
                unset($map[$hubKey]);
            }
        }

        return $map;
    }

    /**
     * @param array<string, string> $grantsMap
     */
    private function highestProfileInScope(array $grantsMap, string $scope): ?string
    {
        if (!isset(self::SCOPES[$scope])) {
            return null;
        }

        $bestLevel = 0;
        $bestProfile = null;
        foreach (self::SCOPES[$scope]['products'] as $product) {
            $profile = $grantsMap[$scope . ':' . $product['id']] ?? '';
            if ($profile === '') {
                continue;
            }
            $level = self::profileNivel($profile);
            if ($level > $bestLevel) {
                $bestLevel = $level;
                $bestProfile = $profile;
            }
        }

        return $bestProfile;
    }

    /**
     * @param array<string, string> $grantsInScope productId => perfil_id
     *
     * @return array{label: string, class: string, description: string}
     */
    public function buildScopeGrantSummary(string $scope, array $grantsInScope): array
    {
        if (!isset(self::SCOPES[$scope])) {
            return ['label' => '—', 'class' => 'none', 'description' => 'Escopo não encontrado.'];
        }

        $values = [];
        foreach (self::SCOPES[$scope]['products'] as $product) {
            $value = $grantsInScope[$product['id']] ?? '';
            if ($value !== '') {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return [
                'label' => 'Sem acesso',
                'class' => 'none',
                'description' => 'Sem permissão neste escopo — produtos desta aba bloqueados.',
            ];
        }

        $unique = array_values(array_unique($values));
        if (\count($unique) === 1) {
            foreach (self::ASSIGNABLE_PROFILES as $profile) {
                if ($profile['id'] === $unique[0]) {
                    return [
                        'label' => $profile['label'],
                        'class' => $profile['class'],
                        'description' => $profile['description'],
                    ];
                }
            }
        }

        return [
            'label' => 'Misto (' . \count($values) . ')',
            'class' => 'default',
            'description' => 'Perfis diferentes entre os produtos desta aba.',
        ];
    }

    private function isValidGrantTarget(string $scope, string $productId): bool
    {
        if (!isset(self::SCOPES[$scope])) {
            return false;
        }

        foreach (self::SCOPES[$scope]['products'] as $product) {
            if ($product['id'] === $productId) {
                return true;
            }
        }

        return false;
    }

    public static function memberIdFromEmail(string $email): string
    {
        $local = explode('@', $email)[0] ?? $email;

        return str_replace('.', '-', $local);
    }

    /**
     * Membros da empresa para busca global e painel de permissões.
     *
     * @return list<array{id: string, nome: string, email: string, initials: string, equipe: string, cargo: string, perfil_global: string, perfil_label: string, perfil_class: string, ficha_id: int|null, user_id: int|null}>
     */
    public function getMembersForSearch(?Empresa $empresa = null): array
    {
        return $this->getMembers($empresa);
    }

    /**
     * @return list<array{id: string, nome: string, email: string, initials: string, equipe: string, cargo: string, perfil_global: string, perfil_label: string, perfil_class: string, ficha_id: int|null, user_id: int|null}>
     */
    private function getMembers(?Empresa $empresa): array
    {
        if ($empresa) {
            $users = $this->userRepo->findBy(['empresa' => $empresa, 'ativo' => true], ['nome' => 'ASC']);
            $members = [];
            foreach ($users as $user) {
                if ($user->getPerfil() === 'TENANT') {
                    continue;
                }
                $members[] = $this->memberFromUser($user);
            }
            if ($members !== []) {
                return $members;
            }
        }

        return $this->getFallbackMembers();
    }

    /**
     * @return list<array{id: string, nome: string, email: string, initials: string, equipe: string, cargo: string, perfil_global: string, perfil_label: string, perfil_class: string, ficha_id: int|null, user_id: int|null}>
     */
    private function getFallbackMembers(): array
    {
        return [
            $this->member('gestor', 'Gestor Oliveira', 'gestor@huplex.dev', 'GESTOR', 'PMO', 'Gestor de Operações', null),
            $this->member('gestor-eq', 'Gestor Costa', 'gestor.eq@huplex.dev', 'GESTOR_EQUIPE', 'Squad Backend', 'Gestor de Equipe', null),
            $this->member('supervisor', 'Supervisor Geral', 'supervisor@huplex.dev', 'SUPERVISOR', '—', 'Supervisor Geral', null),
            $this->member('sup-eq', 'Supervisor Equipe', 'sup.eq@huplex.dev', 'SUPERVISOR_EQUIPE', 'Obras e Projetos', 'Supervisor de Campo', null),
            $this->member('membro', 'Membro Santos', 'membro@huplex.dev', 'MEMBRO', 'Design & Marca', 'Analista', null),
        ];
    }

    private function memberFromUser(User $user): array
    {
        $email = (string) $user->getEmail();
        $meta = self::MEMBER_META[$email] ?? ['equipe' => '—', 'cargo' => $user->getPerfilLabel()];

        return $this->member(
            self::memberIdFromEmail($email),
            (string) $user->getNome(),
            $email,
            $user->getPerfil(),
            $meta['equipe'],
            $meta['cargo'],
            $user->getId(),
        );
    }

    private function resolveUserForMemberId(string $memberId, ?Empresa $empresa): ?User
    {
        if (!$empresa) {
            return null;
        }

        foreach ($this->userRepo->findBy(['empresa' => $empresa, 'ativo' => true]) as $user) {
            if (self::memberIdFromEmail((string) $user->getEmail()) === $memberId) {
                return $user;
            }
        }

        return null;
    }

    private function getActiveEmpresa(): ?Empresa
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->workspace->getActiveEmpresa($user);
    }

    /**
     * @return array{id: string, nome: string, email: string, initials: string, equipe: string, cargo: string, perfil_global: string, perfil_label: string, perfil_class: string, ficha_id: int|null, user_id: int|null}
     */
    private function member(string $id, string $nome, string $email, string $perfil, string $equipe, string $cargo, ?int $userId): array
    {
        $parts = preg_split('/\s+/', trim($nome), 2);
        $initials = mb_strtoupper(mb_substr($parts[0] ?? 'U', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));

        return [
            'id' => $id,
            'nome' => $nome,
            'email' => $email,
            'initials' => $initials ?: 'U',
            'equipe' => $equipe,
            'cargo' => $cargo,
            'perfil_global' => $perfil,
            'perfil_label' => match ($perfil) {
                'MEMBRO' => 'Membro',
                'SUPERVISOR_EQUIPE' => 'Supervisor de Equipe',
                'SUPERVISOR' => 'Supervisor Geral',
                'GESTOR_EQUIPE' => 'Gestor de Equipe',
                'GESTOR' => 'Gestor',
                default => $perfil,
            },
            'perfil_class' => match ($perfil) {
                'MEMBRO' => 'membro',
                'SUPERVISOR_EQUIPE' => 'supervisor-equipe',
                'SUPERVISOR' => 'supervisor',
                'GESTOR_EQUIPE' => 'gestor-equipe',
                'GESTOR' => 'gestor',
                default => 'default',
            },
            'ficha_id' => $userId,
            'user_id' => $userId,
        ];
    }
}
