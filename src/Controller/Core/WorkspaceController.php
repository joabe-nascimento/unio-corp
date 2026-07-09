<?php

namespace App\Controller\Core;

use App\Service\Organismo\OrganismoRedirectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** @deprecated Rotas legadas — redirecionam para o Pulso. */
#[IsGranted('ROLE_USER')]
final class WorkspaceController extends AbstractController
{
    #[Route('/workspace', name: 'app_workspace_select')]
    #[Route('/workspace/switch/{id}', name: 'app_workspace_switch', methods: ['GET'])]
    public function legacyRedirect(OrganismoRedirectService $redirects): Response
    {
        return $this->redirectToRoute($redirects->homeRoute());
    }
}
