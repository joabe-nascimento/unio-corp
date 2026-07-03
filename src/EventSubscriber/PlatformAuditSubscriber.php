<?php

namespace App\EventSubscriber;

use App\Entity\PlatformAuditLog;
use App\Entity\User;
use App\Service\Platform\PlatformAuditService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class PlatformAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PlatformAuditService $audit,
        private Security $security,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
            KernelEvents::EXCEPTION => ['onException', -10],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->audit->record(
            PlatformAuditLog::CATEGORY_AUTH,
            PlatformAuditLog::ACTION_LOGIN,
            PlatformAuditLog::OUTCOME_SUCCESS,
            sprintf('Login bem-sucedido: %s', $user->getEmail()),
            $user,
            null,
            'user',
            $user->getId(),
            $user->getNome() ?? $user->getEmail(),
            $event->getRequest(),
        );
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $email = '';
        $passport = $event->getPassport();
        if ($passport !== null) {
            $badge = $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class);
            if ($badge instanceof \Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge) {
                $email = (string) $badge->getUserIdentifier();
            }
        }

        $this->audit->record(
            PlatformAuditLog::CATEGORY_AUTH,
            PlatformAuditLog::ACTION_LOGIN_FAILED,
            PlatformAuditLog::OUTCOME_FAILURE,
            $email !== ''
                ? sprintf('Tentativa de login falhou: %s', $email)
                : 'Tentativa de login falhou',
            null,
            $email !== '' ? $email : null,
            'user',
            null,
            $email !== '' ? $email : null,
            $event->getRequest(),
            ['exception' => $event->getException()->getMessage()],
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->audit->record(
            PlatformAuditLog::CATEGORY_AUTH,
            PlatformAuditLog::ACTION_LOGOUT,
            PlatformAuditLog::OUTCOME_SUCCESS,
            sprintf('Logout: %s', $user->getEmail()),
            $user,
            null,
            'user',
            $user->getId(),
            $user->getNome() ?? $user->getEmail(),
            $event->getRequest(),
        );
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $throwable = $event->getThrowable();
        if (!$throwable instanceof HttpExceptionInterface) {
            return;
        }

        $status = $throwable->getStatusCode();
        if ($status < 400) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');
        if (!$this->shouldLogRoute($route)) {
            return;
        }

        $user = $this->security->getUser();
        $actor = $user instanceof User ? $user : null;

        $outcome = $status >= 500
            ? PlatformAuditLog::OUTCOME_FAILURE
            : PlatformAuditLog::OUTCOME_WARNING;

        $this->audit->record(
            PlatformAuditLog::CATEGORY_HTTP,
            PlatformAuditLog::ACTION_ERROR,
            $outcome,
            sprintf('HTTP %d em %s — %s', $status, $route, $throwable->getMessage()),
            $actor,
            null,
            'route',
            null,
            $route,
            $request,
            ['status' => $status],
        );
    }

    private function shouldLogRoute(string $route): bool
    {
        if ($route === '') {
            return false;
        }

        return str_starts_with($route, 'app_admin_')
            || str_starts_with($route, 'app_chat_api_')
            || str_contains($route, '_api_');
    }
}
