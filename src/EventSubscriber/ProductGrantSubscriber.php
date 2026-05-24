<?php

namespace App\EventSubscriber;

use App\Security\ProductGrantRouteMap;
use App\Security\Voter\ProductGrantVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Aplica product_grant.view nas rotas mapeadas (após role global).
 */
final class ProductGrantSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onController', 0],
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (!\is_string($route) || !isset(ProductGrantRouteMap::MAP[$route])) {
            return;
        }

        $subject = ProductGrantRouteMap::MAP[$route];

        if (!$this->security->isGranted(ProductGrantVoter::VIEW, $subject)) {
            throw new AccessDeniedHttpException('Sem permissão para acessar este produto.');
        }
    }
}
