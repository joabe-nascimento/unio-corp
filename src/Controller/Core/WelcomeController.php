<?php

namespace App\Controller\Core;

use App\Service\WelcomeService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class WelcomeController extends AbstractController
{
    #[Route('/bem-vindo', name: 'app_welcome')]
    public function index(WelcomeService $welcome, WorkspaceService $workspace): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $dt = $welcome->getDateTimeInfo();

        return $this->render('core/welcome/index.html.twig', [
            'greeting' => $welcome->getGreeting(),
            'date_label' => $dt['date_label'],
            'time_label' => $dt['time_label'],
            'weekday' => $dt['weekday'],
            'hubs' => $welcome->getHubsForUser($user),
            'novidades' => $welcome->getNovidadesForUser($user),
            'empresa' => $workspace->getActiveEmpresa($user),
        ]);
    }
}
