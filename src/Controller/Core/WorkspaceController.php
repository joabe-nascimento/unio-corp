<?php

namespace App\Controller\Core;

use App\Service\OnboardingProgressService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class WorkspaceController extends AbstractController
{
    #[Route('/workspace', name: 'app_workspace_select')]
    public function select(WorkspaceService $ws, OnboardingProgressService $onboarding, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $empresas = $ws->getAvailableEmpresas($user);

        if (empty($empresas)) {
            return $this->redirectToRoute('app_dashboard');
        }

        if (count($empresas) === 1 && !$request->query->has('force')) {
            $ws->switchTo($user, $empresas[0]->getId());
            $onboarding->markStepComplete('workspace');

            return $this->redirectToRoute('app_welcome');
        }

        return $this->render('workspace/select.html.twig', [
            'empresas' => $empresas,
            'current'  => $ws->getActiveEmpresa($user),
        ]);
    }

    #[Route('/workspace/switch/{id}', name: 'app_workspace_switch', methods: ['GET'])]
    public function switch(int $id, WorkspaceService $ws, OnboardingProgressService $onboarding, Request $request): Response
    {
        $ws->switchTo($this->getUser(), $id);
        $onboarding->markStepComplete('workspace');
        $redirect = $request->query->get('back', 'app_welcome');

        return $this->redirectToRoute($redirect);
    }
}
