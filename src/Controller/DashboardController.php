<?php

namespace App\Controller;

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
    public function index(): Response
    {
        $user = $this->getUser();

        $stats = [
            'funcionarios'  => 128,
            'departamentos' => 12,
            'vagas_abertas' => 7,
            'treinamentos'  => 23,
        ];

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
            'user'  => $user,
        ]);
    }
}
