<?php

namespace App\EventListener;

use App\Service\PlatformConfigService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Encerra a sessão após período de inatividade configurado em Admin → Configurações.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
class SessionTimeoutListener
{
    private const SKIP_PATH_PREFIXES = [
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/manutencao',
        '/_wdt',
        '/_profiler',
    ];

    public function __construct(
        private PlatformConfigService $config,
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $router,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($this->shouldSkip($request)) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user === null) {
            return;
        }

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $timeout = $this->config->getSessaoTimeoutSeconds();
        $now     = time();
        $last = $session->get('_last_activity');

        if (\is_int($last) && ($now - $last) > $timeout) {
            $session->invalidate();
            $this->tokenStorage->setToken(null);

            $event->setResponse(new RedirectResponse(
                $this->router->generate('app_login', ['timeout' => 1]),
                RedirectResponse::HTTP_FOUND
            ));

            return;
        }

        $session->set('_last_activity', $now);
    }

    private function shouldSkip(Request $request): bool
    {
        $path = $request->getPathInfo();
        foreach (self::SKIP_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
