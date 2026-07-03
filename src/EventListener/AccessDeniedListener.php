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

        if ($this->config->isMaintenanceMode() && !$this->hasPlatformAccess()) {
            $event->setResponse(new RedirectResponse(
                $this->router->generate('app_manutencao'),
                Response::HTTP_TEMPORARY_REDIRECT
            ));

            return;
        }

        if ($request->isXmlHttpRequest()) {
            $detail = $this->extractDeniedMessage($exception);
            $event->setResponse(new Response(
                json_encode(['error' => 'access_denied', 'message' => $detail ?: 'Sem permissão para esta ação.']),
                Response::HTTP_FORBIDDEN,
                ['Content-Type' => 'application/json']
            ));

            return;
        }

        $isAdmin = str_starts_with($request->getPathInfo(), '/admin');
        $detail  = $this->extractDeniedMessage($exception);

        if (!$isAdmin && $this->shouldRedirectToWorkspace($detail)) {
            $this->addFlash($request, 'warning', $detail !== '' ? $detail : 'Selecione uma área de trabalho para continuar.');
            $event->setResponse(new RedirectResponse(
                $this->router->generate('app_workspace_select'),
                Response::HTTP_FOUND
            ));

            return;
        }

        if (!$isAdmin && str_contains(mb_strtolower($detail), 'projetos e metas')) {
            $this->addFlash($request, 'warning', $detail);
            $event->setResponse(new RedirectResponse(
                $this->router->generate('app_dashboard'),
                Response::HTTP_FOUND
            ));

            return;
        }

        if ($this->shouldForceLogout($detail)) {
            $event->setResponse(new RedirectResponse(
                $this->router->generate('app_sessao_encerrar'),
                Response::HTTP_FOUND
            ));

            return;
        }

        $back = $this->resolveBackLink($isAdmin);
        $message = $isAdmin
            ? 'Esta área é exclusiva para administradores da plataforma (tenant).'
            : ($detail !== '' ? $detail : 'Você não tem permissão para acessar este recurso.');

        if (!$isAdmin && !$this->security->isGranted('ROLE_USER')) {
            $message = 'Sua sessão expirou ou é inválida. Saia e faça login novamente.';
        }

        $event->setResponse(new Response(
            $this->twig->render('error/access_denied.html.twig', [
                'title'      => $isAdmin ? 'Área restrita' : 'Acesso negado',
                'message'    => $message,
                'back_url'   => $back['url'],
                'back_label' => $back['label'],
            ]),
            Response::HTTP_FORBIDDEN
        ));
    }

    private function extractDeniedMessage(\Throwable $exception): string
    {
        $messages = [];
        $current  = $exception;
        while ($current !== null) {
            $msg = trim($current->getMessage());
            if ($msg !== '' && !preg_match('/^access denied\.?$/i', $msg)) {
                $messages[] = $msg;
            }
            $current = $current->getPrevious();
        }

        return $messages[0] ?? '';
    }

    private function shouldForceLogout(string $detail): bool
    {
        if (!$this->security->getUser()) {
            return false;
        }

        if ($this->security->isGranted('ROLE_USER')) {
            return false;
        }

        if (str_contains($detail, 'ROLE_USER')) {
            return true;
        }

        return true;
    }

    /** @return array{url: string, label: string} */
    private function resolveBackLink(bool $isAdmin): array
    {
        if ($isAdmin) {
            return [
                'url'   => $this->router->generate('app_dashboard'),
                'label' => 'Voltar ao início',
            ];
        }

        if ($this->security->isGranted('ROLE_USER')) {
            return [
                'url'   => $this->router->generate('app_dashboard'),
                'label' => 'Voltar ao início',
            ];
        }

        return [
            'url'   => $this->router->generate('app_sessao_encerrar'),
            'label' => 'Sair e entrar de novo',
        ];
    }

    private function shouldRedirectToWorkspace(string $detail): bool
    {
        if ($detail === '') {
            return false;
        }

        $lower = mb_strtolower($detail);

        return str_contains($lower, 'área de trabalho')
            || str_contains($lower, 'area de trabalho');
    }

    private function hasPlatformAccess(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && $user->hasPlatformAccess();
    }

    private function addFlash(Request $request, string $type, string $message): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $request->getSession()->getFlashBag()->add($type, $message);
    }
}
