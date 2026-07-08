<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\PlatformConfigService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Bloqueia acesso de usuários comuns quando o modo de manutenção está ativo.
 * Tenant e dono da plataforma (PLATFORM_OWNER) continuam com acesso.
 * Redireciona para /manutencao ou retorna 503 para requisições não-HTML.
 */
/** Executa após o firewall (prioridade 8) para o usuário já estar autenticado. */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
class MaintenanceListener
{
    /** Rotas públicas que permanecem acessíveis durante a manutenção */
    private const ALLOWED_ROUTES = [
        'app_home',
        'app_logout',
        'app_sessao_encerrar',
        'app_manutencao',
        'app_legal_termos',
        'app_legal_privacidade',
        'app_legal_lgpd',
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
        foreach (['/manutencao', '/logout', '/encerrar-sessao', '/termos', '/privacidade', '/lgpd'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }
        if ($path === '/') {
            return;
        }

        // Tenant e dono da plataforma continuam com acesso total
        $user = $this->security->getUser();
        if ($user instanceof User && $user->hasPlatformAccess()) {
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
