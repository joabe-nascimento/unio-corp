<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class ChatMercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {}

    /** @param string|string[] $topics */
    public function publish(string|array $topics, array $payload, bool $private = true): void
    {
        try {
            $this->hub->publish(new Update(
                $topics,
                json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
                private: $private,
                type: $payload['type'] ?? null,
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('Chat Mercure publish failed: {message}', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
