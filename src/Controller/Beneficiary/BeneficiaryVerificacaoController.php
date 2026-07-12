<?php

namespace App\Controller\Beneficiary;

use App\Service\Clinic\ClinicVerificacaoAuditService;
use App\Service\PosOperatorio\ClinicVerificacaoPublicaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Validação pública por QR — recepção, hospital, convênio. */
#[Route('/verificar')]
final class BeneficiaryVerificacaoController extends AbstractController
{
    public function __construct(
        private ClinicVerificacaoPublicaService $verificacao,
        private ClinicVerificacaoAuditService $audit,
    ) {}

    #[Route('/{codigo}', name: 'app_verificar_documento', requirements: ['codigo' => '[A-Za-z0-9]{6,12}'])]
    public function verificar(Request $request, string $codigo): Response
    {
        $resultado = $this->verificacao->verificar($codigo);
        $this->audit->log($request, $codigo, $resultado);

        return $this->render('beneficiary/verificar.html.twig', [
            'resultado' => $resultado,
        ]);
    }
}
