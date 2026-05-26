<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\FuncionarioRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Checklist de primeiros passos pós-login (workspace, RH, convite, hub).
 */
class OnboardingProgressService
{
    public const SESSION_HUB_VISITED = 'onboarding.hub_visited';

    public function __construct(
        private RequestStack $requestStack,
        private NavigationService $navigation,
        private WelcomeService $welcome,
        private FuncionarioRepository $funcionarioRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * @return array{
     *     steps: list<array{id: string, label: string, hint: string, icon: string, route: string, route_params: array<string, mixed>, done: bool}>,
     *     completed: int,
     *     total: int,
     *     percent: int,
     *     complete: bool,
     *     visible: bool
     * }
     */
    public function build(User $user, ?Empresa $empresa, int $empresasCount = 0): array
    {
        $steps = $this->resolveSteps($user, $empresa, $empresasCount);
        $total = \count($steps);
        $completed = \count(array_filter($steps, static fn (array $s): bool => $s['done']));

        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 100;
        $complete = $total > 0 && $completed === $total;

        return [
            'steps' => $steps,
            'completed' => $completed,
            'total' => $total,
            'percent' => $percent,
            'complete' => $complete,
            'visible' => $total > 0 && !$complete,
        ];
    }

    public function markHubVisited(): void
    {
        $this->requestStack->getSession()->set(self::SESSION_HUB_VISITED, true);
    }

    private function hasVisitedHub(): bool
    {
        return (bool) $this->requestStack->getSession()->get(self::SESSION_HUB_VISITED, false);
    }

    /**
     * @return list<array{id: string, label: string, hint: string, icon: string, route: string, route_params: array<string, mixed>, done: bool}>
     */
    private function resolveSteps(User $user, ?Empresa $empresa, int $empresasCount): array
    {
        $steps = [];

        $steps[] = [
            'id' => 'workspace',
            'label' => 'Confirmar área de trabalho',
            'hint' => $empresa
                ? ($empresasCount > 1 ? 'Você pode trocar de empresa quando precisar.' : 'Empresa ativa nesta sessão.')
                : 'Selecione a empresa em que vai trabalhar.',
            'icon' => 'fa-building',
            'route' => 'app_workspace_select',
            'route_params' => $empresa && $empresasCount > 1 ? ['force' => 1] : [],
            'done' => $empresa !== null,
        ];

        if ($empresa !== null && $this->navigation->showModuloRh($user)) {
            $funcCount = $this->funcionarioRepository->countByEmpresa($empresa);
            $steps[] = [
                'id' => 'funcionario',
                'label' => 'Cadastrar primeiro colaborador',
                'hint' => $funcCount > 0
                    ? 'Colaboradores já registrados no RH.'
                    : 'Adicione alguém da equipe no módulo de RH.',
                'icon' => 'fa-user-plus',
                'route' => $funcCount > 0 ? 'app_rh_funcionarios' : 'app_rh_admissoes_nova',
                'route_params' => [],
                'done' => $funcCount > 0,
            ];
        }

        if ($empresa !== null && $this->navigation->showPlataforma($user)) {
            $userCount = $this->userRepository->countByEmpresa($empresa);
            $steps[] = [
                'id' => 'invite_user',
                'label' => 'Convidar outro usuário',
                'hint' => $userCount >= 2
                    ? 'Sua empresa já tem mais de um usuário ativo.'
                    : 'Crie acesso para um colega na administração.',
                'icon' => 'fa-user-group',
                'route' => 'app_admin_usuarios',
                'route_params' => $userCount < 2 ? ['open_novo' => 1] : [],
                'done' => $userCount >= 2,
            ];
        }

        $hubs = $this->welcome->getHubsForUser($user);
        if ($hubs !== []) {
            $firstHub = $hubs[0];
            $steps[] = [
                'id' => 'hub',
                'label' => 'Explorar um hub',
                'hint' => $this->hasVisitedHub()
                    ? 'Você já visitou um hub da plataforma.'
                    : sprintf('Comece pelo %s ou outro hub disponível.', $firstHub['title']),
                'icon' => $firstHub['icon'] ?? 'fa-layer-group',
                'route' => $firstHub['route'],
                'route_params' => [],
                'done' => $this->hasVisitedHub(),
            ];
        }

        return $steps;
    }
}
