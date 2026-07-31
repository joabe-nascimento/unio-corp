<?php

namespace App\Controller\Api;


use App\Dev\DevSeedEmails;
use App\Entity\User;
use App\Service\Juridico\AgenteAutonomoStatusStore;
use App\Service\Juridico\AiTokenUsageService;
use App\Service\Juridico\JurisFlowAiClient;
use App\Service\Juridico\LegalIntentDetector;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\PosOperatorio\SashaContextService;
use App\Service\Sasha\DocumentTextExtractorService;
use App\Service\Sasha\SashaClient;
use App\Service\Sasha\SashaToolRegistry;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/sasha')]
#[IsGranted('ROLE_USER')]
final class SashaApiController extends AbstractController
{
    /**
     * Ferramentas de leitura (não gravam nada) rodam sozinhas assim que a intenção é
     * detectada — o agente já responde com o resultado, sem exigir clique nem depender
     * do provedor de IA externo estar disponível. Ferramentas de escrita nunca entram
     * aqui: sempre passam pelo fluxo de confirmação explícita.
     */
    private const FERRAMENTAS_LEITURA_AUTOMATICA = [
        'calcular_prazo',
        'calcular_honorarios',
        'analisar_carteira',
        'tarefas_urgentes',
        'buscar_processo',
        'prever_exito',
        'consultar_datajud',
        'resumir_documento',
        'analisar_contrato',
        'gerar_minuta',
        'analisar_sentenca',
        'comparar_documentos',
        'listar_prazos',
        'sugerir_pecas_similares',
    ];

    public function __construct(
        private SashaClient $vitoria,
        private JurisFlowAiClient $juridicoAi,
        private WorkspaceService $workspace,
        private SashaContextService $vitoriaContext,
        private SashaToolRegistry $toolRegistry,
        private OrganismoCopyService $organismoCopy,
        private LegalIntentDetector $legalIntentDetector,
        private AiTokenUsageService $aiTokenUsage,
        private AgenteAutonomoStatusStore $agenteStatus,
        private \App\Service\Sasha\SashaConversationManager $conversationManager,
        private DocumentTextExtractorService $documentTextExtractor,
    ) {}

    /**
     * Escolhe o motor de IA ativo conforme a identidade da plataforma:
     * Unio Jurídico usa o JurisFlow (LangChain + RAG jurídico); demais usam a Vitória padrão.
     */
    private function activeClient(): SashaClient|JurisFlowAiClient
    {
        if ($this->organismoCopy->isJuridicoProfile()) {
            return $this->juridicoAi;
        }

        return $this->vitoria;
    }

    #[Route('/status', name: 'api_sasha_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json([
            'enabled' => true,
            'online' => $this->activeClient()->isAvailable(),
            'assistant' => $this->organismoCopy->lumen(),
        ]);
    }

    #[Route('/tools', name: 'api_sasha_tools', methods: ['GET'])]
    public function tools(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(['tools' => $this->toolRegistry->listFor($user)]);
    }

    #[Route('/tools/{name}', name: 'api_sasha_tool_run', methods: ['POST'])]
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

    #[Route('/ai-usage', name: 'api_sasha_ai_usage', methods: ['GET'])]
    public function aiUsage(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (mb_strtolower($user->getEmail() ?? '') !== mb_strtolower(DevSeedEmails::JOABE)) {
            return $this->json(['error' => 'Indisponível'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->organismoCopy->isJuridicoProfile()) {
            return $this->json(['error' => 'Indisponível neste perfil'], Response::HTTP_FORBIDDEN);
        }

        $summary = $this->aiTokenUsage->getSummary();
        if ($summary === null) {
            return $this->json(['error' => 'IA jurídica desabilitada'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json($summary);
    }

    /**
     * Status do Agente Autônomo (monitoramento em background de prazos/tarefas/carteira,
     * rodando via cron independente do chat). Visível para qualquer usuário do perfil
     * jurídico — é um indicador operacional, não um dado sensível.
     */
    #[Route('/agente-status', name: 'api_sasha_agente_status', methods: ['GET'])]
    public function agenteStatus(): JsonResponse
    {
        if (!$this->organismoCopy->isJuridicoProfile()) {
            return $this->json(['error' => 'Indisponível neste perfil'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->agenteStatus->resumo());
    }

    #[Route('/conversations', name: 'api_sasha_conversations', methods: ['GET'])]
    public function conversations(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();

        $conversations = $this->conversationManager->getUserConversations($user, $empresa);

        return $this->json($conversations);
    }

    #[Route('/conversations/{id}', name: 'api_sasha_conversation_get', methods: ['GET'])]
    public function getConversation(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $conversation = $this->conversationManager->getConversation($id, $user);

        if ($conversation === null) {
            return $this->json(['error' => 'Conversa não encontrada'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($conversation);
    }

    #[Route('/conversations/{id}', name: 'api_sasha_conversation_delete', methods: ['DELETE'])]
    public function deleteConversation(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $success = $this->conversationManager->deleteConversation($id, $user);

        if (!$success) {
            return $this->json(['error' => 'Conversa não encontrada'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/conversations/{id}/pin', name: 'api_sasha_conversation_pin', methods: ['POST'])]
    public function togglePin(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $success = $this->conversationManager->togglePin($id, $user);

        if (!$success) {
            return $this->json(['error' => 'Conversa não encontrada'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/messages/{id}/rate', name: 'api_sasha_message_rate', methods: ['POST'])]
    public function rateMessage(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return $this->json(['error' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
        }

        $rating = (int) ($payload['rating'] ?? 0);
        $feedback = trim((string) ($payload['feedback'] ?? ''));

        if ($rating < -1 || $rating > 1) {
            return $this->json(['error' => 'Rating inválido (-1, 0, 1)'], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->conversationManager->rateMessage($id, $user, $rating, $feedback !== '' ? $feedback : null);

        if (!$success) {
            return $this->json(['error' => 'Mensagem não encontrada'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['success' => true]);
    }

    /**
     * Extrai texto de um arquivo anexado no chat (PDF/DOCX/TXT) para uso ad-hoc —
     * nada é persistido aqui. O frontend anexa o texto extraído à própria mensagem
     * antes de enviar para /chat, reaproveitando os gatilhos já existentes no
     * {@see LegalIntentDetector} (resumir_documento, analisar_contrato, etc).
     */
    #[Route('/upload', name: 'api_sasha_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('arquivo');
        if ($file === null) {
            return $this->json(['error' => 'Nenhum arquivo enviado.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $resultado = $this->documentTextExtractor->extract($file);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'filename' => $file->getClientOriginalName(),
            'text' => $resultado['text'],
            'truncated' => $resultado['truncated'],
        ]);
    }

    #[Route('/chat', name: 'api_sasha_chat', methods: ['POST'])]
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

        $conversationId = isset($payload['conversation_id']) ? (int) $payload['conversation_id'] : null;
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        $context = $payload['context'] ?? [];
        $contextType = isset($context['numero_processo']) ? 'processo' : (isset($context['patient_codigo']) ? 'paciente' : null);
        $contextId = $contextType === 'processo' ? $context['numero_processo'] : ($contextType === 'paciente' ? $context['patient_codigo'] : null);

        // Criar ou buscar conversa
        if ($conversationId !== null) {
            $conversationData = $this->conversationManager->getConversation($conversationId, $user);
            if ($conversationData === null) {
                return $this->json(['error' => 'Conversa não encontrada'], Response::HTTP_NOT_FOUND);
            }
            $conversation = $this->conversationManager->getConversationEntity($conversationId, $user);
        } else {
            $conversation = $this->conversationManager->createOrGetConversation(
                $user,
                $empresa,
                $contextType,
                $contextId,
                $message
            );
        }

        // Salvar mensagem do usuário
        $this->conversationManager->addMessage($conversation, 'user', $message);

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

        $pacienteCodigo = $payload['context']['patient_codigo'] ?? $payload['patient_codigo'] ?? null;
        $numeroProcessoAtual = $payload['context']['numero_processo'] ?? $payload['numero_processo'] ?? null;
        $numeroProcessoAtual = \is_string($numeroProcessoAtual) && trim($numeroProcessoAtual) !== '' ? trim($numeroProcessoAtual) : null;
        $chatMode = strtolower(trim((string) ($payload['mode'] ?? $payload['context']['mode'] ?? 'standard')));
        if (\in_array($chatMode, ['lex', 'premium', 'high'], true)) {
            $chatMode = 'superior';
        }
        if ($chatMode !== 'superior') {
            $chatMode = 'standard';
        }
        $contextData = [
            'hub' => (string) ($payload['context']['hub'] ?? $payload['hub'] ?? ''),
            'empresa_nome' => $empresa?->getNome(),
            'user_name' => $user->getNome() ?? 'Usuário',
            'patient_codigo' => $pacienteCodigo,
            'numero_processo_atual' => $numeroProcessoAtual,
            'assistant' => $this->organismoCopy->lumen(),
            'escritorio_id' => $empresa?->getId() !== null ? (string) $empresa->getId() : 'default',
            'mode' => $chatMode,
        ];

        if ($empresa && !$this->organismoCopy->isJuridicoProfile()) {
            $contextData = $this->vitoriaContext->enrichChatContext($empresa, $contextData, is_string($pacienteCodigo) ? $pacienteCodigo : null);
        }

        $isJuridico = $this->organismoCopy->isJuridicoProfile();
        $acoesSugeridas = $isJuridico ? $this->legalIntentDetector->detect($message, $numeroProcessoAtual) : [];

        if ($isJuridico && $this->legalIntentDetector->isConsultaDatajudSemNumero($message, $numeroProcessoAtual)) {
            $reply = 'Para consultar o andamento oficial no DataJud, preciso do número do processo no formato CNJ (ex.: 0000000-00.0000.0.00.0000). Pode me informar?';
            $this->conversationManager->addMessage($conversation, 'assistant', $reply);

            return $this->json([
                'reply' => $reply,
                'source' => 'agent',
                'suggested_actions' => [],
                'tool_results' => [],
                'conversation_id' => $conversation->getId(),
            ]);
        }

        // Intenção determinística já resolvida: responde na hora, sem chamar a IA externa.
        // Mais rápido e não depende do provedor de IA estar disponível.
        if ($isJuridico && $acoesSugeridas !== []) {
            $auto = $this->executarAutomaticamente($user, $acoesSugeridas[0]);
            if ($auto !== null) {
                // Salvar resposta do assistente
                $this->conversationManager->addMessage($conversation, 'assistant', $auto['reply'], ['tool_results' => $auto['tool_results']]);

                return $this->json([
                    'reply' => $auto['reply'],
                    'source' => 'agent',
                    'suggested_actions' => [],
                    'tool_results' => $auto['tool_results'],
                    'conversation_id' => $conversation->getId(),
                ]);
            }

            $reply = $this->mensagemParaAcoesSugeridas($acoesSugeridas);
            $this->conversationManager->addMessage($conversation, 'assistant', $reply, ['suggested_actions' => $acoesSugeridas]);

            return $this->json([
                'reply' => $reply,
                'source' => 'agent',
                'suggested_actions' => $acoesSugeridas,
                'tool_results' => [],
                'conversation_id' => $conversation->getId(),
            ]);
        }

        $result = $this->activeClient()->chat($message, $history, $contextData);
        if ($result === null) {
            $reply = sprintf(
                '%s está temporariamente indisponível. Tente novamente em instantes.',
                $this->organismoCopy->lumen(),
            );
            $this->conversationManager->addMessage($conversation, 'assistant', $reply);

            return $this->json([
                'reply' => $reply,
                'source' => 'offline',
                'suggested_actions' => [],
                'conversation_id' => $conversation->getId(),
            ]);
        }

        // Salvar resposta do assistente
        $this->conversationManager->addMessage($conversation, 'assistant', $result['reply'] ?? '', $result);

        return $this->json(array_merge($result, ['conversation_id' => $conversation->getId()]));
    }

    /**
     * Executa de imediato uma ferramenta de leitura já identificada pelo detector de
     * intenção — o agente age sozinho quando não há risco de gravação indevida.
     *
     * @param array{tool: string, label: string, params: array<string, mixed>} $acao
     *
     * @return array{reply: string, tool_results: list<array<string, mixed>>}|null
     */
    private function executarAutomaticamente(User $user, array $acao): ?array
    {
        $tool = (string) ($acao['tool'] ?? '');
        if (!\in_array($tool, self::FERRAMENTAS_LEITURA_AUTOMATICA, true)) {
            return null;
        }
        if (!$this->toolRegistry->supports($user, $tool)) {
            return null;
        }

        $toolObj = $this->toolRegistry->get($tool);
        if ($toolObj === null) {
            return null;
        }

        $execResult = $toolObj->execute($user, \is_array($acao['params'] ?? null) ? $acao['params'] : []);
        $summary = trim((string) ($execResult['summary'] ?? ''));

        return [
            'reply' => $summary !== '' ? $summary : 'Já verifiquei isso pra você.',
            'tool_results' => \is_array($execResult['results'] ?? null) ? $execResult['results'] : [],
        ];
    }

    /**
     * Quando o detector já sabe o que fazer, a resposta deve ser direta — sem ruído
     * de mensagens genéricas do provedor de IA (ex.: "instabilidade no provedor").
     *
     * @param list<array{tool: string, label: string, params: array<string, mixed>}> $acoes
     */
    private function mensagemParaAcoesSugeridas(array $acoes): string
    {
        $primeira = $acoes[0];
        $label = mb_strtolower(trim((string) ($primeira['label'] ?? '')));

        if (str_starts_with($label, 'registrar prazo')) {
            $tipo = trim(str_replace('registrar prazo:', '', $label));

            return $tipo !== ''
                ? sprintf('Entendi! Posso registrar esse prazo de %s para você. Confira abaixo e confirme se estiver certo.', $tipo)
                : 'Entendi! Posso registrar esse prazo para você. Confira abaixo e confirme se estiver certo.';
        }

        if (str_starts_with($label, 'criar tarefa')) {
            return 'Entendi! Posso criar essa tarefa para você. Confira os detalhes abaixo e confirme se estiver certo.';
        }

        if (str_contains($label, 'pesquisar jurisprudência') || str_contains($label, 'pesquisar jurisprudencia')) {
            return 'Posso pesquisar essa jurisprudência para você. É só confirmar abaixo.';
        }

        if (str_contains($label, 'calcular')) {
            return 'Posso calcular isso para você. Confira abaixo e confirme se quiser seguir.';
        }

        $rotulo = trim((string) ($primeira['label'] ?? 'executar essa ação'));

        return sprintf('Entendi! Posso %s para você. Confira abaixo e confirme se estiver certo.', mb_strtolower($rotulo));
    }
}
