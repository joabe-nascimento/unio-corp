<?php

namespace App\Service;

final class ChatMercureTopics
{
    public function __construct(
        private readonly string $topicBase,
    ) {}

    public function conversation(int $conversationId): string
    {
        return rtrim($this->topicBase, '/') . '/conversation/' . $conversationId;
    }

    public function user(int $userId): string
    {
        return rtrim($this->topicBase, '/') . '/user/' . $userId;
    }
}
