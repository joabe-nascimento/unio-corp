<?php

namespace App\Controller\Core;

use App\Service\NavigationService;
use App\Service\WelcomeAnalyticsService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/bem-vindo/api/graficos')]
final class WelcomeAnalyticsApiController extends AbstractController
{
    #[Route('', name: 'app_welcome_analytics_api_feed', methods: ['GET'])]
    public function feed(
        WelcomeAnalyticsService $analytics,
        WorkspaceService $workspace,
        NavigationService $navigation,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $empresa = $workspace->getActiveEmpresa($user);

        return $this->json($analytics->getChartPayload($user, $empresa));
    }
}
