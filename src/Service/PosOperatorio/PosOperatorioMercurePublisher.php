<?php

namespace App\Service\PosOperatorio;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class PosOperatorioMercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly PosOperatorioMercureTopics $topics,
        private readonly LoggerInterface $logger,
    ) {}

    public function publishAlertasUpdate(int $empresaId, array $payload): void
    {
        $this->publish($this->topics->alertas($empresaId), array_merge([
            'type' => 'pos_operatorio.alerta_update',
            'empresa_id' => $empresaId,
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $payload));
    }

    private function publish(string $topic, array $payload): void
    {
        try {
            $this->hub->publish(new Update(
                $topic,
                json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
                private: true,
                type: $payload['type'] ?? null,
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('Pós-Op Mercure publish failed: {message}', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
