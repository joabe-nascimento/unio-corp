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

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$user instanceof User) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');

        $this->twig->addGlobal('empresa', $this->workspaceService->getActiveEmpresa($user));
        $this->twig->addGlobal('empresas', $this->workspaceService->getAvailableEmpresas($user));
        $this->twig->addGlobal('nav_layout', $this->navigation->getLayout($user));
        $this->twig->addGlobal('nav_show_hub_operacoes', $this->navigation->showHubOperacoes($user));
        $this->twig->addGlobal('nav_show_hub_talentos', $this->navigation->showHubTalentos($user));
        $this->twig->addGlobal('nav_show_hub_maturidade', $this->navigation->showHubMaturidade($user));
        $this->twig->addGlobal('nav_show_admin', $this->navigation->showAdmin($user));
        $this->twig->addGlobal('nav_show_tenant_empresas', $this->navigation->showTenantEmpresas($user));
        $this->twig->addGlobal('nav_hub_operacoes_active', $this->navigation->isHubOperacoesActive($route));
    }
}