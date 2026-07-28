<?php

namespace App\Service\Juridico;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Agrega o consumo de tokens do JurisFlow (Azure OpenAI) para exibição no shell.
 */
final class AiTokenUsageService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private JurisFlowAiClient $jurisFlowAi,
        private LoggerInterface $logger,
        private bool $enabled,
        private string $baseUrl,
    ) {
    }

    /**
     * @return array{
     *     online: bool,
     *     provider: string,
     *     model: string,
     *     today: array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int},
     *     month: array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int},
     *     lifetime: array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int},
     *     last_request_at: ?string
     * }|null
     */
    public function getSummary(): ?array
    {
        if (!$this->enabled || $this->baseUrl === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/v1/usage', [
                'timeout' => 4,
            ]);

            if ($response->getStatusCode() !== 200) {
                return $this->fallbackFromStatus();
            }

            $data = $response->toArray(false);

            return [
                'online' => true,
                'provider' => (string) ($data['provider'] ?? 'azure'),
                'model' => (string) ($data['model'] ?? ''),
                'today' => $this->normalizeBucket($data['today'] ?? []),
                'month' => $this->normalizeBucket($data['month'] ?? []),
                'lifetime' => $this->normalizeBucket($data['lifetime'] ?? []),
                'last_request_at' => isset($data['last_request_at']) ? (string) $data['last_request_at'] : null,
            ];
        } catch (\Throwable $e) {
            $this->logger->debug('Resumo de tokens IA indisponível: {msg}', ['msg' => $e->getMessage()]);

            return $this->fallbackFromStatus();
        }
    }

    /** @param array<string, mixed> $bucket */
    private function normalizeBucket(array $bucket): array
    {
        return [
            'prompt_tokens' => (int) ($bucket['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($bucket['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($bucket['total_tokens'] ?? 0),
            'requests' => (int) ($bucket['requests'] ?? 0),
        ];
    }

    private function fallbackFromStatus(): ?array
    {
        $status = $this->jurisFlowAi->status();
        if ($status === null) {
            return [
                'online' => false,
                'provider' => 'azure',
                'model' => '',
                'today' => $this->normalizeBucket([]),
                'month' => $this->normalizeBucket([]),
                'lifetime' => $this->normalizeBucket([]),
                'last_request_at' => null,
            ];
        }

        return [
            'online' => true,
            'provider' => (string) ($status['llm_provider'] ?? 'azure'),
            'model' => (string) ($status['llm_model'] ?? ''),
            'today' => $this->normalizeBucket([]),
            'month' => $this->normalizeBucket([]),
            'lifetime' => $this->normalizeBucket([]),
            'last_request_at' => null,
        ];
    }
}
