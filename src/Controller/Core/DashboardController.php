<?php

namespace App\Controller\Core;

use App\Service\NavigationService;
use App\Service\WelcomeService;
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
    public function index(WorkspaceService $workspaceService, NavigationService $navigation, WelcomeService $welcome): Response
    {
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $empresa  = $workspaceService->getActiveEmpresa($user);
        $empresas = $workspaceService->getAvailableEmpresas($user);
        $layout   = $navigation->getLayout($user);
        $dt       = $welcome->getDateTimeInfo();

        $stats = match ($layout) {
            'tenant' => ['funcionarios' => 128, 'departamentos' => 12, 'vagas_abertas' => 7, 'treinamentos' => 23, 'usuarios' => 34, 'empresas' => count($empresas)],
            'gestor' => ['funcionarios' => 64, 'departamentos' => 6, 'vagas_abertas' => 4, 'treinamentos' => 11],
            'supervisor' => ['funcionarios' => 28, 'departamentos' => 3, 'vagas_abertas' => 2, 'treinamentos' => 5],
            default => ['treinamentos' => 3, 'avaliacoes' => 1],
        };

        return $this->render('core/dashboard/index.html.twig', [
            'stats'      => $stats,
            'layout'     => $layout,
            'user'       => $user,
            'empresa'    => $empresa,
            'empresas'   => $empresas,
            'greeting'   => $welcome->getGreeting(),
            'date_label' => $dt['date_label'],
            'time_label' => $dt['time_label'],
            'weekday'    => $dt['weekday'],
        ]);
    }
}
