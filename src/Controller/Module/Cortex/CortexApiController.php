<?php

namespace App\Controller\Module\Cortex;

use App\Service\CortexIntelligenceService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/cortex/api')]
final class CortexApiController extends AbstractController
{
    #[Route('', name: 'app_cortex_api_payload', methods: ['GET'])]
    public function payload(
        CortexIntelligenceService $cortex,
        WorkspaceService $workspace,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->json($cortex->buildPayload($user, $workspace->getActiveEmpresa($user)));
    }
}
