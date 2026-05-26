<?php

namespace App\EventListener;

use App\Service\PlatformConfigService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Bloqueia acesso de usuários não-tenant quando o modo de manutenção está ativo.
 * Redireciona para /manutencao ou retorna 503 para requisições não-HTML.
 */
/** Executa após o firewall (prioridade 8) para o usuário já estar autenticado. */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
class MaintenanceListener
{
    /** Rotas públicas que permanecem acessíveis durante a manutenção */
    private const ALLOWED_ROUTES = [
        'app_login',
        'app_logout',
        'app_manutencao',
        'app_register',
        'app_forgot_password',
        'app_reset_password',
        '_wdt',
        '_profiler',
        '_profiler_home',
        '_profiler_search',
        '_profiler_search_bar',
        '_profiler_phpinfo',
        '_profiler_search_results',
        '_profiler_open_file',
        '_profiler_router',
        '_profiler_exception',
        '_profiler_exception_css',
    ];

    public function __construct(
        private PlatformConfigService $config,
        private Security $security,
        private RouterInterface $router,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->config->isMaintenanceMode()) {
            return;
        }

        $request = $event->getRequest();
        $route   = (string) $request->attributes->get('_route', '');
        $path    = $request->getPathInfo();

        // Rotas públicas durante manutenção
        foreach (self::ALLOWED_ROUTES as $allowed) {
            if ($route !== '' && str_starts_with($route, $allowed)) {
                return;
            }
        }
        foreach (['/manutencao', '/login', '/logout', '/register', '/forgot-password', '/reset-password'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        // Tenants continuam com acesso total
        $user = $this->security->getUser();
        if ($user !== null && method_exists($user, 'isTenant') && $user->isTenant()) {
            return;
        }

        // Redirect HTML requests; return 503 for API/XHR
        if ($request->isXmlHttpRequest()) {
            $event->setResponse(new Response(
                json_encode(['error' => 'maintenance']),
                Response::HTTP_SERVICE_UNAVAILABLE,
                ['Content-Type' => 'application/json']
            ));
            return;
        }

        $url = $this->router->generate('app_manutencao');
        $event->setResponse(new RedirectResponse($url, Response::HTTP_TEMPORARY_REDIRECT));
    }
}
