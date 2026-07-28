<?php

namespace App\Security;

use App\Service\Juridico\ApiTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Autenticador stateless da API Pública do Unio Jurídico (`/api/v1/publica/*`).
 * Espera `Authorization: Bearer ujr_live_...` — sem sessão, sem cookie, sem CSRF.
 */
final class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private ApiTokenService $tokens,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api/v1/publica');
    }

    public function authenticate(Request $request): Passport
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            throw new CustomUserMessageAuthenticationException('Envie o cabeçalho Authorization: Bearer <seu_token>.');
        }

        $raw = trim($m[1]);
        $token = $this->tokens->validar($raw);
        if ($token === null) {
            throw new CustomUserMessageAuthenticationException('Token de API inválido, revogado ou inexistente.');
        }

        return new SelfValidatingPassport(new UserBadge('api-token:' . $token->getId(), static fn () => new ApiTokenUser($token)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
    }
}
