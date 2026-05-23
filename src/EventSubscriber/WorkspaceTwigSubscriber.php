<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\NavigationService;
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
        'app_login',
        'app_logout',
    ];

    public function __construct(
        private Environment $twig,
        private TokenStorageInterface $tokenStorage,
        private WorkspaceService $workspaceService,
        private NavigationService $navigation
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

        $this->twig->addGlobal('empresa', $this->workspaceService->getActiveEmpresa($user));
        $this->twig->addGlobal('empresas', $this->workspaceService->getAvailableEmpresas($user));

        foreach ($this->navigation->getNavGlobals($user, $route) as $name => $value) {
            $this->twig->addGlobal($name, $value);
        }
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
