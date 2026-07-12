<?php

namespace App\Controller\Beneficiary;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Redirect legado /carterinha-digital → /carteirinha-digital */
#[Route('/carterinha-digital')]
final class BeneficiaryCarteirinhaLegacyController extends AbstractController
{
    #[Route('', name: 'app_carteirinha_digital_legacy', methods: ['GET', 'POST'])]
    public function redirectFlow(Request $request): Response
    {
        return $this->redirectToRoute('app_carteirinha_digital', $request->query->all(), Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/sair', name: 'app_carteirinha_digital_legacy_sair', methods: ['GET'])]
    public function redirectLogout(): Response
    {
        return $this->redirectToRoute('app_carteirinha_digital_sair', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
