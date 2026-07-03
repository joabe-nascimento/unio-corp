<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\PosOperatorio\VitoriaContextService;
use App\Service\Vitoria\VitoriaClient;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/vitoria')]
#[IsGranted('ROLE_USER')]
final class VitoriaApiController extends AbstractController
{
    public function __construct(
        private VitoriaClient $vitoria,
        private WorkspaceService $workspace,
        private VitoriaContextService $vitoriaContext,
    ) {}

    #[Route('/status', name: 'api_vitoria_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json([
            'enabled' => true,
            'online' => $this->vitoria->isAvailable(),
        ]);
    }

    #[Route('/chat', name: 'api_vitoria_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
        }

        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return $this->json(['error' => 'Mensagem vazia'], Response::HTTP_BAD_REQUEST);
        }

        $history = [];
        foreach ($payload['history'] ?? [] as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $history[] = [
                'role' => (string) ($item['role'] ?? 'user'),
                'content' => (string) ($item['content'] ?? $item['text'] ?? ''),
            ];
        }

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        $pacienteCodigo = $payload['context']['patient_codigo'] ?? $payload['patient_codigo'] ?? null;
        $context = [
            'hub' => (string) ($payload['context']['hub'] ?? $payload['hub'] ?? ''),
            'empresa_nome' => $empresa?->getNome(),
            'user_name' => $user->getNome() ?? 'Usuário',
            'patient_codigo' => $pacienteCodigo,
        ];

        if ($empresa) {
            $context = $this->vitoriaContext->enrichChatContext($empresa, $context, is_string($pacienteCodigo) ? $pacienteCodigo : null);
        }

        $result = $this->vitoria->chat($message, $history, $context);
        if ($result === null) {
            return $this->json([
                'reply' => 'A Vitória está temporariamente indisponível. Sua mensagem foi registrada — tente novamente em instantes.',
                'source' => 'offline',
            ]);
        }

        return $this->json($result);
    }
}
