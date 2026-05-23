<?php

namespace App\Controller;

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
    public function select(WorkspaceService $ws, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $empresas = $ws->getAvailableEmpresas($user);

        // Usuario sem empresa — vai direto
        if (empty($empresas)) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Apenas uma empresa — seleciona automaticamente e redireciona
        if (count($empresas) === 1 && !$request->query->has('force')) {
            $ws->switchTo($user, $empresas[0]->getId());
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('workspace/select.html.twig', [
            'empresas' => $empresas,
            'current'  => $ws->getActiveEmpresa($user),
        ]);
    }

    #[Route('/workspace/switch/{id}', name: 'app_workspace_switch', methods: ['GET'])]
    public function switch(int $id, WorkspaceService $ws, Request $request): Response
    {
        $ws->switchTo($this->getUser(), $id);
        $redirect = $request->query->get('back', 'app_dashboard');
        return $this->redirectToRoute($redirect);
    }
}