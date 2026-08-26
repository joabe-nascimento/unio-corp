<?php

namespace App\Service\Juridico;

use App\Contract\LegalAiClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Agrega o consumo de tokens do JurisFlow (tempo real) + Azure Monitor (histórico sincronizado).
 */
final class AiTokenUsageService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LegalAiClientInterface $jurisFlowAi,
        private AzureTokenUsageStore $azureStore,
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
     *     last_request_at: ?string,
     *     synced_at: ?string
     * }|null
     */
    public function getSummary(): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $azure = $this->azureStore->load();
        $jurisflow = $this->fetchJurisFlowSummary();

        if ($azure === null && $jurisflow === null) {
            return $this->fallbackFromStatus();
        }

        $today = $this->mergeBuckets($azure['today'] ?? null, $jurisflow['today'] ?? null);
        $month = $this->mergeBuckets($azure['month'] ?? null, $jurisflow['month'] ?? null);
        $lifetime = $this->mergeBuckets($azure['lifetime'] ?? null, $jurisflow['lifetime'] ?? null);

        return [
            'online' => $jurisflow['online'] ?? true,
            'provider' => (string) ($azure['provider'] ?? $jurisflow['provider'] ?? 'azure'),
            'model' => (string) ($jurisflow['model'] ?? $azure['model'] ?? ''),
            'today' => $today,
            'month' => $month,
            'lifetime' => $lifetime,
            'last_request_at' => $jurisflow['last_request_at'] ?? null,
            'synced_at' => $azure['synced_at'] ?? null,
        ];
    }

    /**
     * @return array{
     *     online: bool,
     *     provider: string,
     *     model: string,
     *     today: array<string, int>,
     *     month: array<string, int>,
     *     lifetime: array<string, int>,
     *     last_request_at: ?string
     * }|null
     */
    private function fetchJurisFlowSummary(): ?array
    {
        if ($this->baseUrl === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/v1/usage', [
                'timeout' => 4,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
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

            return null;
        }
    }

    /** @param array<string, int>|null $azure @param array<string, int>|null $live */
    private function mergeBuckets(?array $azure, ?array $live): array
    {
        $azure = $azure ?? $this->normalizeBucket([]);
        $live = $live ?? $this->normalizeBucket([]);

        return [
            'prompt_tokens' => max($azure['prompt_tokens'], $live['prompt_tokens']),
            'completion_tokens' => max($azure['completion_tokens'], $live['completion_tokens']),
            'total_tokens' => max($azure['total_tokens'], $live['total_tokens']),
            'requests' => max($azure['requests'], $live['requests']),
        ];
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
                'synced_at' => null,
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
            'synced_at' => null,
        ];
    }
}
