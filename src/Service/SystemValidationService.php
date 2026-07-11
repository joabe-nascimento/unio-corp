<?php

namespace App\Service;

use App\Dev\DevSeedEmails;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\ProductGrantAccess;
use App\Service\Organismo\OrganismoFeature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class SystemValidationService
{
    private const SEED_EMAILS = [
        DevSeedEmails::JOABE,
        DevSeedEmails::RENATA,
        DevSeedEmails::LUCAS,
        DevSeedEmails::ANA_PAULA,
    ];

    /** @var array<string, array<string, bool>> */
    private const ROUTE_EXPECTATIONS = [
        DevSeedEmails::LUCAS => [
            'app_pessoas' => true,
            'app_pessoas_membro_novo' => false,
            'app_core_projetos' => false,
            'app_core_tarefa_nova' => false,
        ],
        DevSeedEmails::RENATA => [
            'app_pessoas' => true,
            'app_pessoas_membro_novo' => true,
            'app_core_projetos' => true,
            'app_core_tarefa_nova' => true,
            'app_core_tarefa_editar' => true,
            'app_core_tarefa_excluir' => true,
        ],
        DevSeedEmails::ANA_PAULA => [
            'app_pessoas' => true,
            'app_pessoas_membro_novo' => false,
            'app_core_projetos' => true,
            'app_core_tarefa_nova' => true,
        ],
        DevSeedEmails::JOABE => [
            'app_core_projetos' => true,
            'app_pessoas_membro_novo' => true,
        ],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private ProductGrantAccess $grants,
        private PermissionService $permissions,
        private NavigationService $navigation,
        private WorkspaceService $workspace,
        private OrganismoFeature $organismo,
        private RouterInterface $router,
        private TokenStorageInterface $tokenStorage,
    ) {}

    public function validate(): SystemValidationResult
    {
        $failures = [];
        $reports = [];

        try {
            $this->em->getConnection()->executeQuery('SELECT 1');
            $reports[] = 'Banco de dados: conectado';
        } catch (\Throwable $e) {
            return SystemValidationResult::fail(['Banco de dados indisponível: ' . $e->getMessage()]);
        }

        $coreRoutes = $this->organismo->isEnabled()
            ? array_merge(self::CORE_ROUTES, self::ORGANISMO_CORE_ROUTES)
            : self::CORE_ROUTES;

        foreach ($coreRoutes as $route) {
            if ($this->router->getRouteCollection()->get($route) === null) {
                $failures[] = sprintf('Rota ausente: %s', $route);
            }
        }
        if ($failures === []) {
            $reports[] = sprintf('Rotas core: %d registradas', \count($coreRoutes));
        }

        $missingSeeds = [];
        foreach (self::SEED_EMAILS as $email) {
            if (!$this->findSeedUser($email)) {
                $missingSeeds[] = $email;
            }
        }
        if ($missingSeeds !== []) {
            $failures[] = 'Usuários seed ausentes: ' . implode(', ', $missingSeeds)
                . ' (rode php bin/console app:seed-users)';
        } else {
            $reports[] = 'Usuários seed: OK';
        }

        if (!$this->organismo->isEnabled()) {
            foreach (self::ROUTE_EXPECTATIONS as $email => $routes) {
                $user = $this->findSeedUser($email);
                if (!$user) {
                    continue;
                }

                $this->authenticateAs($user);

                foreach ($routes as $route => $expected) {
                    $actual = $this->grants->isRouteAllowed($user, $route);
                    if ($actual !== $expected) {
                        $failures[] = sprintf(
                            '%s — %s: esperado %s, obteve %s',
                            $email,
                            $route,
                            $expected ? 'permitido' : 'bloqueado',
                            $actual ? 'permitido' : 'bloqueado',
                        );
                    }
                }
            }

            $membro = $this->findSeedUser(DevSeedEmails::LUCAS);
            if ($membro) {
                $this->authenticateAs($membro);
                if ($this->permissions->canManagePermissions($membro)) {
                    $failures[] = DevSeedEmails::LUCAS . ' não deve gerenciar permissões globais';
                }
                if ($this->grants->grantAtLeast($membro, 'product_pessoas', 'membros', 'GESTOR_EQUIPE')) {
                    $failures[] = DevSeedEmails::LUCAS . ' não deve criar membros';
                }
                if ($this->navigation->showProjetosMetas($membro)) {
                    $failures[] = DevSeedEmails::LUCAS . ' não deve ver Projetos e Metas';
                }
            }

            $gestor = $this->findSeedUser(DevSeedEmails::RENATA);
            if ($gestor) {
                $this->authenticateAs($gestor);
                if (!$this->permissions->canManagePermissions($gestor, 'product_pessoas')) {
                    $failures[] = DevSeedEmails::RENATA . ' deve gerenciar permissões em Pessoas';
                }
                if (!$this->grants->grantAtLeast($gestor, 'product_pessoas', 'membros', 'GESTOR_EQUIPE')) {
                    $failures[] = DevSeedEmails::RENATA . ' deve poder criar membros';
                }
            }

            $tenant = $this->findSeedUser(DevSeedEmails::JOABE);
            if ($tenant) {
                $empresas = $this->workspace->getAvailableEmpresas($tenant);
                if (\count($empresas) < 2) {
                    $failures[] = DevSeedEmails::JOABE . ' deve ver múltiplas empresas no workspace';
                } else {
                    $reports[] = sprintf('Workspace multi-clínica: %d empresas', \count($empresas));
                }
            }

            $gestorEmpresas = $gestor ? $this->workspace->getAvailableEmpresas($gestor) : [];
            if ($gestor && \count($gestorEmpresas) !== 1) {
                $failures[] = DevSeedEmails::RENATA . ' deve ter exatamente 1 empresa no workspace';
            }

            if ($gestor && !$this->navigation->showProjetosMetas($gestor)) {
                $failures[] = DevSeedEmails::RENATA . ' deve ver navegação Projetos e Metas';
            }
        } else {
            foreach ([DevSeedEmails::JOABE, DevSeedEmails::RENATA] as $email) {
                $user = $this->findSeedUser($email);
                if (!$user) {
                    continue;
                }

                $empresas = $this->workspace->getAvailableEmpresas($user);
                if (\count($empresas) < 1) {
                    $failures[] = sprintf('%s deve ter clínica ativa vinculada', $email);
                }
            }

            $reports[] = 'Clínica ativa (organismo): OK';
        }

        if ($failures === []) {
            $reports[] = 'Regras de permissão e rotas: OK';
        }

        return $failures === []
            ? SystemValidationResult::pass($reports)
            : SystemValidationResult::fail($failures, $reports);
    }

    private const CORE_ROUTES = [
        'app_cortex',
        'app_cortex_api_payload',
        'app_core_projetos',
        'app_core_projetos_show',
        'app_core_tarefa_nova',
        'app_core_tarefa_editar',
        'app_core_tarefa_excluir',
        'app_core_tarefa_mover',
        'app_pessoas',
        'app_pessoas_membro_novo',
        'app_workspace_select',
        'app_workspace_switch',
    ];

    /** @var list<string> */
    private const ORGANISMO_CORE_ROUTES = [
        'app_pulso',
    ];

    private function findSeedUser(string $email): ?User
    {
        $user = $this->userRepo->findOneBy(['email' => $email]);
        if ($user instanceof User) {
            return $user;
        }

        $legacy = DevSeedEmails::LEGACY[$email] ?? null;
        if ($legacy === null) {
            return null;
        }

        $legacyUser = $this->userRepo->findOneBy(['email' => $legacy]);

        return $legacyUser instanceof User ? $legacyUser : null;
    }

    private function authenticateAs(User $user): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
