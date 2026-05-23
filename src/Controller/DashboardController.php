<?php

namespace App\Controller;

use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(WorkspaceService $workspaceService): Response
    {
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $empresa  = $workspaceService->getActiveEmpresa($user);
        $empresas = $workspaceService->getAvailableEmpresas($user);

        if ($this->isGranted('ROLE_ADMIN')) {
            $stats  = ['funcionarios' => 128, 'departamentos' => 12, 'vagas_abertas' => 7, 'treinamentos' => 23, 'usuarios' => 34, 'empresas' => count($empresas)];
            $layout = 'admin';
        } elseif ($this->isGranted('ROLE_GESTOR')) {
            $stats  = ['funcionarios' => 64, 'departamentos' => 6, 'vagas_abertas' => 4, 'treinamentos' => 11];
            $layout = 'gestor';
        } elseif ($this->isGranted('ROLE_SUPERVISOR')) {
            $stats  = ['funcionarios' => 28, 'departamentos' => 3, 'vagas_abertas' => 2, 'treinamentos' => 5];
            $layout = 'supervisor';
        } else {
            $stats  = ['treinamentos' => 3, 'avaliacoes' => 1];
            $layout = 'membro';
        }

        return $this->render('dashboard/index.html.twig', [
            'stats'    => $stats,
            'layout'   => $layout,
            'user'     => $user,
            'empresa'  => $empresa,
            'empresas' => $empresas,
        ]);
    }
}