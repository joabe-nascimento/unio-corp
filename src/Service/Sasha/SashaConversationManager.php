<?php

namespace App\Service\Sasha;

use App\Entity\Empresa;
use App\Entity\Sasha\SashaConversation;
use App\Entity\Sasha\SashaMessage;
use App\Entity\User;
use App\Repository\Sasha\SashaConversationRepository;
use App\Repository\Sasha\SashaMessageRepository;

final class SashaConversationManager
{
    public function __construct(
        private SashaConversationRepository $conversationRepo,
        private SashaMessageRepository $messageRepo,
    ) {
    }

    public function getUserConversations(User $user, ?Empresa $empresa = null, int $limit = 50): array
    {
        $conversations = $this->conversationRepo->findByUser($user, $empresa, $limit);

        return array_map(fn (SashaConversation $c) => [
            'id' => $c->getId(),
            'title' => $c->getTitle(),
            'context' => $c->getContext(),
            'context_id' => $c->getContextId(),
            'created_at' => $c->getCreatedAt()->format('c'),
            'updated_at' => $c->getUpdatedAt()->format('c'),
            'pinned' => $c->isPinned(),
            'message_count' => $c->getMessages()->count(),
        ], $conversations);
    }

    public function getConversation(int $id, User $user): ?array
    {
        $conversation = $this->conversationRepo->find($id);

        if ($conversation === null || $conversation->getUser() !== $user) {
            return null;
        }

        return [
            'id' => $conversation->getId(),
            'title' => $conversation->getTitle(),
            'context' => $conversation->getContext(),
            'context_id' => $conversation->getContextId(),
            'created_at' => $conversation->getCreatedAt()->format('c'),
            'updated_at' => $conversation->getUpdatedAt()->format('c'),
            'pinned' => $conversation->isPinned(),
            'messages' => array_map(fn (SashaMessage $m) => [
                'id' => $m->getId(),
                'role' => $m->getRole(),
                'content' => $m->getContent(),
                'created_at' => $m->getCreatedAt()->format('c'),
                'rating' => $m->getRating(),
                'metadata' => $m->getMetadata(),
            ], $conversation->getMessages()->toArray()),
        ];
    }

    public function getConversationEntity(int $id, User $user): ?SashaConversation
    {
        $conversation = $this->conversationRepo->find($id);

        if ($conversation === null || $conversation->getUser() !== $user) {
            return null;
        }

        return $conversation;
    }

    public function createOrGetConversation(User $user, ?Empresa $empresa, ?string $context, ?string $contextId, string $firstMessage): SashaConversation
    {
        if ($context !== null && $contextId !== null) {
            $existing = $this->conversationRepo->findByContext($user, $context, $contextId);
            if ($existing !== null) {
                return $existing;
            }
        }

        $conversation = new SashaConversation();
        $conversation->setUser($user);
        $conversation->setEmpresa($empresa);
        $conversation->setContext($context);
        $conversation->setContextId($contextId);
        $conversation->setTitle($this->generateTitle($firstMessage));

        $this->conversationRepo->save($conversation, true);

        return $conversation;
    }

    public function addMessage(SashaConversation $conversation, string $role, string $content, ?array $metadata = null): SashaMessage
    {
        $message = new SashaMessage();
        $message->setConversation($conversation);
        $message->setRole($role);
        $message->setContent($content);
        $message->setMetadata($metadata);

        $conversation->setUpdatedAt(new \DateTime());
        $conversation->addMessage($message);

        $this->messageRepo->save($message);
        $this->conversationRepo->save($conversation, true);

        return $message;
    }

    public function rateMessage(int $messageId, User $user, int $rating, ?string $feedback = null): bool
    {
        $message = $this->messageRepo->find($messageId);

        if ($message === null || $message->getConversation()->getUser() !== $user) {
            return false;
        }

        $message->setRating($rating);
        $message->setFeedback($feedback);

        $this->messageRepo->save($message, true);

        return true;
    }

    public function deleteConversation(int $id, User $user): bool
    {
        $conversation = $this->conversationRepo->find($id);

        if ($conversation === null || $conversation->getUser() !== $user) {
            return false;
        }

        $this->conversationRepo->remove($conversation, true);

        return true;
    }

    public function togglePin(int $id, User $user): bool
    {
        $conversation = $this->conversationRepo->find($id);

        if ($conversation === null || $conversation->getUser() !== $user) {
            return false;
        }

        $conversation->setPinned(!$conversation->isPinned());
        $this->conversationRepo->save($conversation, true);

        return true;
    }

    private function generateTitle(string $message): string
    {
        $title = mb_substr($message, 0, 60);
        if (mb_strlen($message) > 60) {
            $title .= '...';
        }

        return $title;
    }
}
