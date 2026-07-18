<?php

namespace App\Controller\Core;

use App\Service\Clinic\ClinicReceptionHomeService;
use App\Service\DashboardStatsService;
use App\Service\NavigationService;
use App\Service\OnboardingProgressService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\PosOperatorio\ClinicOperationsService;
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
        OrganismoCopyService $organismoCopy,
        ClinicOperationsService $clinicOperations,
        ClinicReceptionHomeService $clinicReceptionHome,
    ): Response {
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $empresa  = $workspaceService->getActiveEmpresa($user);
        $empresas = $workspaceService->getAvailableEmpresas($user);
        $layout   = $navigation->getLayout($user);
        $dt       = $welcome->getDateTimeInfo();
        $clinicQueue = null;
        $receptionHome = null;
        $isClinic = $organismoCopy->isClinicProfile();
        // Clínica: Início operacional. BI em Qualidade / Contas / CRM Analytics.
        $chartSections = $isClinic ? [] : $analytics->getChartSections($user, $empresa);
        $chartExecutive = ['kpis' => []];
        if ($isClinic && $empresa !== null) {
            $clinicQueue = $clinicOperations->buildWorkQueue($empresa);
            $receptionHome = $clinicReceptionHome->build($empresa);
        }

        return $this->render('core/dashboard/index.html.twig', [
            'layout'          => $layout,
            'layout_headline' => $dashboardStats->getLayoutHeadline($layout, $empresa),
            'kpis'            => $dashboardStats->getKpis($user, $empresa, $layout, \count($empresas)),
            'chart_sections'  => $chartSections,
            'chart_executive' => $chartExecutive,
            'empresa'         => $empresa,
            'empresas'        => $empresas,
            'account_pending' => empty($empresas) && !$user->hasPlatformAccess(),
            'greeting'        => $welcome->getGreeting(),
            'date_label'      => $dt['date_label'],
            'time_label'      => $dt['time_label'],
            'weekday'         => $dt['weekday'],
            'onboarding'      => $onboardingProgress->build($user, $empresa, \count($empresas)),
            'clinic_queue'    => $clinicQueue,
            'reception_home'  => $receptionHome,
        ]);
    }
}
