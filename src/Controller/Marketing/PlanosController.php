<?php

namespace App\Controller\Marketing;

use App\PosOperatorio\ClinicCommercialPlans;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlanosController extends AbstractController
{
    #[Route('/planos', name: 'app_planos', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('marketing/planos.html.twig', [
            'plans' => ClinicCommercialPlans::all(),
            'default_plan' => ClinicCommercialPlans::defaultPlanId(),
        ]);
    }
}
