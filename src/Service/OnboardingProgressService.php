<?php



namespace App\Service;



use App\Entity\Empresa;

use App\Entity\User;

use App\Security\ProductGrantAccess;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\SecurityBundle\Security;

use Symfony\Component\HttpFoundation\RequestStack;



/**

 * Checklist de primeiros passos — conclusão persistida no usuário (sobrevive logout/dispositivo).

 */

class OnboardingProgressService

{

    public const SESSION_COMPLETED = 'onboarding.completed_steps';



    /** @var list<string> */

    public const STEP_IDS = ['shell_tour', 'funcionario', 'portal', 'invite_user', 'hub'];



    public function __construct(

        private RequestStack $requestStack,

        private NavigationService $navigation,

        private WelcomeService $welcome,

        private ProductGrantAccess $grants,

        private Security $security,

        private EntityManagerInterface $em,

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

        $this->migrateSessionStepsToUser($user);

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



    public function markStepComplete(string $stepId, ?User $user = null): void

    {

        if (!\in_array($stepId, self::STEP_IDS, true)) {

            return;

        }



        $user ??= $this->resolveUser();

        if ($user === null) {

            $this->markStepCompleteInSession($stepId);



            return;

        }



        $this->migrateSessionStepsToUser($user);



        if (!$user->isOnboardingStepComplete($stepId)) {

            $user->addOnboardingCompletedStep($stepId);

            $this->em->flush();

        }

    }



    public function isStepComplete(string $stepId, ?User $user = null): bool

    {

        $user ??= $this->resolveUser();

        if ($user === null) {

            return $this->isStepCompleteInSession($stepId);

        }



        $this->migrateSessionStepsToUser($user);



        return $user->isOnboardingStepComplete($stepId);

    }



    public function markHubVisited(?User $user = null): void

    {

        $this->markStepComplete('hub', $user);

    }



    /**

     * @return list<array{id: string, label: string, hint: string, icon: string, route: string, route_params: array<string, mixed>, done: bool}>

     */

    private function resolveSteps(User $user, ?Empresa $empresa, int $empresasCount): array

    {

        $steps = [];

        $shellTourDone = $this->isStepComplete('shell_tour', $user);

        $steps[] = [

            'id' => 'shell_tour',

            'label' => 'Conhecer a interface',

            'hint' => $shellTourDone

                ? 'Você concluiu o tour do menu, busca e núcleos.'

                : 'Abra Ajuda no topo da tela e escolha o tour da interface.',

            'icon' => 'fa-route',

            'route' => 'app_dashboard',

            'route_params' => [],

            'done' => $shellTourDone,

        ];



        if ($empresa !== null && $this->canManageAdmissoes($user)) {

            $funcDone = $this->isStepComplete('funcionario', $user);

            $steps[] = [

                'id' => 'funcionario',

                'label' => 'Cadastrar primeiro colaborador',

                'hint' => $funcDone

                    ? 'Você iniciou ou concluiu um cadastro no RH.'

                    : 'Inicie uma admissão no RH para registrar o primeiro colaborador.',

                'icon' => 'fa-user-plus',

                'route' => 'app_rh_admissoes_nova',

                'route_params' => [],

                'done' => $funcDone,

            ];

        }



        if ($empresa !== null && !$this->canManageAdmissoes($user) && $this->grants->grantAtLeast($user, 'product_rh', 'portal', 'MEMBRO')) {

            $portalDone = $this->isStepComplete('portal', $user);

            $steps[] = [

                'id' => 'portal',

                'label' => 'Conhecer o portal do colaborador',

                'hint' => $portalDone

                    ? 'Você já acessou o portal do colaborador.'

                    : 'Veja holerites, férias e comunicados da empresa.',

                'icon' => 'fa-id-badge',

                'route' => 'app_rh_portal',

                'route_params' => [],

                'done' => $portalDone,

            ];

        }



        if ($empresa !== null && $this->navigation->showPlataforma($user)) {

            $inviteDone = $this->isStepComplete('invite_user', $user);

            $steps[] = [

                'id' => 'invite_user',

                'label' => 'Convidar outro usuário',

                'hint' => $inviteDone

                    ? 'Você criou um novo usuário na administração.'

                    : 'Cadastre um colega em Usuários (botão novo usuário).',

                'icon' => 'fa-user-group',

                'route' => 'app_admin_usuarios',

                'route_params' => ['open_novo' => 1],

                'done' => $inviteDone,

            ];

        }



        $hubs = $this->welcome->getHubsForUser($user);

        if ($hubs !== []) {

            $firstHub = $hubs[0];

            $hubDone = $this->isStepComplete('hub', $user);

            $steps[] = [

                'id' => 'hub',

                'label' => 'Explorar um hub',

                'hint' => $hubDone

                    ? 'Você já entrou em um hub da plataforma.'

                    : sprintf('Abra o %s ou outro hub disponível.', $firstHub['title']),

                'icon' => $firstHub['icon'] ?? 'fa-layer-group',

                'route' => $firstHub['route'],

                'route_params' => [],

                'done' => $hubDone,

            ];

        }



        return $steps;

    }



    private function migrateSessionStepsToUser(User $user): void

    {

        /** @var mixed $completed */

        $completed = $this->requestStack->getSession()->get(self::SESSION_COMPLETED, []);

        if (!\is_array($completed) || $completed === []) {

            return;

        }



        $dirty = false;

        foreach ($completed as $stepId) {

            if (!\is_string($stepId) || !\in_array($stepId, self::STEP_IDS, true)) {

                continue;

            }

            if (!$user->isOnboardingStepComplete($stepId)) {

                $user->addOnboardingCompletedStep($stepId);

                $dirty = true;

            }

        }



        if ($dirty) {

            $this->em->flush();

        }



        $this->requestStack->getSession()->remove(self::SESSION_COMPLETED);

    }



    private function markStepCompleteInSession(string $stepId): void

    {

        $session = $this->requestStack->getSession();

        /** @var list<string> $completed */

        $completed = $session->get(self::SESSION_COMPLETED, []);

        if (!\is_array($completed)) {

            $completed = [];

        }



        if (!\in_array($stepId, $completed, true)) {

            $completed[] = $stepId;

            $session->set(self::SESSION_COMPLETED, $completed);

        }

    }



    private function isStepCompleteInSession(string $stepId): bool

    {

        /** @var mixed $completed */

        $completed = $this->requestStack->getSession()->get(self::SESSION_COMPLETED, []);



        return \is_array($completed) && \in_array($stepId, $completed, true);

    }



    private function resolveUser(): ?User

    {

        $user = $this->security->getUser();



        return $user instanceof User ? $user : null;

    }



    private function canManageAdmissoes(User $user): bool

    {

        return $this->grants->grantAtLeast($user, 'product_rh', 'admissoes', 'GESTOR_EQUIPE');

    }

}

