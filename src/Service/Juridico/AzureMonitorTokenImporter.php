<?php

declare(strict_types=1);

namespace App\Service\Juridico;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sincroniza consumo de tokens/requests da Azure OpenAI via Azure Monitor API.
 */
final class AzureMonitorTokenImporter
{
    private const METRIC_TOTAL_TOKENS = 'TotalTokens';
    private const METRIC_TOTAL_CALLS = 'TotalCalls';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AzureTokenUsageStore $store,
        private readonly LoggerInterface $logger,
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $resourceId,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->tenantId !== ''
            && $this->clientId !== ''
            && $this->clientSecret !== ''
            && $this->resourceId !== '';
    }

    /**
     * @return array{
     *     synced_at: string,
     *     today: array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int},
     *     month: array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int},
     *     lifetime: array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int}
     * }
     */
    public function sync(): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException(
                'Azure Monitor não configurado. Defina AZURE_TENANT_ID, AZURE_CLIENT_ID, AZURE_CLIENT_SECRET e AZURE_OPENAI_RESOURCE_ID no .env.',
            );
        }

        $accessToken = $this->fetchAccessToken();
        $tz = new \DateTimeZone('America/Sao_Paulo');
        $now = new \DateTimeImmutable('now', $tz);

        $summary = [
            'today' => $this->fetchBucket($accessToken, $now->modify('-24 hours'), $now),
            'month' => $this->fetchBucket(
                $accessToken,
                $now->modify('first day of this month')->setTime(0, 0, 0),
                $now,
            ),
            'lifetime' => $this->fetchBucket($accessToken, $now->modify('-90 days'), $now),
        ];

        $summary['month'] = $this->maxBucket($summary['month'], $summary['today']);
        $summary['lifetime'] = $this->maxBucket($summary['lifetime'], $summary['month']);

        $this->store->save($summary);

        $this->logger->info('Azure OpenAI usage synced', [
            'today_tokens' => $summary['today']['total_tokens'],
            'month_tokens' => $summary['month']['total_tokens'],
            'lifetime_tokens' => $summary['lifetime']['total_tokens'],
        ]);

        return [
            'synced_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            ...$summary,
        ];
    }

    private function fetchAccessToken(): string
    {
        $response = $this->httpClient->request('POST', sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
            $this->tenantId,
        ), [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://management.azure.com/.default',
            ],
        ]);

        $data = $response->toArray(false);
        $token = (string) ($data['access_token'] ?? '');

        if ($token === '') {
            $error = (string) ($data['error_description'] ?? $data['error'] ?? 'Token vazio');

            throw new \RuntimeException('Falha ao autenticar na Azure: ' . $error);
        }

        return $token;
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int}
     */
    private function fetchBucket(
        string $accessToken,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): array {
        $totalTokens = $this->fetchMetricSum($accessToken, self::METRIC_TOTAL_TOKENS, $start, $end);
        $totalCalls = $this->fetchMetricSum($accessToken, self::METRIC_TOTAL_CALLS, $start, $end);

        if ($totalTokens === 0) {
            $prompt = $this->fetchMetricSum($accessToken, 'ProcessedPromptTokens', $start, $end);
            $completion = $this->fetchMetricSum($accessToken, 'GeneratedCompletionTokens', $start, $end);
            $totalTokens = $prompt + $completion;
        }

        $promptTokens = $totalTokens > 0 ? (int) round($totalTokens * 0.5) : 0;

        return [
            'total_tokens' => $totalTokens,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => max(0, $totalTokens - $promptTokens),
            'requests' => $totalCalls,
        ];
    }

    private function fetchMetricSum(
        string $accessToken,
        string $metricName,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): int {
        $startUtc = $start->setTimezone(new \DateTimeZone('UTC'));
        $endUtc = $end->setTimezone(new \DateTimeZone('UTC'));

        $response = $this->httpClient->request('GET', sprintf(
            'https://management.azure.com%s/providers/Microsoft.Insights/metrics',
            $this->resourceId,
        ), [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'query' => [
                'api-version' => '2023-10-01',
                'metricnames' => $metricName,
                'aggregation' => 'Total',
                'interval' => 'PT1H',
                'timespan' => sprintf(
                    '%s/%s',
                    $startUtc->format('Y-m-d\TH:i:s\Z'),
                    $endUtc->format('Y-m-d\TH:i:s\Z'),
                ),
            ],
        ]);

        $data = $response->toArray(false);
        $sum = 0;

        foreach ($data['value'] ?? [] as $metric) {
            foreach ($metric['timeseries'] ?? [] as $series) {
                foreach ($series['data'] ?? [] as $point) {
                    $value = $point['total'] ?? $point['sum'] ?? null;
                    if ($value !== null) {
                        $sum += (int) round((float) $value);
                    }
                }
            }
        }

        return $sum;
    }

    /**
     * @param array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int} $a
     * @param array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int} $b
     *
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int}
     */
    private function maxBucket(array $a, array $b): array
    {
        return [
            'total_tokens' => max($a['total_tokens'], $b['total_tokens']),
            'prompt_tokens' => max($a['prompt_tokens'], $b['prompt_tokens']),
            'completion_tokens' => max($a['completion_tokens'], $b['completion_tokens']),
            'requests' => max($a['requests'], $b['requests']),
        ];
    }
}
