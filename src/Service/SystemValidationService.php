<?php

namespace App\Service;

use App\Clinic\ClinicStaffRole;
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
        DevSeedEmails::CAMILA_RECEPCAO,
        DevSeedEmails::BEATRIZ_ENFERMAGEM,
        DevSeedEmails::ANDRE_MEDICO,
        DevSeedEmails::HELENA_COORDENACAO,
    ];

    /** @var array<string, array<string, bool>> */
    private const CLINIC_ROUTE_EXPECTATIONS = [
        DevSeedEmails::CAMILA_RECEPCAO => [
            'app_pos_operatorio_pacientes' => true,
            'app_pos_operatorio_agenda' => true,
            'app_pos_operatorio_paciente_novo' => true,
            'app_pos_operatorio_contas' => true,
            'app_pos_operatorio_alertas' => false,
            'app_pos_operatorio_questionarios' => false,
            'app_pos_operatorio_protocolos' => false,
            'app_pos_operatorio_config' => false,
            'app_pos_operatorio_relatorios' => false,
        ],
        DevSeedEmails::BEATRIZ_ENFERMAGEM => [
            'app_pos_operatorio_questionarios' => true,
            'app_pos_operatorio_pacientes' => true,
            'app_pos_operatorio_paciente_novo' => false,
            'app_pos_operatorio_contas' => false,
            'app_pos_operatorio_protocolos' => false,
            'app_pos_operatorio_config' => false,
            'app_pos_operatorio_alertas' => false,
        ],
        DevSeedEmails::ANDRE_MEDICO => [
            'app_pos_operatorio_alertas' => true,
            'app_pos_operatorio_paciente_show' => true,
            'app_pos_operatorio_protocolos' => true,
            'app_pos_operatorio_paciente_novo' => false,
            'app_pos_operatorio_contas' => false,
            'app_pos_operatorio_config' => false,
            'app_pos_operatorio_questionarios' => false,
            'app_pos_operatorio_relatorios' => false,
        ],
        DevSeedEmails::HELENA_COORDENACAO => [
            'app_pos_operatorio_relatorios' => true,
            'app_pos_operatorio_config' => true,
            'app_pos_operatorio_pacientes' => false,
            'app_pos_operatorio_alertas' => false,
            'app_pos_operatorio_questionarios' => false,
            'app_pos_operatorio_protocolos' => false,
            'app_pos_operatorio_contas' => false,
        ],
        DevSeedEmails::JOABE => [
            'app_pos_operatorio_pacientes' => true,
            'app_pos_operatorio_config' => true,
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
            ? array_merge(self::CLINIC_CORE_ROUTES, self::ORGANISMO_CORE_ROUTES)
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
            $reports[] = 'Usuários seed clínicos: OK';
        }

        if ($this->organismo->isEnabled()) {
            foreach (self::CLINIC_ROUTE_EXPECTATIONS as $email => $routes) {
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

            $coord = $this->findSeedUser(DevSeedEmails::HELENA_COORDENACAO);
            if ($coord && $coord->getPerfil() !== ClinicStaffRole::COORDENACAO) {
                $failures[] = DevSeedEmails::HELENA_COORDENACAO . ' deve ter perfil COORDENACAO';
            }

            $recepcao = $this->findSeedUser(DevSeedEmails::CAMILA_RECEPCAO);
            if ($recepcao && $recepcao->getPerfil() !== ClinicStaffRole::RECEPCAO) {
                $failures[] = DevSeedEmails::CAMILA_RECEPCAO . ' deve ter perfil RECEPCAO';
            }

            foreach ([DevSeedEmails::JOABE, DevSeedEmails::HELENA_COORDENACAO] as $email) {
                $user = $this->findSeedUser($email);
                if (!$user) {
                    continue;
                }

                $empresas = $this->workspace->getAvailableEmpresas($user);
                if (\count($empresas) < 1 && $email !== DevSeedEmails::JOABE) {
                    $failures[] = sprintf('%s deve ter clínica ativa vinculada', $email);
                }
            }

            $reports[] = 'Clínica ativa (RBAC clínico): OK';
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
    private const CLINIC_CORE_ROUTES = [
        'app_pos_operatorio',
        'app_pos_operatorio_pacientes',
        'app_pos_operatorio_alertas',
        'app_pos_operatorio_questionarios',
        'app_pos_operatorio_protocolos',
        'app_pos_operatorio_relatorios',
        'app_pos_operatorio_config',
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
