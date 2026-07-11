<?php

namespace App\Controller\Marketing;

use App\Service\Marketing\ClinicLandingService;
use App\Service\Marketing\ClinicPatientProductService;
use App\Service\Organismo\OrganismoRedirectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LandingController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(
        OrganismoRedirectService $redirects,
        ClinicPatientProductService $patientProduct,
        ClinicLandingService $clinicLanding,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute($redirects->afterLoginRoute());
        }

        $demoCard = $patientProduct->planById('premium') ?? $patientProduct->plans()[0] ?? [];

        return $this->render('marketing/home.html.twig', [
            'landing_card' => $demoCard,
            'landing_card_theme' => $demoCard['theme'] ?? 'premium',
            'landing_plans' => $patientProduct->plans(),
            'landing_guia' => $patientProduct->demoGuia(),
            'landing_demo_access' => $patientProduct->demoAccess(),
            'clinic_hubs' => $clinicLanding->hubs(),
        ]);
    }
}
