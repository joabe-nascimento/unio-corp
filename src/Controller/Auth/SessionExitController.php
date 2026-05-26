<?php

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
/**
 * Encerra sessão e cookies de autenticação sem exigir ROLE_USER.
 * Usado quando a sessão está inválida e /logout retornaria 403.
 */
final class SessionExitController extends AbstractController
{
    #[Route('/encerrar-sessao', name: 'app_sessao_encerrar', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        $response = new RedirectResponse($this->generateUrl('app_login', ['relogin' => 1]));

        foreach (['REMEMBERME', 'remember_me'] as $cookieName) {
            $response->headers->clearCookie($cookieName, '/', null, false, false, 'lax');
        }

        return $response;
    }
}
