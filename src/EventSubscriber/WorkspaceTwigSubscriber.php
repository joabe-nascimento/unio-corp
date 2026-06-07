<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ChatService;
use App\Service\GlobalSearchService;
use App\Service\NavigationService;
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
        $this->twig->addGlobal('empresa', $empresa);
        $this->twig->addGlobal('empresas', $this->workspaceService->getAvailableEmpresas($user));

        foreach ($this->navigation->getNavGlobals($user, $route) as $name => $value) {
            $this->twig->addGlobal($name, $value);
        }

        $this->twig->addGlobal(
            'nav_notifications_unread',
            $empresa !== null ? $this->notifications->countUnread($empresa, $user) : 0,
        );
        $this->twig->addGlobal('nav_chat_unread', $this->chat->getUnreadCount($user, $empresa ?? $user->getEmpresa()));
        $this->twig->addGlobal(
            'global_search_members_json',
            json_encode(
                $this->globalSearch->getMemberItems($user, $empresa),
                \JSON_UNESCAPED_UNICODE,
            ) ?: '[]',
        );
        $this->twig->addGlobal('platform_modules', $this->navigation->getPlatformModules($user));

        $pageBack = $this->pageBackResolver->resolve(\is_string($route) ? $route : null);
        $this->twig->addGlobal('page_back_route', $pageBack['route'] ?? null);
        $this->twig->addGlobal('page_back_route_params', $pageBack['params'] ?? []);
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
