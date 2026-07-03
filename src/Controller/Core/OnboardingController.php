<?php

namespace App\Controller\Core;

use App\Service\OnboardingProgressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/onboarding')]
#[IsGranted('ROLE_USER')]
class OnboardingController extends AbstractController
{
    #[Route('/tour-complete', name: 'app_onboarding_tour_complete', methods: ['POST'])]
    public function tourComplete(Request $request, OnboardingProgressService $onboarding): JsonResponse
    {
        if (!$this->isCsrfTokenValid('onboarding_tour', (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'csrf'], 403);
        }

        $onboarding->markStepComplete('shell_tour');

        return new JsonResponse([
            'ok' => true,
            'shell_tour_done' => true,
        ]);
    }
}
