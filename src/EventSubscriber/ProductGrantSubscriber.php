<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\ProductGrantAccess;
use App\Security\ProductGrantRouteMap;
use App\Service\Organismo\OrganismoFeature;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Aplica product_grant.view nas rotas mapeadas (após role global).
 * Sem acesso → redireciona ao dashboard (menus somem na sidebar; sem tela 403).
 */
final class ProductGrantSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private ProductGrantAccess $grants,
        private UrlGeneratorInterface $urlGenerator,
        private OrganismoFeature $organismo,
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

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if ($this->organismo->isEnabled()) {
            return;
        }

        if ($this->grants->isRouteAllowed($user, $route)) {
            return;
        }

        $dashboardUrl = $this->urlGenerator->generate('app_dashboard');
        $event->setController(static fn (): RedirectResponse => new RedirectResponse($dashboardUrl));
    }
}
