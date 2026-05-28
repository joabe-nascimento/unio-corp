<?php

namespace App\Controller\Core;

use App\Service\NavigationService;
use App\Service\WelcomeUpdatesIntelligenceService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/bem-vindo/api/atualizacoes')]
final class WelcomeUpdatesApiController extends AbstractController
{
    #[Route('', name: 'app_welcome_updates_api_feed', methods: ['GET'])]
    public function feed(
        WelcomeUpdatesIntelligenceService $updatesIntel,
        WorkspaceService $workspace,
        NavigationService $navigation,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $empresa = $workspace->getActiveEmpresa($user);
        $layout = $navigation->getLayout($user);
        $empresasCount = \count($workspace->getAvailableEmpresas($user));

        $payload = $updatesIntel->buildPayload($user, $empresa, $layout, $empresasCount);

        return $this->json([
            'items' => $payload['items'],
            'meta' => [
                'dynamic_count' => $payload['dynamic_count'],
                'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }
}
