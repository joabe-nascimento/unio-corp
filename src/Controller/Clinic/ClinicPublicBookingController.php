<?php

namespace App\Controller\Clinic;

use App\Service\Clinic\ClinicPublicBookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clinica')]
final class ClinicPublicBookingController extends AbstractController
{
    public function __construct(
        private ClinicPublicBookingService $booking,
    ) {}

    #[Route('/{slug}/agendar', name: 'app_clinica_agendar_public', methods: ['GET', 'POST'])]
    public function bySlug(string $slug, Request $request): Response
    {
        return $this->renderPage($this->booking->resolveEmpresa($slug), $request);
    }

    #[Route('/agendar/{empresaId}', name: 'app_clinica_agendar_public_id', requirements: ['empresaId' => '\d+'], methods: ['GET', 'POST'])]
    public function byId(int $empresaId, Request $request): Response
    {
        return $this->renderPage($this->booking->resolveEmpresa((string) $empresaId), $request);
    }

    private function renderPage(?\App\Entity\Empresa $empresa, Request $request): Response
    {
        if ($empresa === null) {
            return $this->render('clinic/public/booking.html.twig', [
                'ok' => false,
                'message' => 'Clínica não encontrada ou indisponível.',
            ]);
        }

        $submitted = false;
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_public_booking', (string) $request->request->get('_token'))) {
                $error = 'Sessão expirada. Tente novamente.';
            } else {
                try {
                    $this->booking->submit($empresa, $request->request->all());
                    $submitted = true;
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('clinic/public/booking.html.twig', [
            'ok' => true,
            'empresa' => $empresa,
            'submitted' => $submitted,
            'error' => $error,
            'motivos' => ClinicPublicBookingService::motivoLabels(),
            'periodos' => ClinicPublicBookingService::periodoLabels(),
            'form' => [
                'nome' => (string) $request->request->get('nome', ''),
                'telefone' => (string) $request->request->get('telefone', ''),
                'email' => (string) $request->request->get('email', ''),
                'motivo' => (string) $request->request->get('motivo', 'consulta'),
                'data_preferida' => (string) $request->request->get('data_preferida', ''),
                'periodo' => (string) $request->request->get('periodo', 'indiferente'),
                'observacao' => (string) $request->request->get('observacao', ''),
            ],
        ]);
    }
}
