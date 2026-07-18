<?php

namespace App\Controller\Core;

use App\Entity\User;
use App\Service\Analytics\ClinicFinanceAnalyticsService;
use App\Service\NavigationService;
use App\Service\Organismo\OrganismoFeature;
use App\Service\Organismo\PulsoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class PulsoController extends AbstractController
{
    #[Route('/pulso', name: 'app_pulso')]
    public function index(
        OrganismoFeature $organismo,
        PulsoService $pulso,
        WorkspaceService $workspace,
        NavigationService $navigation,
        ClinicFinanceAnalyticsService $financeAnalytics,
    ): Response {
        if (!$organismo->isEnabled()) {
            return $this->redirectToRoute('app_dashboard');
        }

        /** @var User $user */
        $user = $this->getUser();
        $empresa = $workspace->getActiveEmpresa($user);
        $empresas = $workspace->getAvailableEmpresas($user);
        $layout = $navigation->getLayout($user);
        $snapshot = $pulso->buildSnapshot($user, $empresa, $layout, \count($empresas));

        $financeData = ['dre' => [], 'repasse' => [], 'trend_labels' => [], 'trend_values' => []];
        try {
            $financeData = $financeAnalytics->getWidgetData($empresa);
        } catch (\Exception $e) {
        }

        return $this->render('core/pulso/index.html.twig', [
            'snapshot' => $snapshot,
            'empresa' => $empresa,
            'empresas' => $empresas,
            'pulso_api_url' => $this->generateUrl('api_pulso'),
            'finance_data' => $financeData,
        ]);
    }
}
