<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\Juridico\JurisFlowAiClient;
use App\Service\Juridico\LegalIntentDetector;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\PosOperatorio\VitoriaContextService;
use App\Service\Vitoria\VitoriaClient;
use App\Service\Vitoria\VitoriaToolRegistry;
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
        private JurisFlowAiClient $juridicoAi,
        private WorkspaceService $workspace,
        private VitoriaContextService $vitoriaContext,
        private VitoriaToolRegistry $toolRegistry,
        private OrganismoCopyService $organismoCopy,
        private LegalIntentDetector $legalIntentDetector,
    ) {}

    /**
     * Escolhe o motor de IA ativo conforme a identidade da plataforma:
     * Unio Jurídico usa o JurisFlow (LangChain + RAG jurídico); demais usam a Vitória padrão.
     */
    private function activeClient(): VitoriaClient|JurisFlowAiClient
    {
        if ($this->organismoCopy->isJuridicoProfile()) {
            return $this->juridicoAi;
        }

        return $this->vitoria;
    }

    #[Route('/status', name: 'api_vitoria_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json([
            'enabled' => true,
            'online' => $this->activeClient()->isAvailable(),
            'assistant' => $this->organismoCopy->lumen(),
        ]);
    }

    #[Route('/tools', name: 'api_vitoria_tools', methods: ['GET'])]
    public function tools(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(['tools' => $this->toolRegistry->listFor($user)]);
    }

    #[Route('/tools/{name}', name: 'api_vitoria_tool_run', methods: ['POST'])]
    public function runTool(string $name, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->toolRegistry->supports($user, $name)) {
            return $this->json(['error' => 'Ferramenta indisponível'], Response::HTTP_FORBIDDEN);
        }

        $tool = $this->toolRegistry->get($name);
        if ($tool === null) {
            return $this->json(['error' => 'Ferramenta não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        $params = \is_array($payload) ? $payload : [];

        $result = $tool->execute($user, $params);

        return $this->json([
            'tool' => $name,
            'summary' => $result['summary'],
            'results' => $result['results'],
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
            'assistant' => $this->organismoCopy->lumen(),
            'escritorio_id' => $empresa?->getId() !== null ? (string) $empresa->getId() : 'default',
        ];

        if ($empresa && !$this->organismoCopy->isJuridicoProfile()) {
            $context = $this->vitoriaContext->enrichChatContext($empresa, $context, is_string($pacienteCodigo) ? $pacienteCodigo : null);
        }

        $isJuridico = $this->organismoCopy->isJuridicoProfile();
        $acoesSugeridas = $isJuridico ? $this->legalIntentDetector->detect($message) : [];

        $result = $this->activeClient()->chat($message, $history, $context);
        if ($result === null) {
            $reply = sprintf(
                '%s está temporariamente indisponível. Tente novamente em instantes.',
                $this->organismoCopy->lumen(),
            );

            if ($acoesSugeridas !== []) {
                $reply .= ' Enquanto isso, posso executar isto direto no sistema, sem precisar da IA:';
            }

            return $this->json([
                'reply' => $reply,
                'source' => 'offline',
                'suggested_actions' => $acoesSugeridas,
            ]);
        }

        if ($isJuridico && $acoesSugeridas !== []) {
            $result['suggested_actions'] = $acoesSugeridas;
        }

        return $this->json($result);
    }
}
