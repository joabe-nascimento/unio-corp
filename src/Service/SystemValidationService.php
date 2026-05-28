<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\ProductGrantAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class SystemValidationService
{
    private const SEED_EMAILS = [
        'tenant@unio.dev',
        'gestor@unio.dev',
        'membro@unio.dev',
        'supervisor@unio.dev',
    ];

    /** @var array<string, array<string, bool>> */
    private const ROUTE_EXPECTATIONS = [
        'membro@unio.dev' => [
            'app_pessoas' => true,
            'app_pessoas_membro_novo' => false,
            'app_core_projetos' => false,
            'app_core_tarefa_nova' => false,
        ],
        'gestor@unio.dev' => [
            'app_pessoas' => true,
            'app_pessoas_membro_novo' => true,
            'app_core_projetos' => true,
            'app_core_tarefa_nova' => true,
            'app_core_tarefa_editar' => true,
            'app_core_tarefa_excluir' => true,
        ],
        'supervisor@unio.dev' => [
            'app_pessoas' => true,
            'app_pessoas_membro_novo' => false,
            'app_core_projetos' => true,
            'app_core_tarefa_nova' => true,
        ],
        'tenant@unio.dev' => [
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

        foreach (self::CORE_ROUTES as $route) {
            if ($this->router->getRouteCollection()->get($route) === null) {
                $failures[] = sprintf('Rota ausente: %s', $route);
            }
        }
        if ($failures === []) {
            $reports[] = sprintf('Rotas core: %d registradas', \count(self::CORE_ROUTES));
        }

        $missingSeeds = [];
        foreach (self::SEED_EMAILS as $email) {
            if (!$this->userRepo->findOneBy(['email' => $email])) {
                $missingSeeds[] = $email;
            }
        }
        if ($missingSeeds !== []) {
            $failures[] = 'Usuários seed ausentes: ' . implode(', ', $missingSeeds)
                . ' (rode php bin/console app:seed-users)';
        } else {
            $reports[] = 'Usuários seed: OK';
        }

        foreach (self::ROUTE_EXPECTATIONS as $email => $routes) {
            $user = $this->userRepo->findOneBy(['email' => $email]);
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

        $membro = $this->userRepo->findOneBy(['email' => 'membro@unio.dev']);
        if ($membro) {
            $this->authenticateAs($membro);
            if ($this->permissions->canManagePermissions($membro)) {
                $failures[] = 'membro@unio.dev não deve gerenciar permissões globais';
            }
            if ($this->grants->grantAtLeast($membro, 'product_pessoas', 'membros', 'GESTOR_EQUIPE')) {
                $failures[] = 'membro@unio.dev não deve criar membros';
            }
            if ($this->navigation->showProjetosMetas($membro)) {
                $failures[] = 'membro@unio.dev não deve ver Projetos e Metas';
            }
        }

        $gestor = $this->userRepo->findOneBy(['email' => 'gestor@unio.dev']);
        if ($gestor) {
            $this->authenticateAs($gestor);
            if (!$this->permissions->canManagePermissions($gestor, 'product_pessoas')) {
                $failures[] = 'gestor@unio.dev deve gerenciar permissões em Pessoas';
            }
            if (!$this->grants->grantAtLeast($gestor, 'product_pessoas', 'membros', 'GESTOR_EQUIPE')) {
                $failures[] = 'gestor@unio.dev deve poder criar membros';
            }
        }

        $tenant = $this->userRepo->findOneBy(['email' => 'tenant@unio.dev']);
        if ($tenant) {
            $empresas = $this->workspace->getAvailableEmpresas($tenant);
            if (\count($empresas) < 2) {
                $failures[] = 'tenant@unio.dev deve ver múltiplas empresas no workspace';
            } else {
                $reports[] = sprintf('Tenant workspace: %d empresas', \count($empresas));
            }
        }

        $gestorEmpresas = $gestor ? $this->workspace->getAvailableEmpresas($gestor) : [];
        if ($gestor && \count($gestorEmpresas) !== 1) {
            $failures[] = 'gestor@unio.dev deve ter exatamente 1 empresa no workspace';
        }

        if ($gestor && !$this->navigation->showProjetosMetas($gestor)) {
            $failures[] = 'gestor@unio.dev deve ver navegação Projetos e Metas';
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

    private function authenticateAs(User $user): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
