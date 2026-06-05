<?php

namespace App\Service;

use App\Entity\ChatCallSignal;
use App\Entity\ChatConversation;
use App\Entity\ChatMessage;
use App\Entity\ChatParticipant;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\ChatCallSignalRepository;
use App\Repository\ChatConversationRepository;
use App\Repository\ChatMessageRepository;
use App\Repository\ChatParticipantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChatService
{
    private const VOICE_DIR = 'uploads/chat/voice';
    private const FILE_DIR = 'uploads/chat/files';
    private const FILE_MAX_BYTES = 10_485_760;
    private const MESSAGE_PAGE_SIZE = 50;

    public function __construct(
        private EntityManagerInterface $em,
        private ChatConversationRepository $conversationRepo,
        private ChatParticipantRepository $participantRepo,
        private ChatMessageRepository $messageRepo,
        private ChatCallSignalRepository $signalRepo,
        private UserRepository $userRepo,
        private ChatMercurePublisher $mercure,
        private ChatMercureTopics $mercureTopics,
        private string $projectDir,
    ) {}

    public function ensureWorkspaceReady(User $user, Empresa $empresa): void
    {
        $existing = $this->conversationRepo->findForUser($user, $empresa);
        if ($existing !== []) {
            return;
        }

        $group = new ChatConversation();
        $group->setEmpresa($empresa);
        $group->setType(ChatConversation::TYPE_GROUP);
        $group->setName('Equipe ' . $empresa->getNome());
        $this->em->persist($group);

        $members = $this->userRepo->findActiveByEmpresa($empresa);
        foreach ($members as $member) {
            $group->addParticipant($this->makeParticipant($group, $member));
        }

        $welcome = new ChatMessage();
        $welcome->setConversation($group);
        $welcome->setMessageType(ChatMessage::TYPE_SYSTEM);
        $welcome->setBody('Grupo da equipe criado. Envie mensagens de texto ou áudio.');
        $group->addMessage($welcome);

        $this->em->flush();
    }

    /** @return list<array<string, mixed>> */
    public function getConversationsPayload(User $user, Empresa $empresa): array
    {
        $this->ensureWorkspaceReady($user, $empresa);

        $out = [];
        foreach ($this->conversationRepo->findForUser($user, $empresa) as $conv) {
            $out[] = $this->serializeConversation($conv, $user);
        }

        usort($out, static fn (array $a, array $b) => strcmp($b['sort_at'] ?? '', $a['sort_at'] ?? ''));

        return $out;
    }

    /** @return list<string> */
    public function getSubscribeTopics(User $user, Empresa $empresa): array
    {
        $this->ensureWorkspaceReady($user, $empresa);

        $topics = [$this->mercureTopics->user((int) $user->getId())];
        foreach ($this->conversationRepo->findForUser($user, $empresa) as $conv) {
            $topics[] = $this->mercureTopics->conversation((int) $conv->getId());
        }

        return array_values(array_unique($topics));
    }

    /** @return array{messages: list<array<string, mixed>>, has_more: bool} */
    public function getMessagesPayload(
        User $user,
        int $conversationId,
        ?string $sinceIso = null,
        ?string $beforeIso = null,
        ?int $limit = null,
    ): array {
        $conv = $this->requireConversation($user, $conversationId);
        $pageSize = min(100, max(1, $limit ?? self::MESSAGE_PAGE_SIZE));

        if ($sinceIso) {
            $since = new \DateTimeImmutable($sinceIso);
            $messages = $this->messageRepo->findSince($conv, $since);

            return [
                'messages' => array_map(fn (ChatMessage $m) => $this->serializeMessage($m, $user), $messages),
                'has_more' => false,
            ];
        }

        $before = $beforeIso ? new \DateTimeImmutable($beforeIso) : null;
        [$messages, $hasMore] = $this->messageRepo->findPage($conv, $pageSize, $before);

        return [
            'messages' => array_map(fn (ChatMessage $m) => $this->serializeMessage($m, $user), $messages),
            'has_more' => $hasMore,
        ];
    }

    public function sendText(User $user, int $conversationId, string $text, ?int $replyToId = null): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Mensagem vazia.');
        }

        $conv = $this->requireConversation($user, $conversationId);
        $replyTo = $this->resolveReplyTo($conv, $replyToId);
        $msg = new ChatMessage();
        $msg->setConversation($conv);
        $msg->setAuthor($user);
        $msg->setMessageType(ChatMessage::TYPE_TEXT);
        $msg->setBody($text);
        if ($replyTo) {
            $msg->setReplyTo($replyTo);
        }
        $conv->addMessage($msg);
        $this->markReadInternal($conv, $user);
        $this->em->flush();

        $serialized = $this->serializeMessage($msg, $user);
        $this->publishMessage($conv, $serialized, $user);

        return $serialized;
    }

    public function sendVoice(User $user, int $conversationId, UploadedFile $file, int $durationMs): array
    {
        $conv = $this->requireConversation($user, $conversationId);
        $dir = $this->projectDir . '/public/' . self::VOICE_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar pasta de áudio.');
        }

        $ext = $file->guessExtension() ?: 'webm';
        $filename = sprintf('voice_%d_%s.%s', $user->getId(), bin2hex(random_bytes(8)), $ext);
        $file->move($dir, $filename);

        $msg = new ChatMessage();
        $msg->setConversation($conv);
        $msg->setAuthor($user);
        $msg->setMessageType(ChatMessage::TYPE_VOICE);
        $msg->setBody('Mensagem de voz');
        $msg->setVoicePath(self::VOICE_DIR . '/' . $filename);
        $msg->setVoiceDurationMs(max(0, $durationMs));
        $conv->addMessage($msg);
        $this->markReadInternal($conv, $user);
        $this->em->flush();

        $serialized = $this->serializeMessage($msg, $user);
        $this->publishMessage($conv, $serialized, $user);

        return $serialized;
    }

    public function sendFile(User $user, int $conversationId, UploadedFile $file, ?int $replyToId = null): array
    {
        if ($file->getSize() > self::FILE_MAX_BYTES) {
            throw new \InvalidArgumentException('Arquivo muito grande (máx. 10 MB).');
        }

        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $isImage = str_starts_with($mime, 'image/');
        $isVideo = str_starts_with($mime, 'video/');
        $allowed = $isImage || $isVideo || \in_array($mime, [
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ], true);
        if (!$allowed) {
            throw new \InvalidArgumentException('Tipo de arquivo não permitido.');
        }

        $conv = $this->requireConversation($user, $conversationId);
        $dir = $this->projectDir . '/public/' . self::FILE_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar pasta de anexos.');
        }

        $ext = $file->guessExtension() ?: ($isImage ? 'jpg' : 'bin');
        $filename = sprintf('file_%d_%s.%s', $user->getId(), bin2hex(random_bytes(8)), $ext);
        $file->move($dir, $filename);

        $originalName = $file->getClientOriginalName() ?: $filename;
        $replyTo = $this->resolveReplyTo($conv, $replyToId);

        $msg = new ChatMessage();
        $msg->setConversation($conv);
        $msg->setAuthor($user);
        $msg->setMessageType($isImage ? ChatMessage::TYPE_IMAGE : ChatMessage::TYPE_FILE);
        $msg->setBody($isImage ? 'Imagem' : $originalName);
        $msg->setFilePath(self::FILE_DIR . '/' . $filename);
        $msg->setFileName($originalName);
        $msg->setFileMime($mime);
        if ($replyTo) {
            $msg->setReplyTo($replyTo);
        }
        $conv->addMessage($msg);
        $this->markReadInternal($conv, $user);
        $this->em->flush();

        $serialized = $this->serializeMessage($msg, $user);
        $this->publishMessage($conv, $serialized, $user);

        return $serialized;
    }

    public function deleteMessage(User $user, int $messageId): array
    {
        $msg = $this->messageRepo->find($messageId);
        if (!$msg) {
            throw new NotFoundHttpException('Mensagem não encontrada.');
        }
        $conv = $msg->getConversation();
        if (!$this->participantRepo->findOneForUser($conv, $user)) {
            throw new AccessDeniedHttpException('Sem acesso a esta conversa.');
        }
        if ($msg->getAuthor()?->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('Só é possível apagar suas próprias mensagens.');
        }
        if ($msg->getMessageType() === ChatMessage::TYPE_SYSTEM || $msg->isDeleted()) {
            throw new \InvalidArgumentException('Esta mensagem não pode ser apagada.');
        }

        $msg->setDeletedAt(new \DateTimeImmutable());
        $msg->setBody(null);
        $this->em->flush();

        $payload = [
            'id' => (string) $msg->getId(),
            'deleted' => true,
            'text' => 'Mensagem apagada',
            'type' => $msg->getMessageType(),
            'at' => $msg->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'role' => 'user',
        ];

        $conversationId = (int) $conv->getId();
        $this->mercure->publish($this->mercureTopics->conversation($conversationId), [
            'type' => 'message_deleted',
            'conversation_id' => $conversationId,
            'message' => $payload,
        ]);

        foreach ($conv->getParticipants() as $participant) {
            $viewer = $participant->getUser();
            if ($viewer->getId() === $user->getId()) {
                continue;
            }
            $this->mercure->publish($this->mercureTopics->user((int) $viewer->getId()), [
                'type' => 'message_deleted',
                'conversation_id' => $conversationId,
                'message' => array_merge($payload, ['role' => 'other']),
                'conversation' => $this->serializeConversation($conv, $viewer),
            ]);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public function getConversationDetail(User $user, int $conversationId): array
    {
        $conv = $this->requireConversation($user, $conversationId);
        $members = [];
        foreach ($conv->getParticipants() as $p) {
            $member = $p->getUser();
            $members[] = [
                'id' => $member->getId(),
                'name' => $member->getNome(),
                'initials' => $member->getInitials(),
                'is_self' => $member->getId() === $user->getId(),
            ];
        }

        usort($members, static fn (array $a, array $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        return array_merge($this->serializeConversation($conv, $user), [
            'members' => $members,
            'can_manage' => $conv->getType() === ChatConversation::TYPE_GROUP,
        ]);
    }

    /** @return array{all: list<array<string, mixed>>, images: list<array<string, mixed>>, documents: list<array<string, mixed>>, links: list<array<string, mixed>>, audio: list<array<string, mixed>>} */
    public function getMediaGallery(User $user, int $conversationId): array
    {
        $conv = $this->requireConversation($user, $conversationId);
        $images = [];
        $documents = [];
        $links = [];
        $audio = [];
        $all = [];

        foreach ($this->messageRepo->findGalleryItems($conv) as $msg) {
            try {
                $serialized = $this->serializeMessage($msg, $user);
            } catch (\Throwable) {
                continue;
            }
            if (empty($serialized['at'])) {
                continue;
            }
            $base = [
                'message_id' => $serialized['id'],
                'at' => $serialized['at'],
                'sender' => $serialized['sender'] ?? null,
            ];

            $type = $msg->getMessageType();
            if ($type === ChatMessage::TYPE_IMAGE) {
                $item = array_merge($base, [
                    'kind' => 'image',
                    'url' => $serialized['file_url'] ?? null,
                    'name' => $serialized['file_name'] ?? 'Imagem',
                ]);
                $images[] = $item;
                $all[] = $item;
                continue;
            }

            if ($type === ChatMessage::TYPE_VOICE) {
                $item = array_merge($base, [
                    'kind' => 'audio',
                    'url' => $serialized['voice_url'] ?? null,
                    'duration_ms' => $serialized['voice_duration_ms'] ?? 0,
                ]);
                $audio[] = $item;
                $all[] = $item;
                continue;
            }

            if ($type === ChatMessage::TYPE_FILE) {
                $mime = (string) ($msg->getFileMime() ?? '');
                if ($mime !== '' && str_starts_with($mime, 'video/')) {
                    $item = array_merge($base, [
                        'kind' => 'video',
                        'url' => $serialized['file_url'] ?? null,
                        'name' => $serialized['file_name'] ?? 'Vídeo',
                        'mime' => $mime,
                        'is_video' => true,
                    ]);
                    $images[] = $item;
                    $all[] = $item;
                } else {
                    $item = array_merge($base, [
                        'kind' => 'document',
                        'url' => $serialized['file_url'] ?? null,
                        'name' => $serialized['file_name'] ?? 'Arquivo',
                        'mime' => $mime !== '' ? $mime : null,
                    ]);
                    $documents[] = $item;
                    $all[] = $item;
                }
                continue;
            }

            if ($type === ChatMessage::TYPE_TEXT) {
                $body = trim((string) ($msg->getBody() ?? ''));
                if ($body === '') {
                    continue;
                }
                foreach ($this->extractLinksFromText($body) as $url) {
                    $item = array_merge($base, [
                        'kind' => 'link',
                        'url' => $url,
                        'label' => $body,
                    ]);
                    $links[] = $item;
                    $all[] = $item;
                }
            }
        }

        usort($all, static function (array $a, array $b): int {
            return strcmp($b['at'] ?? '', $a['at'] ?? '');
        });

        return [
            'all' => $all,
            'images' => $images,
            'documents' => $documents,
            'links' => $links,
            'audio' => $audio,
        ];
    }

    /** @return list<string> */
    private function extractLinksFromText(string $body): array
    {
        if (!preg_match_all('#https?://[^\s<>"\'\)\]]+#i', $body, $matches)) {
            return [];
        }

        $links = [];
        foreach ($matches[0] as $url) {
            $url = rtrim($url, '.,;:!?)');
            if ($url !== '' && !\in_array($url, $links, true)) {
                $links[] = $url;
            }
        }

        return $links;
    }

    public function renameGroup(User $user, int $conversationId, string $name): array
    {
        $conv = $this->requireGroup($user, $conversationId);
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Informe o nome do grupo.');
        }
        if (mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Nome do grupo muito longo.');
        }

        $conv->setName($name);
        $this->em->flush();

        $this->publishConversationUpdated($conv, $user, ($user->getNome() ?? 'Alguém') . ' renomeou o grupo.');

        return $this->serializeConversation($conv, $user);
    }

    /** @param list<int> $memberIds */
    public function addGroupMembers(User $user, int $conversationId, array $memberIds): array
    {
        $conv = $this->requireGroup($user, $conversationId);
        $empresa = $conv->getEmpresa();
        $existingIds = [];
        foreach ($conv->getParticipants() as $p) {
            $existingIds[(int) $p->getUser()->getId()] = true;
        }

        $addedNames = [];
        foreach (array_unique(array_map('intval', $memberIds)) as $memberId) {
            if ($memberId <= 0 || isset($existingIds[$memberId])) {
                continue;
            }
            $member = $this->userRepo->find($memberId);
            if (!$member || !$member->isAtivo() || $member->getEmpresa()?->getId() !== $empresa->getId()) {
                continue;
            }
            $conv->addParticipant($this->makeParticipant($conv, $member));
            $existingIds[$memberId] = true;
            $addedNames[] = $member->getNome() ?? 'Usuário';
        }

        if ($addedNames === []) {
            throw new \InvalidArgumentException('Nenhum participante novo para adicionar.');
        }

        $system = new ChatMessage();
        $system->setConversation($conv);
        $system->setMessageType(ChatMessage::TYPE_SYSTEM);
        $system->setBody(($user->getNome() ?? 'Alguém') . ' adicionou ' . implode(', ', $addedNames) . '.');
        $conv->addMessage($system);
        $this->em->flush();

        $this->publishConversationUpdated($conv, $user, $system->getBody() ?? '');

        return $this->serializeConversation($conv, $user);
    }

    public function leaveGroup(User $user, int $conversationId): void
    {
        $conv = $this->requireGroup($user, $conversationId);
        $participant = $this->participantRepo->findOneForUser($conv, $user);
        if (!$participant) {
            throw new AccessDeniedHttpException('Você não participa deste grupo.');
        }

        if ($conv->getParticipants()->count() <= 1) {
            throw new \InvalidArgumentException('Não é possível sair sendo o único participante.');
        }

        $system = new ChatMessage();
        $system->setConversation($conv);
        $system->setMessageType(ChatMessage::TYPE_SYSTEM);
        $system->setBody(($user->getNome() ?? 'Alguém') . ' saiu do grupo.');
        $conv->addMessage($system);

        $this->em->remove($participant);
        $this->em->flush();

        $conversationId = (int) $conv->getId();
        $this->mercure->publish($this->mercureTopics->user((int) $user->getId()), [
            'type' => 'conversation_left',
            'conversation_id' => $conversationId,
        ]);

        foreach ($conv->getParticipants() as $p) {
            $viewer = $p->getUser();
            $this->mercure->publish($this->mercureTopics->user((int) $viewer->getId()), [
                'type' => 'conversation_updated',
                'conversation_id' => $conversationId,
                'conversation' => $this->serializeConversation($conv, $viewer),
                'system_message' => $this->serializeMessage($system, $viewer),
            ]);
        }
    }

    public function publishTyping(User $user, int $conversationId): void
    {
        $conv = $this->requireConversation($user, $conversationId);
        $conversationId = (int) $conv->getId();
        $event = [
            'type' => 'typing',
            'conversation_id' => $conversationId,
            'user_id' => $user->getId(),
            'user_name' => $user->getNome() ?? 'Alguém',
        ];

        $this->mercure->publish($this->mercureTopics->conversation($conversationId), $event);

        foreach ($conv->getParticipants() as $participant) {
            $viewer = $participant->getUser();
            if ($viewer->getId() === $user->getId()) {
                continue;
            }
            $this->mercure->publish($this->mercureTopics->user((int) $viewer->getId()), $event);
        }
    }

    public function createDirect(User $user, Empresa $empresa, int $targetUserId): array
    {
        $target = $this->userRepo->find($targetUserId);
        if (!$target || !$target->isAtivo() || $target->getEmpresa()?->getId() !== $empresa->getId()) {
            throw new NotFoundHttpException('Usuário não encontrado.');
        }
        if ($target->getId() === $user->getId()) {
            throw new \InvalidArgumentException('Não é possível conversar consigo mesmo.');
        }

        $existing = $this->conversationRepo->findDirectBetween($empresa, $user, $target)
            ?? $this->conversationRepo->findDirectBetween($empresa, $target, $user);
        if ($existing) {
            return $this->serializeConversation($existing, $user);
        }

        $conv = new ChatConversation();
        $conv->setEmpresa($empresa);
        $conv->setType(ChatConversation::TYPE_DIRECT);
        $conv->addParticipant($this->makeParticipant($conv, $user));
        $conv->addParticipant($this->makeParticipant($conv, $target));
        $this->em->persist($conv);
        $this->em->flush();

        $forCreator = $this->serializeConversation($conv, $user);
        $forTarget = $this->serializeConversation($conv, $target);
        $this->publishConversationCreated($conv, $forCreator, $forTarget, $user, $target);

        return $forCreator;
    }

    /** @param list<int> $memberIds */
    public function createGroup(User $user, Empresa $empresa, string $name, array $memberIds): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Informe o nome do grupo.');
        }
        if (mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Nome do grupo muito longo.');
        }

        $memberIds = array_values(array_unique(array_filter(
            array_map('intval', $memberIds),
            static fn (int $id) => $id > 0 && $id !== $user->getId(),
        )));
        if ($memberIds === []) {
            throw new \InvalidArgumentException('Selecione pelo menos um participante.');
        }

        $conv = new ChatConversation();
        $conv->setEmpresa($empresa);
        $conv->setType(ChatConversation::TYPE_GROUP);
        $conv->setName($name);
        $conv->addParticipant($this->makeParticipant($conv, $user));

        $added = 0;
        foreach ($memberIds as $memberId) {
            $member = $this->userRepo->find($memberId);
            if (!$member || !$member->isAtivo() || $member->getEmpresa()?->getId() !== $empresa->getId()) {
                continue;
            }
            $conv->addParticipant($this->makeParticipant($conv, $member));
            ++$added;
        }

        if ($added === 0) {
            throw new NotFoundHttpException('Nenhum participante válido encontrado.');
        }

        $welcome = new ChatMessage();
        $welcome->setConversation($conv);
        $welcome->setMessageType(ChatMessage::TYPE_SYSTEM);
        $welcome->setBody(($user->getNome() ?? 'Alguém') . ' criou o grupo.');
        $conv->addMessage($welcome);

        $this->em->persist($conv);
        $this->em->flush();

        $forCreator = $this->serializeConversation($conv, $user);
        $this->publishGroupCreated($conv, $user);

        return $forCreator;
    }

    public function markRead(User $user, int $conversationId): void
    {
        $conv = $this->requireConversation($user, $conversationId);
        $this->markReadInternal($conv, $user);
        $this->em->flush();
    }

    public function getUnreadCount(User $user, ?Empresa $empresa): int
    {
        if (!$empresa) {
            return 0;
        }

        $total = 0;
        foreach ($this->conversationRepo->findForUser($user, $empresa) as $conv) {
            $total += $this->countUnread($conv, $user);
        }

        return $total;
    }

    public function postCallSignal(User $user, int $conversationId, string $type, string $payload, ?int $toUserId = null): array
    {
        $conv = $this->requireConversation($user, $conversationId);
        if ($conv->getType() !== ChatConversation::TYPE_DIRECT) {
            throw new AccessDeniedHttpException('Chamada de voz disponível apenas em conversas diretas.');
        }

        $toUser = null;
        if ($toUserId) {
            $toUser = $this->userRepo->find($toUserId);
        } else {
            foreach ($conv->getParticipants() as $p) {
                if ($p->getUser()->getId() !== $user->getId()) {
                    $toUser = $p->getUser();
                    break;
                }
            }
        }

        $signal = new ChatCallSignal();
        $signal->setConversation($conv);
        $signal->setFromUser($user);
        $signal->setToUser($toUser);
        $signal->setSignalType($type);
        $signal->setPayload($payload);
        $this->em->persist($signal);
        $this->em->flush();

        $payload = [
            'id' => $signal->getId(),
            'type' => $signal->getSignalType(),
            'from_user_id' => $user->getId(),
            'to_user_id' => $toUser?->getId(),
            'payload' => $signal->getPayload(),
            'at' => $signal->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        $this->publishCallSignal($conv, $payload, $toUser);

        return $payload;
    }

    /** @return list<array<string, mixed>> */
    public function pollCallSignals(User $user, int $conversationId, string $sinceIso): array
    {
        $conv = $this->requireConversation($user, $conversationId);
        $since = new \DateTimeImmutable($sinceIso);

        return array_map(static fn (ChatCallSignal $s) => [
            'id' => $s->getId(),
            'type' => $s->getSignalType(),
            'from_user_id' => $s->getFromUser()->getId(),
            'to_user_id' => $s->getToUser()?->getId(),
            'payload' => $s->getPayload(),
            'at' => $s->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $this->signalRepo->findSince($conv, $user, $since));
    }

    /** @return list<array<string, mixed>> */
    public function getColleagues(User $user, Empresa $empresa): array
    {
        $out = [];
        foreach ($this->userRepo->findActiveByEmpresa($empresa) as $colleague) {
            if ($colleague->getId() === $user->getId()) {
                continue;
            }
            $out[] = [
                'id' => $colleague->getId(),
                'name' => $colleague->getNome(),
                'initials' => $colleague->getInitials(),
                'online' => true,
            ];
        }

        return $out;
    }

    private function requireConversation(User $user, int $conversationId): ChatConversation
    {
        $conv = $this->conversationRepo->find($conversationId);
        if (!$conv) {
            throw new NotFoundHttpException('Conversa não encontrada.');
        }
        if (!$this->participantRepo->findOneForUser($conv, $user)) {
            throw new AccessDeniedHttpException('Sem acesso a esta conversa.');
        }

        return $conv;
    }

    private function makeParticipant(ChatConversation $conv, User $user): ChatParticipant
    {
        $p = new ChatParticipant();
        $p->setConversation($conv);
        $p->setUser($user);

        return $p;
    }

    private function markReadInternal(ChatConversation $conv, User $user): void
    {
        $participant = $this->participantRepo->findOneForUser($conv, $user);
        if ($participant) {
            $participant->setLastReadAt(new \DateTimeImmutable());
            $this->em->persist($participant);
        }
    }

    private function countUnread(ChatConversation $conv, User $user): int
    {
        $participant = $this->participantRepo->findOneForUser($conv, $user);
        $since = $participant?->getLastReadAt();

        $qb = $this->messageRepo->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.conversation = :conv')
            ->andWhere('m.author != :user OR m.author IS NULL')
            ->setParameter('conv', $conv)
            ->setParameter('user', $user);

        if ($since) {
            $qb->andWhere('m.createdAt > :since')->setParameter('since', $since);
        }

        $qb->andWhere('m.deletedAt IS NULL');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return array<string, mixed> */
    private function serializeConversation(ChatConversation $conv, User $viewer): array
    {
        $latest = $this->messageRepo->findLatest($conv);
        $unread = $this->countUnread($conv, $viewer);
        $displayName = $conv->getName() ?? '';
        $initials = '??';
        $online = false;
        $peerId = null;

        if ($conv->getType() === ChatConversation::TYPE_DIRECT) {
            foreach ($conv->getParticipants() as $p) {
                if ($p->getUser()->getId() !== $viewer->getId()) {
                    $displayName = $p->getUser()->getNome() ?? 'Usuário';
                    $initials = $p->getUser()->getInitials();
                    $online = true;
                    $peerId = $p->getUser()->getId();
                    break;
                }
            }
        } else {
            $words = preg_split('/\s+/', trim($displayName)) ?: [];
            $initials = mb_strtoupper(
                mb_substr($words[0] ?? 'G', 0, 1) . mb_substr($words[1] ?? '', 0, 1),
            ) ?: 'GR';
        }

        $preview = '';
        $sortAt = $conv->getCreatedAt()->format(\DateTimeInterface::ATOM);
        if ($latest) {
            $sortAt = $latest->getCreatedAt()->format(\DateTimeInterface::ATOM);
            $preview = $this->messagePreview($latest, $viewer);
        }

        return [
            'id' => (string) $conv->getId(),
            'name' => $displayName,
            'type' => $conv->getType(),
            'initials' => $initials,
            'online' => $online,
            'peer_user_id' => $peerId,
            'member_count' => $conv->getType() === ChatConversation::TYPE_GROUP
                ? $conv->getParticipants()->count()
                : null,
            'unread' => $unread,
            'preview' => $preview,
            'time_label' => $this->timeLabel($latest?->getCreatedAt()),
            'sort_at' => $sortAt,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeMessage(ChatMessage $msg, User $viewer): array
    {
        if ($msg->isDeleted()) {
            $author = $msg->getAuthor();
            $isMine = $author && $author->getId() === $viewer->getId();

            return [
                'id' => (string) $msg->getId(),
                'role' => $isMine ? 'user' : 'other',
                'sender' => $author?->getNome(),
                'text' => 'Mensagem apagada',
                'type' => $msg->getMessageType(),
                'deleted' => true,
                'at' => $msg->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        $author = $msg->getAuthor();
        $isMine = $author && $author->getId() === $viewer->getId();

        $data = [
            'id' => (string) $msg->getId(),
            'role' => $msg->getMessageType() === ChatMessage::TYPE_SYSTEM ? 'system' : ($isMine ? 'user' : 'other'),
            'sender' => $author?->getNome(),
            'text' => $msg->getBody() ?? '',
            'type' => $msg->getMessageType(),
            'at' => $msg->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($msg->getReplyTo()) {
            $reply = $msg->getReplyTo();
            $data['reply_to'] = [
                'id' => (string) $reply->getId(),
                'sender' => $reply->getAuthor()?->getNome(),
                'text' => $reply->isDeleted() ? 'Mensagem apagada' : ($reply->getBody() ?? ''),
                'type' => $reply->getMessageType(),
            ];
        }

        if ($msg->getMessageType() === ChatMessage::TYPE_VOICE && $msg->getVoicePath()) {
            $data['voice_url'] = '/' . ltrim($msg->getVoicePath(), '/');
            $data['voice_duration_ms'] = $msg->getVoiceDurationMs() ?? 0;
            $data['text'] = 'Mensagem de voz';
        }

        if (\in_array($msg->getMessageType(), [ChatMessage::TYPE_IMAGE, ChatMessage::TYPE_FILE], true) && $msg->getFilePath()) {
            $data['file_url'] = '/' . ltrim($msg->getFilePath(), '/');
            $data['file_name'] = $msg->getFileName() ?? 'Arquivo';
            $data['file_mime'] = $msg->getFileMime();
            if ($msg->getMessageType() === ChatMessage::TYPE_IMAGE) {
                $data['text'] = 'Imagem';
            }
        }

        return $data;
    }

    private function messagePreview(ChatMessage $msg, User $viewer): string
    {
        if ($msg->isDeleted()) {
            $author = $msg->getAuthor();
            if ($author && $author->getId() === $viewer->getId()) {
                return 'Você: Mensagem apagada';
            }

            return 'Mensagem apagada';
        }
        if ($msg->getMessageType() === ChatMessage::TYPE_SYSTEM) {
            return $msg->getBody() ?? '';
        }
        if ($msg->getMessageType() === ChatMessage::TYPE_VOICE) {
            $prefix = $msg->getAuthor()?->getId() === $viewer->getId() ? 'Você: ' : '';
            return $prefix . 'Mensagem de voz';
        }
        if ($msg->getMessageType() === ChatMessage::TYPE_IMAGE) {
            $prefix = $msg->getAuthor()?->getId() === $viewer->getId() ? 'Você: ' : '';
            return $prefix . 'Imagem';
        }
        if ($msg->getMessageType() === ChatMessage::TYPE_FILE) {
            $prefix = $msg->getAuthor()?->getId() === $viewer->getId() ? 'Você: ' : '';
            return $prefix . ($msg->getFileName() ?? 'Arquivo');
        }

        $author = $msg->getAuthor();
        if ($author && $author->getId() === $viewer->getId()) {
            return 'Você: ' . ($msg->getBody() ?? '');
        }
        if ($msg->getConversation()->getType() === ChatConversation::TYPE_GROUP && $author) {
            $first = explode(' ', $author->getNome() ?? '')[0];

            return $first . ': ' . ($msg->getBody() ?? '');
        }

        return $msg->getBody() ?? '';
    }

    private function timeLabel(?\DateTimeImmutable $at): string
    {
        if (!$at) {
            return '';
        }

        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $at->getTimestamp();
        if ($diff < 60) {
            return 'Agora';
        }
        if ($diff < 3600) {
            return max(1, (int) floor($diff / 60)) . ' min';
        }
        if ($at->format('Y-m-d') === $now->format('Y-m-d')) {
            return $at->format('H:i');
        }
        $yesterday = $now->modify('-1 day');
        if ($at->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            return 'Ontem';
        }

        return $at->format('d/m');
    }

    /** @param array<string, mixed> $message */
    private function publishMessage(ChatConversation $conv, array $message, User $sender): void
    {
        $conversationId = (int) $conv->getId();
        $this->mercure->publish(
            $this->mercureTopics->conversation($conversationId),
            [
                'type' => 'message',
                'conversation_id' => $conversationId,
                'message' => $message,
            ],
        );

        foreach ($conv->getParticipants() as $participant) {
            $viewer = $participant->getUser();
            if ($viewer->getId() === $sender->getId()) {
                continue;
            }
            $this->mercure->publish(
                $this->mercureTopics->user((int) $viewer->getId()),
                [
                    'type' => 'conversation_activity',
                    'conversation_id' => $conversationId,
                    'conversation' => $this->serializeConversation($conv, $viewer),
                    'message' => $message,
                ],
            );
        }
    }

    /** @param array<string, mixed> $signal */
    private function publishCallSignal(ChatConversation $conv, array $signal, ?User $toUser): void
    {
        $conversationId = (int) $conv->getId();
        $event = [
            'type' => 'call_signal',
            'conversation_id' => $conversationId,
            'signal' => $signal,
        ];

        $this->mercure->publish($this->mercureTopics->conversation($conversationId), $event);

        if ($toUser) {
            $this->mercure->publish($this->mercureTopics->user((int) $toUser->getId()), $event);
        }
    }

    /** @param array<string, mixed> $forCreator @param array<string, mixed> $forTarget */
    private function publishConversationCreated(
        ChatConversation $conv,
        array $forCreator,
        array $forTarget,
        User $creator,
        User $target,
    ): void {
        $conversationId = (int) $conv->getId();
        $topic = $this->mercureTopics->conversation($conversationId);

        $this->mercure->publish($topic, [
            'type' => 'conversation_created',
            'conversation_id' => $conversationId,
            'conversation' => $forCreator,
        ]);

        $this->mercure->publish($this->mercureTopics->user((int) $creator->getId()), [
            'type' => 'conversation_created',
            'conversation_id' => $conversationId,
            'conversation' => $forCreator,
            'resubscribe' => true,
        ]);

        $this->mercure->publish($this->mercureTopics->user((int) $target->getId()), [
            'type' => 'conversation_created',
            'conversation_id' => $conversationId,
            'conversation' => $forTarget,
            'resubscribe' => true,
        ]);
    }

    private function publishGroupCreated(ChatConversation $conv, User $creator): void
    {
        $conversationId = (int) $conv->getId();
        $topic = $this->mercureTopics->conversation($conversationId);

        foreach ($conv->getParticipants() as $participant) {
            $viewer = $participant->getUser();
            $payload = $this->serializeConversation($conv, $viewer);

            $this->mercure->publish($this->mercureTopics->user((int) $viewer->getId()), [
                'type' => 'conversation_created',
                'conversation_id' => $conversationId,
                'conversation' => $payload,
                'resubscribe' => true,
            ]);
        }

        $creatorPayload = $this->serializeConversation($conv, $creator);
        $this->mercure->publish($topic, [
            'type' => 'conversation_created',
            'conversation_id' => $conversationId,
            'conversation' => $creatorPayload,
        ]);
    }

    private function requireGroup(User $user, int $conversationId): ChatConversation
    {
        $conv = $this->requireConversation($user, $conversationId);
        if ($conv->getType() !== ChatConversation::TYPE_GROUP) {
            throw new AccessDeniedHttpException('Disponível apenas para grupos.');
        }

        return $conv;
    }

    private function resolveReplyTo(ChatConversation $conv, ?int $replyToId): ?ChatMessage
    {
        if (!$replyToId) {
            return null;
        }
        $reply = $this->messageRepo->find($replyToId);
        if (!$reply || $reply->getConversation()->getId() !== $conv->getId()) {
            throw new NotFoundHttpException('Mensagem para resposta não encontrada.');
        }
        if ($reply->getMessageType() === ChatMessage::TYPE_SYSTEM) {
            throw new \InvalidArgumentException('Não é possível responder a esta mensagem.');
        }

        return $reply;
    }

    private function publishConversationUpdated(ChatConversation $conv, User $actor, string $systemBody): void
    {
        $conversationId = (int) $conv->getId();
        $latest = $this->messageRepo->findLatest($conv);
        $systemPayload = $latest ? $this->serializeMessage($latest, $actor) : null;

        foreach ($conv->getParticipants() as $participant) {
            $viewer = $participant->getUser();
            $payload = [
                'type' => 'conversation_updated',
                'conversation_id' => $conversationId,
                'conversation' => $this->serializeConversation($conv, $viewer),
            ];
            if ($systemPayload && $latest) {
                $payload['system_message'] = $this->serializeMessage($latest, $viewer);
            }
            $this->mercure->publish($this->mercureTopics->user((int) $viewer->getId()), $payload);
        }

        $this->mercure->publish($this->mercureTopics->conversation($conversationId), [
            'type' => 'conversation_updated',
            'conversation_id' => $conversationId,
            'conversation' => $this->serializeConversation($conv, $actor),
            'system_message' => $systemPayload,
        ]);
    }
}
