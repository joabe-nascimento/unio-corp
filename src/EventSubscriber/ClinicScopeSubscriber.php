<?php

namespace App\EventSubscriber;

use App\Clinic\ClinicScopedRoutes;
use App\Service\Clinic\ClinicPlatformScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bloqueia rotas de beneficiário e produtos clínicos fora do deploy Unio Saúde.
 */
final class ClinicScopeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ClinicPlatformScope $clinicScope,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onController', 16],
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest() || $this->clinicScope->isActive()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $routeName = \is_string($route) ? $route : null;

        if (!ClinicScopedRoutes::isRestricted($routeName, $request->getPathInfo())) {
            return;
        }

        throw new NotFoundHttpException('Recurso disponível apenas no ambiente Unio Saúde.');
    }
}
