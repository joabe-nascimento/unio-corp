<?php

namespace App\Controller\Core;

use App\Service\NavigationService;
use App\Service\OnboardingProgressService;
use App\Service\WelcomeAnalyticsService;
use App\Service\WelcomePresentationService;
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
    public function index(
        WelcomeService $welcome,
        WelcomePresentationService $presentation,
        WelcomeAnalyticsService $analytics,
        OnboardingProgressService $onboardingProgress,
        WorkspaceService $workspace,
        NavigationService $navigation,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $dt = $welcome->getDateTimeInfo();

        $empresas = $workspace->getAvailableEmpresas($user);
        $empresa = $workspace->getActiveEmpresa($user);
        $empresasCount = \count($empresas);

        return $this->render('core/welcome/index.html.twig', [
            'greeting' => $welcome->getGreeting(),
            'date_label' => $dt['date_label'],
            'time_label' => $dt['time_label'],
            'weekday' => $dt['weekday'],
            'hubs' => $welcome->getHubsForUser($user),
            'novidades' => $welcome->getNovidadesForUser($user),
            'presentation' => $presentation->build($user, $empresa, $empresasCount),
            'layout' => $navigation->getLayout($user),
            'empresa' => $empresa,
            'empresas_count' => $empresasCount,
            'chart_sections' => $analytics->getChartSections($user, $empresa),
            'perfil_label' => $user->getPerfilLabel(),
            'perfil_class' => $user->getPerfilClass(),
            'onboarding' => $onboardingProgress->build($user, $empresa, $empresasCount),
        ]);
    }
}
