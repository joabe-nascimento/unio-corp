<?php

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Redireciona visitantes anônimos do portal para a tela de login do paciente.
 */
final class PortalPatientAccessSubscriber implements EventSubscriberInterface
{
    private const PORTAL_PREFIXES = [
        '/clinica/portal/acompanhamento',
        '/pos-operatorio/portal',
    ];

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Depois do FirewallListener (8) para a sessão já estar resolvida.
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if ($path === '/clinica/portal/login' || $path === '/clinica/portal' || $path === '/clinica/portal/') {
            return;
        }

        foreach (self::PORTAL_PREFIXES as $prefix) {
            if (!str_starts_with($path, $prefix)) {
                continue;
            }

            if ($this->security->getUser() !== null) {
                return;
            }

            $event->setResponse(new RedirectResponse(
                $this->router->generate('app_portal_patient_login'),
            ));

            return;
        }
    }
}
