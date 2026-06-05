<?php

namespace App\Controller\Module\Ti;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Ti\TiChamadoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Webhook para abertura de chamados por integrações externas. */
final class TiWebhookController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private TiChamadoService $chamados,
        private UserRepository $users,
    ) {}

    #[Route('/api/ti/webhook/chamado', name: 'app_ti_webhook_chamado', methods: ['POST'])]
    public function createTicket(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-TI-Webhook-Token', '');
        $expected = $_ENV['TI_WEBHOOK_TOKEN'] ?? 'ti-webhook-dev';
        if (!hash_equals($expected, $token)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            $payload = $request->request->all();
        }

        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);
        $user = $this->users->find($userId);
        if (!$user instanceof User || !$user->isAtivo()) {
            return new JsonResponse(['error' => 'Usuário inválido'], 422);
        }

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa || ($empresaId > 0 && $empresa->getId() !== $empresaId)) {
            return new JsonResponse(['error' => 'Empresa inválida'], 422);
        }

        try {
            $ticket = $this->chamados->createFromWebhook($empresa, $user, $payload);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse(['ok' => true, 'ticket' => $ticket], 201);
    }
}
