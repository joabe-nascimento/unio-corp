<?php

namespace App\Controller;

use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class WorkspaceController extends AbstractController
{
    #[Route('/workspace/switch/{id}', name: 'app_workspace_switch', methods: ['GET'])]
    public function switch(int $id, WorkspaceService $ws): RedirectResponse
    {
        $ws->switchTo($this->getUser(), $id);
        return $this->redirectToRoute('app_dashboard');
    }
}