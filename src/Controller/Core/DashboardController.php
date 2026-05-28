<?php

namespace App\Controller\Core;

use App\Service\DashboardStatsService;
use App\Service\NavigationService;
use App\Service\OnboardingProgressService;
use App\Service\WelcomeAnalyticsService;
use App\Service\WelcomeService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        WorkspaceService $workspaceService,
        NavigationService $navigation,
        WelcomeService $welcome,
        DashboardStatsService $dashboardStats,
        WelcomeAnalyticsService $analytics,
        OnboardingProgressService $onboardingProgress,
    ): Response {
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $empresa  = $workspaceService->getActiveEmpresa($user);
        $empresas = $workspaceService->getAvailableEmpresas($user);
        $layout   = $navigation->getLayout($user);
        $dt       = $welcome->getDateTimeInfo();

        return $this->render('core/dashboard/index.html.twig', [
            'layout'          => $layout,
            'layout_headline' => $dashboardStats->getLayoutHeadline($layout, $empresa),
            'kpis'            => $dashboardStats->getKpis($user, $empresa, $layout, \count($empresas)),
            'chart_sections'  => $analytics->getChartSections($user, $empresa),
            'empresa'         => $empresa,
            'empresas'        => $empresas,
            'account_pending' => empty($empresas) && !$user->isTenant(),
            'greeting'        => $welcome->getGreeting(),
            'date_label'      => $dt['date_label'],
            'time_label'      => $dt['time_label'],
            'weekday'         => $dt['weekday'],
            'onboarding'      => $onboardingProgress->build($user, $empresa, \count($empresas)),
        ]);
    }
}
