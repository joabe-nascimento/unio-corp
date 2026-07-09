<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\TiGrantService;
use App\Service\ChatService;
use App\Service\GlobalSearchService;
use App\Service\NavigationService;
use App\Service\OnboardingTourService;
use App\Service\Organismo\ClinicNavBadgeService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Organismo\OrganismoFeature;
use App\Service\Organismo\OrganismoPraticaRegistry;
use App\Service\PageBackResolver;
use App\Service\PlatformNotificationService;
use App\Service\WorkspaceService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class WorkspaceTwigSubscriber implements EventSubscriberInterface
{
    private const SKIP_ROUTE_PREFIXES = ['_profiler', '_wdt'];

    private const SKIP_ROUTES = [
        'app_home',
        'app_login',
        'app_logout',
        'app_sessao_encerrar',
        'app_register',
        'app_forgot_password',
        'app_reset_password',
    ];

    public function __construct(
        private Environment $twig,
        private TokenStorageInterface $tokenStorage,
        private WorkspaceService $workspaceService,
        private NavigationService $navigation,
        private PlatformNotificationService $notifications,
        private ChatService $chat,
        private GlobalSearchService $globalSearch,
        private PageBackResolver $pageBackResolver,
        private TiGrantService $tiGrants,
        private OnboardingTourService $onboardingTour,
        private OrganismoFeature $organismoFeature,
        private OrganismoCopyService $organismoCopy,
        private OrganismoPraticaRegistry $praticaRegistry,
        private ClinicNavBadgeService $clinicNavBadges,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onController'];
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if ($this->shouldSkip($route)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$user instanceof User) {
            return;
        }

        $empresa = $this->workspaceService->getActiveEmpresa($user);
        $empresas = $this->workspaceService->getAvailableEmpresas($user);
        $this->twig->addGlobal('empresa', $empresa);
        $this->twig->addGlobal('empresas', $empresas);

        foreach ($this->navigation->getNavGlobals($user, $route) as $name => $value) {
            $this->twig->addGlobal($name, $value);
        }

        $notificationsUnread = $empresa !== null ? $this->notifications->countUnread($empresa, $user) : 0;
        $this->twig->addGlobal('nav_notifications_unread', $notificationsUnread);
        $chatUnread = $this->chat->getUnreadCount($user, $empresa ?? $user->getEmpresa());
        $this->twig->addGlobal('nav_chat_unread', $chatUnread);
        $this->twig->addGlobal(
            'global_search_members_json',
            json_encode(
                $this->globalSearch->getMemberItems($user, $empresa),
                \JSON_UNESCAPED_UNICODE,
            ) ?: '[]',
        );
        $this->twig->addGlobal('platform_modules', $this->navigation->getPlatformModules($user));
        $this->twig->addGlobal(
            'mobile_shell_nav',
            $this->navigation->getMobileShellNav(
                $user,
                \is_string($route) ? $route : null,
                $chatUnread,
                $notificationsUnread,
            ),
        );
        $this->twig->addGlobal('organismo', [
            'enabled' => $this->organismoFeature->isEnabled(),
            'pulso_home' => $this->organismoFeature->isPulsoHome(),
            'copy' => $this->organismoCopy->getGlobals(),
        ]);
        $this->twig->addGlobal('org_clinic', $this->organismoFeature->isEnabled());
        $this->twig->addGlobal(
            'nav_organismo_practices',
            $this->organismoFeature->isEnabled()
                ? $this->praticaRegistry->getGroupedForUser($user)
                : [],
        );
        $activePractice = $this->organismoFeature->isEnabled()
            ? $this->praticaRegistry->resolveActiveForRoute($user, \is_string($route) ? $route : null)
            : null;
        $this->twig->addGlobal('nav_active_practice', $activePractice);
        $this->twig->addGlobal(
            'clinic_nav_badges',
            $this->organismoFeature->isEnabled()
                ? $this->clinicNavBadges->forEmpresa($empresa)
                : [],
        );
        $this->twig->addGlobal(
            'nav_home_route',
            $this->organismoFeature->isEnabled()
                ? ($this->organismoFeature->isPulsoHome() ? 'app_pulso' : 'app_dashboard')
                : 'app_dashboard',
        );
        $tourConfig = $this->onboardingTour->build($user, $empresa, \count($empresas), \is_string($route) ? $route : null);
        $this->twig->addGlobal(
            'onboarding_tour_json',
            json_encode($tourConfig, \JSON_UNESCAPED_UNICODE) ?: '{}',
        );
        $this->twig->addGlobal('onboarding_help_flows', $tourConfig['flows'] ?? []);

        $pageBack = $this->pageBackResolver->resolve(
            \is_string($route) ? $route : null,
            $this->extractRouteParams($event->getRequest()),
        );
        if (\is_string($route) && $route === 'app_ti_chamado_show' && !$this->tiGrants->canOperateChamados($user)) {
            $pageBack = ['route' => 'app_ti_meus_chamados', 'params' => []];
        }
        $this->twig->addGlobal('page_back_route', $pageBack['route'] ?? null);
        $this->twig->addGlobal('page_back_route_params', $pageBack['params'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractRouteParams(\Symfony\Component\HttpFoundation\Request $request): array
    {
        $params = [];
        foreach (['id', 'slug', 'uuid'] as $key) {
            if ($request->attributes->has($key)) {
                $params[$key] = $request->attributes->get($key);
            }
        }

        return $params;
    }

    private function shouldSkip(?string $route): bool
    {
        if (!$route || \in_array($route, self::SKIP_ROUTES, true)) {
            return true;
        }

        foreach (self::SKIP_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
