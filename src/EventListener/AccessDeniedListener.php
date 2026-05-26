<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\PlatformConfigService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * Substitui a página de erro 403 do Symfony por mensagens amigáveis.
 * Em manutenção, redireciona não-tenants para /manutencao em vez de exibir 403.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 64)]
class AccessDeniedListener
{
    public function __construct(
        private PlatformConfigService $config,
        private Security $security,
        private UrlGeneratorInterface $router,
        private Environment $twig,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getThrowable();
        $isDenied  = $exception instanceof AccessDeniedHttpException
            || $exception instanceof AccessDeniedException
            || $exception->getPrevious() instanceof AccessDeniedException;

        if (!$isDenied) {
            return;
        }

        $request = $event->getRequest();

        if ($this->config->isMaintenanceMode() && !$this->isTenant()) {
            $event->setResponse(new RedirectResponse(
                $this->router->generate('app_manutencao'),
                Response::HTTP_TEMPORARY_REDIRECT
            ));

            return;
        }

        if ($request->isXmlHttpRequest()) {
            $event->setResponse(new Response(
                json_encode(['error' => 'access_denied', 'message' => 'Sem permissão para esta ação.']),
                Response::HTTP_FORBIDDEN,
                ['Content-Type' => 'application/json']
            ));

            return;
        }

        $isAdmin = str_starts_with($request->getPathInfo(), '/admin');
        $event->setResponse(new Response(
            $this->twig->render('error/access_denied.html.twig', [
                'title'   => $isAdmin ? 'Área restrita' : 'Acesso negado',
                'message' => $isAdmin
                    ? 'Esta área é exclusiva para administradores da plataforma (tenant).'
                    : 'Você não tem permissão para acessar este recurso.',
                'back_url'  => $this->router->generate('app_dashboard'),
                'back_label'=> 'Voltar ao início',
            ]),
            Response::HTTP_FORBIDDEN
        ));
    }

    private function isTenant(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && $user->isTenant();
    }
}
