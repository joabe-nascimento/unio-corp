<?php

namespace App\EventSubscriber;

use App\Entity\User;
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
        private WorkspaceService $workspaceService
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

        $this->twig->addGlobal('empresa', $this->workspaceService->getActiveEmpresa($user));
        $this->twig->addGlobal('empresas', $this->workspaceService->getAvailableEmpresas($user));
    }
}