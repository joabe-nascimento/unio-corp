<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ClinicWebhookDispatcher
{
    public function __construct(
        private ClinicIntegrationConfigService $integrationConfig,
        private HttpClientInterface $httpClient,
        private ?LoggerInterface $logger = null,
    ) {}

    /** @param array<string, mixed> $payload */
    public function dispatch(Empresa $empresa, string $event, array $payload): bool
    {
        $config = $this->integrationConfig->get($empresa);
        $url = $config['webhook_url'];
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $events = $config['webhook_events'];
        if (!\in_array($event, $events, true)) {
            return false;
        }

        try {
            $this->httpClient->request('POST', $url, [
                'json' => [
                    'event' => $event,
                    'empresa_id' => $empresa->getId(),
                    'empresa' => $empresa->getNome(),
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                    'payload' => $payload,
                ],
                'timeout' => 8,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger?->warning('Webhook clínico falhou: {message}', [
                'message' => $e->getMessage(),
                'event' => $event,
                'empresa_id' => $empresa->getId(),
            ]);

            return false;
        }
    }
}
