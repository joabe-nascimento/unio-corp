<?php

namespace App\Security;

use App\Service\Organismo\OrganismoRedirectService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Pós-login: organismo → Pulso; legado → seleção de workspace.
 */
final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $router,
        private OrganismoRedirectService $redirects,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $target = $request->request->get('_target_path');
        if (\is_string($target) && $target !== '' && str_starts_with($target, '/')) {
            return new RedirectResponse($target);
        }

        $user = $token->getUser();
        $route = $this->redirects->afterLoginRoute(
            $user instanceof \App\Entity\User ? $user : null,
        );

        return new RedirectResponse(
            $this->router->generate($route),
        );
    }
}
