<?php

namespace App\Controller\Module\PosOperatorio;

use App\Service\PosOperatorio\ClinicAltaIntakeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Webhook público de alta cirúrgica — autenticado por token da clínica.
 */
#[Route('/pos-operatorio/api/alta')]
final class PosOperatorioAltaWebhookController extends AbstractController
{
    public function __construct(
        private ClinicAltaIntakeService $alta,
    ) {}

    #[Route('', name: 'app_pos_operatorio_api_alta', methods: ['POST'])]
    public function ingest(Request $request): JsonResponse
    {
        $token = trim((string) (
            $request->headers->get('X-Unio-Alta-Token')
            ?? $request->query->get('token')
            ?? ''
        ));

        $empresa = $this->alta->findEmpresaByToken($token);
        if ($empresa === null) {
            return $this->json(['error' => 'Token inválido'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent() ?: '{}', true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->alta->ingest($empresa, $payload);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Falha ao registrar alta'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'ok' => true,
            'paciente_id' => $result['paciente_id'],
            'codigo' => $result['codigo'],
            'created' => $result['created'],
        ], $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
