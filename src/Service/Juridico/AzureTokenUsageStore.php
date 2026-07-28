<?php

declare(strict_types=1);

namespace App\Service\Juridico;

/**
 * Persiste totais sincronizados da Azure OpenAI (fonte autoritativa do histórico).
 */
final class AzureTokenUsageStore
{
    private string $filePath;

    public function __construct(string $projectDir)
    {
        $this->filePath = $projectDir . '/var/data/azure_openai_usage.json';
    }

    /**
     * @return array{
     *     synced_at: ?string,
     *     provider: string,
     *     model: string,
     *     today: array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int},
     *     month: array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int},
     *     lifetime: array{prompt_tokens: int, completion_tokens: int, total_tokens: int, requests: int}
     * }|null
     */
    public function load(): ?array
    {
        if (!is_file($this->filePath)) {
            return null;
        }

        $raw = file_get_contents($this->filePath);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!\is_array($data)) {
            return null;
        }

        return [
            'synced_at' => isset($data['synced_at']) ? (string) $data['synced_at'] : null,
            'provider' => (string) ($data['provider'] ?? 'azure'),
            'model' => (string) ($data['model'] ?? ''),
            'today' => $this->normalizeBucket($data['today'] ?? []),
            'month' => $this->normalizeBucket($data['month'] ?? []),
            'lifetime' => $this->normalizeBucket($data['lifetime'] ?? []),
        ];
    }

    /**
     * @param array{
     *     today: array<string, int>,
     *     month: array<string, int>,
     *     lifetime: array<string, int>
     * } $summary
     */
    public function save(array $summary, string $model = ''): void
    {
        $dir = \dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $payload = [
            'synced_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            'provider' => 'azure',
            'model' => $model,
            'today' => $this->normalizeBucket($summary['today'] ?? []),
            'month' => $this->normalizeBucket($summary['month'] ?? []),
            'lifetime' => $this->normalizeBucket($summary['lifetime'] ?? []),
        ];

        file_put_contents(
            $this->filePath,
            json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
            \LOCK_EX,
        );
    }

    /** @param array<string, mixed> $bucket */
    private function normalizeBucket(array $bucket): array
    {
        $total = (int) ($bucket['total_tokens'] ?? 0);
        $prompt = (int) ($bucket['prompt_tokens'] ?? 0);
        $completion = (int) ($bucket['completion_tokens'] ?? 0);

        if ($total > 0 && $prompt === 0 && $completion === 0) {
            $prompt = (int) round($total * 0.5);
            $completion = $total - $prompt;
        }

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'requests' => (int) ($bucket['requests'] ?? 0),
        ];
    }
}
