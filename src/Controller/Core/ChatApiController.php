<?php

namespace App\Controller\Core;

use App\Entity\User;
use App\Service\ChatService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/bate-papo/api')]
class ChatApiController extends AbstractController
{
    public function __construct(
        private ChatService $chat,
        private WorkspaceService $workspace,
        private HubRegistry $hubRegistry,
        private Authorization $authorization,
    ) {}

    #[Route('/conversations', name: 'app_chat_api_conversations', methods: ['GET'])]
    public function conversations(): JsonResponse
    {
        [$user, $empresa] = $this->requireContext();

        return $this->json([
            'conversations' => $this->chat->getConversationsPayload($user, $empresa),
            'colleagues' => $this->chat->getColleagues($user, $empresa),
            'user_id' => $user->getId(),
        ]);
    }

    #[Route('/conversations/direct', name: 'app_chat_api_direct', methods: ['POST'])]
    public function createDirect(Request $request): JsonResponse
    {
        [$user, $empresa] = $this->requireContext();
        $data = json_decode($request->getContent(), true) ?: [];
        $targetId = (int) ($data['user_id'] ?? 0);

        return $this->jsonOrError(fn () => $this->chat->createDirect($user, $empresa, $targetId));
    }

    #[Route('/conversations/group', name: 'app_chat_api_group', methods: ['POST'])]
    public function createGroup(Request $request): JsonResponse
    {
        [$user, $empresa] = $this->requireContext();
        $data = json_decode($request->getContent(), true) ?: [];
        $name = (string) ($data['name'] ?? '');
        $memberIds = is_array($data['member_ids'] ?? null) ? $data['member_ids'] : [];

        return $this->jsonOrError(fn () => $this->chat->createGroup($user, $empresa, $name, $memberIds));
    }

    #[Route('/conversations/{id}', name: 'app_chat_api_conversation', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function conversation(int $id): JsonResponse
    {
        [$user] = $this->requireContext();

        return $this->json($this->chat->getConversationDetail($user, $id));
    }

    #[Route('/conversations/{id}/media', name: 'app_chat_api_media', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function media(int $id): JsonResponse
    {
        [$user] = $this->requireContext();

        return $this->json($this->chat->getMediaGallery($user, $id));
    }

    #[Route('/conversations/{id}', name: 'app_chat_api_conversation_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateConversation(int $id, Request $request): JsonResponse
    {
        [$user] = $this->requireContext();
        $data = json_decode($request->getContent(), true) ?: [];

        return $this->jsonOrError(fn () => $this->chat->renameGroup($user, $id, (string) ($data['name'] ?? '')));
    }

    #[Route('/conversations/{id}/members', name: 'app_chat_api_members', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addMembers(int $id, Request $request): JsonResponse
    {
        [$user] = $this->requireContext();
        $data = json_decode($request->getContent(), true) ?: [];
        $memberIds = is_array($data['member_ids'] ?? null) ? $data['member_ids'] : [];

        return $this->jsonOrError(fn () => $this->chat->addGroupMembers($user, $id, $memberIds));
    }

    #[Route('/conversations/{id}/leave', name: 'app_chat_api_leave', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function leave(int $id): JsonResponse
    {
        [$user] = $this->requireContext();

        return $this->jsonOrError(function () use ($user, $id) {
            $this->chat->leaveGroup($user, $id);

            return ['ok' => true];
        });
    }

    #[Route('/conversations/{id}/messages', name: 'app_chat_api_messages', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function messages(int $id, Request $request): JsonResponse
    {
        [$user] = $this->requireContext();
        $limit = $request->query->get('limit');

        return $this->json($this->chat->getMessagesPayload(
            $user,
            $id,
            $request->query->get('since'),
            $request->query->get('before'),
            $limit !== null && $limit !== '' ? (int) $limit : null,
        ));
    }

    #[Route('/conversations/{id}/messages', name: 'app_chat_api_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function send(int $id, Request $request): JsonResponse
    {
        [$user] = $this->requireContext();
        $data = json_decode($request->getContent(), true) ?: [];
        $text = (string) ($data['text'] ?? '');
        $replyToId = isset($data['reply_to_id']) ? (int) $data['reply_to_id'] : null;

        return $this->jsonOrError(fn () => $this->chat->sendText($user, $id, $text, $replyToId));
    }

    #[Route('/conversations/{id}/messages/{messageId}', name: 'app_chat_api_delete_message', requirements: ['id' => '\d+', 'messageId' => '\d+'], methods: ['DELETE'])]
    public function deleteMessage(int $id, int $messageId): JsonResponse
    {
        [$user] = $this->requireContext();

        return $this->jsonOrError(fn () => $this->chat->deleteMessage($user, $messageId));
    }

    #[Route('/conversations/{id}/file', name: 'app_chat_api_file', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function uploadFile(int $id, Request $request): JsonResponse
    {
        [$user] = $this->requireContext();
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Arquivo não enviado.'], 400);
        }
        $replyToId = $request->request->get('reply_to_id');

        return $this->jsonOrError(fn () => $this->chat->sendFile(
            $user,
            $id,
            $file,
            $replyToId !== null && $replyToId !== '' ? (int) $replyToId : null,
        ));
    }

    #[Route('/conversations/{id}/typing', name: 'app_chat_api_typing', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function typing(int $id): JsonResponse
    {
        [$user] = $this->requireContext();
        $this->chat->publishTyping($user, $id);

        return $this->json(['ok' => true]);
    }

    #[Route('/conversations/{id}/voice', name: 'app_chat_api_voice', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function voice(int $id, Request $request): JsonResponse
    {
        [$user] = $this->requireContext();
        $file = $request->files->get('audio');
        if (!$file) {
            return $this->json(['error' => 'Áudio não enviado.'], 400);
        }

        $duration = (int) $request->request->get('duration_ms', 0);

        return $this->json($this->chat->sendVoice($user, $id, $file, $duration));
    }

    #[Route('/conversations/{id}/read', name: 'app_chat_api_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function read(int $id): JsonResponse
    {
        [$user] = $this->requireContext();
        $this->chat->markRead($user, $id);

        return $this->json(['ok' => true]);
    }

    #[Route('/conversations/{id}/call/signal', name: 'app_chat_api_call_signal', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function callSignal(int $id, Request $request): JsonResponse
    {
        [$user] = $this->requireContext();
        $data = json_decode($request->getContent(), true) ?: [];

        return $this->jsonOrError(fn () => $this->chat->postCallSignal(
            $user,
            $id,
            (string) ($data['type'] ?? ''),
            (string) ($data['payload'] ?? ''),
            isset($data['to_user_id']) ? (int) $data['to_user_id'] : null,
        ));
    }

    #[Route('/mercure/subscribe', name: 'app_chat_api_mercure', methods: ['GET'])]
    public function mercureSubscribe(Request $request): JsonResponse
    {
        [$user, $empresa] = $this->requireContext();
        $topics = $this->chat->getSubscribeTopics($user, $empresa);
        $hubUrl = $this->hubRegistry->getHub()->getPublicUrl();
        $separator = str_contains($hubUrl, '?') ? '&' : '?';
        foreach ($topics as $topic) {
            $hubUrl .= $separator . 'topic=' . rawurlencode($topic);
            $separator = '&';
        }

        $response = $this->json(['hub_url' => $hubUrl, 'topics' => $topics]);
        $response->headers->setCookie($this->authorization->createCookie($request, $topics));

        return $response;
    }

    #[Route('/conversations/{id}/call/poll', name: 'app_chat_api_call_poll', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function callPoll(int $id, Request $request): JsonResponse
    {
        [$user] = $this->requireContext();
        $since = (string) $request->query->get('since', (new \DateTimeImmutable('-5 seconds'))->format(\DateTimeInterface::ATOM));

        return $this->json([
            'signals' => $this->chat->pollCallSignals($user, $id, $since),
        ]);
    }

    /** @param callable(): mixed $action */
    private function jsonOrError(callable $action): JsonResponse
    {
        try {
            return $this->json($action());
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }

    /** @return array{0: User, 1: \App\Entity\Empresa} */
    private function requireContext(): array
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw $this->createAccessDeniedException('Selecione uma área de trabalho para usar o Chat Bate Papo.');
        }

        return [$user, $empresa];
    }
}
