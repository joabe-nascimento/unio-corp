<?php

namespace App\Controller\Marketing;

use App\Service\Marketing\ClinicLandingService;
use App\Service\Marketing\ClinicPatientProductService;
use App\Service\Marketing\JuridicoLandingService;
use App\Service\Clinic\ClinicPlatformScope;
use App\Service\Juridico\JuridicoPlatformScope;
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
        ClinicPlatformScope $clinicScope,
        JuridicoLandingService $juridicoLanding,
        JuridicoPlatformScope $juridicoScope,
    ): Response {
        if ($this->getUser()) {
            $user = $this->getUser();

            return $this->redirectToRoute($redirects->afterLoginRoute(
                $user instanceof \App\Entity\User ? $user : null,
            ));
        }

        if ($juridicoScope->isActive()) {
            return $this->render('marketing/home-juridico.html.twig', [
                'juridico_features' => $juridicoLanding->features(),
                'juridico_modules' => $juridicoLanding->modules(),
                'juridico_stats' => $juridicoLanding->stats(),
                'juridico_metrics' => $juridicoLanding->metrics(),
                'juridico_testimonial' => $juridicoLanding->testimonial(),
                'juridico_steps' => $juridicoLanding->steps(),
                'juridico_trust' => $juridicoLanding->trust(),
                'juridico_audiences' => $juridicoLanding->audiences(),
                'juridico_routine' => $juridicoLanding->routine(),
                'juridico_faq' => $juridicoLanding->faq(),
                'autonomous_features' => $juridicoLanding->autonomousFeatures(),
                'daily_routine' => $juridicoLanding->dailyRoutine(),
                'before_after' => $juridicoLanding->beforeAfter(),
                'interactive_use_cases' => $juridicoLanding->interactiveUseCases(),
            ]);
        }

        $demoCard = $patientProduct->planById('premium') ?? $patientProduct->plans()[0] ?? [];

        return $this->render('marketing/home.html.twig', [
            'landing_card' => $demoCard,
            'landing_card_theme' => $demoCard['theme'] ?? 'premium',
            'landing_comprovante_card' => $patientProduct->comprovanteDemoCard(),
            'landing_plans' => $patientProduct->plans(),
            'landing_guia' => $patientProduct->demoGuia(),
            'landing_demo_access' => $patientProduct->demoAccess(),
            'clinic_hubs' => $clinicScope->isActive() ? $clinicLanding->hubs() : [],
            'clinic_plans' => $clinicScope->isActive() ? $clinicLanding->commercialPlans() : [],
            'clinic_specialties' => $clinicScope->isActive() ? $clinicLanding->specialties() : [],
        ]);
    }
}
