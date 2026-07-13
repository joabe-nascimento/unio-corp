<?php

namespace App\Controller\Module\Financeiro;

use App\Service\Clinic\ClinicPlatformScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/financeiro')]
#[IsGranted('ROLE_USER')]
class FinanceiroController extends AbstractController
{
    public function __construct(
        private ClinicPlatformScope $clinicScope,
    ) {}

    #[Route('', name: 'app_financeiro')]
    public function index(): Response
    {
        if ($this->clinicScope->isActive()) {
            return $this->render('modules/financeiro/clinic_bridge.html.twig');
        }

        return $this->render('modules/financeiro/index.html.twig');
    }
}
