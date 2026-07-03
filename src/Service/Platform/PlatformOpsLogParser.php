<?php

namespace App\Service\Platform;

/**
 * Analisa linhas Monolog (texto Symfony ou JSON) e agrupa incidentes operacionais.
 */
final class PlatformOpsLogParser
{
    /** @var list<string> */
    private const NOISE_ROUTES = [
        'app_chat_api_call_poll',
        'app_chat_api_messages',
        'app_chat_api_typing',
    ];

    /**
     * @return array{
     *     scanned_lines: int,
     *     counts: array{errors: int, warnings: int, routes: int, integrations: int, deprecations: int, access: int, noise: int},
     *     incidents: array<string, list<array<string, mixed>>>
     * }
     */
    public function analyzeFile(string $path, int $maxBytes = 2_097_152): array
    {
        if (!is_readable($path)) {
            return $this->emptyReport();
        }

        $content = $this->readTail($path, $maxBytes);
        if ($content === '') {
            return $this->emptyReport();
        }

        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];

        return $this->analyzeLines($lines);
    }

    /**
     * @param list<string> $lines
     *
     * @return array{
     *     scanned_lines: int,
     *     counts: array{errors: int, warnings: int, routes: int, integrations: int, deprecations: int, access: int, noise: int},
     *     incidents: array<string, list<array<string, mixed>>>
     * }
     */
    public function analyzeLines(array $lines): array
    {
        $entries = [];
        foreach ($lines as $line) {
            $entry = $this->parseLine($line);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $this->buildReport($entries);
    }

    /**
     * @return array{
     *     scanned_lines: int,
     *     counts: array{errors: int, warnings: int, routes: int, integrations: int, deprecations: int, access: int, noise: int},
     *     incidents: array<string, list<array<string, mixed>>>
     * }
     */
    private function emptyReport(): array
    {
        return [
            'scanned_lines' => 0,
            'counts' => [
                'errors' => 0,
                'warnings' => 0,
                'routes' => 0,
                'integrations' => 0,
                'deprecations' => 0,
                'access' => 0,
                'noise' => 0,
            ],
            'incidents' => [
                'errors' => [],
                'warnings' => [],
                'routes' => [],
                'integrations' => [],
                'deprecations' => [],
                'access' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (str_starts_with($line, '{')) {
            return $this->parseJsonLine($line);
        }

        if (!preg_match('/^\[([^\]]+)\]\s+([^.]+)\.(\w+):\s+(.*)$/', $line, $matches)) {
            return null;
        }

        [, $at, $channel, $level, $rest] = $matches;
        $contextJson = null;
        $message = $rest;

        if (preg_match('/^(.+?)\s+(\{.*\})\s*$/', $rest, $ctxMatch)) {
            $message = $ctxMatch[1];
            $contextJson = $ctxMatch[2];
        }

        return $this->normalizeEntry($at, $channel, $level, $message, $contextJson);
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function classifyBucket(array $entry): string
    {
        return $this->classify($entry);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonLine(string $line): ?array
    {
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $at = (string) ($data['datetime'] ?? $data['timestamp'] ?? '');
        $channel = (string) ($data['channel'] ?? 'app');
        $level = strtoupper((string) ($data['level_name'] ?? $data['level'] ?? 'INFO'));
        $message = (string) ($data['message'] ?? '');
        $context = isset($data['context']) && is_array($data['context'])
            ? json_encode($data['context'], JSON_UNESCAPED_UNICODE)
            : null;

        if ($at === '' || $message === '') {
            return null;
        }

        return $this->normalizeEntry($at, $channel, $level, $message, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeEntry(
        string $at,
        string $channel,
        string $level,
        string $message,
        ?string $contextJson,
    ): array {
        $route = $this->extractRoute($message, $contextJson);
        $uri = $this->extractUri($message, $contextJson);
        $user = $this->extractUser($message, $contextJson);

        return [
            'at' => $at,
            'channel' => $channel,
            'level' => strtoupper($level),
            'message' => $message,
            'route' => $route,
            'uri' => $uri,
            'user' => $user,
            'context_preview' => $this->contextPreview($contextJson),
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return array{
     *     scanned_lines: int,
     *     counts: array{errors: int, warnings: int, routes: int, integrations: int, deprecations: int, access: int, noise: int},
     *     incidents: array<string, list<array<string, mixed>>>
     * }
     */
    private function buildReport(array $entries): array
    {
        $counts = [
            'errors' => 0,
            'warnings' => 0,
            'routes' => 0,
            'integrations' => 0,
            'deprecations' => 0,
            'access' => 0,
            'noise' => 0,
        ];

        $incidents = [
            'errors' => [],
            'warnings' => [],
            'routes' => [],
            'integrations' => [],
            'deprecations' => [],
            'access' => [],
        ];

        for ($i = count($entries) - 1; $i >= 0; --$i) {
            $entry = $entries[$i];
            $bucket = $this->classify($entry);
            if ($bucket === 'noise') {
                ++$counts['noise'];
                continue;
            }

            ++$counts[$bucket];
            $incidents[$bucket][] = $entry;
        }

        return [
            'scanned_lines' => count($entries),
            'counts' => $counts,
            'incidents' => $incidents,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function classify(array $entry): string
    {
        if ($this->isNoise($entry)) {
            return 'noise';
        }

        $level = (string) $entry['level'];
        $channel = (string) $entry['channel'];
        $message = (string) $entry['message'];

        if ($channel === 'deprecation' || ($level === 'INFO' && str_contains($message, 'User Deprecated'))) {
            return 'deprecations';
        }

        if ($this->isAccessIssue($entry)) {
            return 'access';
        }

        if ($this->isRouteIssue($entry)) {
            return 'routes';
        }

        if ($this->isIntegrationIssue($entry)) {
            return 'integrations';
        }

        if (in_array($level, ['CRITICAL', 'ERROR', 'ALERT', 'EMERGENCY'], true)) {
            return 'errors';
        }

        if ($level === 'WARNING') {
            return 'warnings';
        }

        return 'noise';
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function isNoise(array $entry): bool
    {
        $level = (string) $entry['level'];
        $channel = (string) $entry['channel'];
        $message = (string) $entry['message'];
        $route = (string) ($entry['route'] ?? '');

        if ($channel === 'security' && $level === 'DEBUG') {
            return true;
        }

        if ($channel === 'request' && $level === 'INFO' && str_contains($message, 'Matched route')) {
            if ($route !== '' && $this->isChatPollRoute($route)) {
                return true;
            }
        }

        if ($channel === 'http_client' && $level === 'INFO') {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function isAccessIssue(array $entry): bool
    {
        $message = (string) $entry['message'];
        $level = (string) $entry['level'];

        if (str_contains($message, 'Access denied') || str_contains($message, 'AccessDeniedException')) {
            return true;
        }

        if ($level === 'WARNING' && str_contains($message, '403')) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function isRouteIssue(array $entry): bool
    {
        $level = (string) $entry['level'];
        $channel = (string) $entry['channel'];
        $message = (string) $entry['message'];

        if (str_contains($message, 'Uncaught PHP Exception')) {
            return true;
        }

        if (str_contains($message, 'No route found') || str_contains($message, 'NotFoundHttpException')) {
            return true;
        }

        if ($channel === 'request' && in_array($level, ['CRITICAL', 'ERROR'], true)) {
            return true;
        }

        if (preg_match('/\b(4\d\d|5\d\d)\b/', $message) === 1
            && (str_contains($message, 'exception') || str_contains($message, 'Exception'))) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function isIntegrationIssue(array $entry): bool
    {
        $message = (string) $entry['message'];
        $channel = (string) $entry['channel'];

        if (str_contains($message, 'Mercure') || str_contains($message, 'Failed to send an update')) {
            return true;
        }

        if ($channel === 'mailer' || str_contains($message, 'Email') && str_contains($message, 'failed')) {
            return true;
        }

        if ($channel === 'http_client' && (string) $entry['level'] !== 'INFO') {
            return true;
        }

        return false;
    }

    private function isChatPollRoute(string $route): bool
    {
        foreach (self::NOISE_ROUTES as $noiseRoute) {
            if ($route === $noiseRoute || str_starts_with($route, $noiseRoute)) {
                return true;
            }
        }

        return false;
    }

    private function extractRoute(string $message, ?string $contextJson): ?string
    {
        if (preg_match('/Matched route "([^"]+)"/', $message, $matches)) {
            return $matches[1];
        }

        if ($contextJson !== null) {
            if (preg_match('/"route"\s*:\s*"([^"]+)"/', $contextJson, $matches)) {
                return $matches[1];
            }
            if (preg_match('/"_route"\s*:\s*"([^"]+)"/', $contextJson, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function extractUri(string $message, ?string $contextJson): ?string
    {
        if ($contextJson !== null && preg_match('/"request_uri"\s*:\s*"([^"]+)"/', $contextJson, $matches)) {
            return $this->truncate($matches[1], 120);
        }

        if (preg_match('/request_uri":"([^"]+)"/', $message, $matches)) {
            return $this->truncate($matches[1], 120);
        }

        return null;
    }

    private function extractUser(string $message, ?string $contextJson): ?string
    {
        if ($contextJson !== null && preg_match('/"username"\s*:\s*"([^"]+)"/', $contextJson, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function contextPreview(?string $contextJson): ?string
    {
        if ($contextJson === null || $contextJson === '') {
            return null;
        }

        return $this->truncate($contextJson, 200);
    }

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max - 1) . '…';
    }

    private function readTail(string $path, int $maxBytes): string
    {
        $size = filesize($path);
        if ($size === false || $size === 0) {
            return '';
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        $readFrom = max(0, $size - $maxBytes);
        fseek($handle, $readFrom);
        $content = (string) fread($handle, $size - $readFrom);
        fclose($handle);

        if ($readFrom > 0) {
            $newline = strpos($content, "\n");
            if ($newline !== false) {
                $content = substr($content, $newline + 1);
            }
        }

        return $content;
    }
}
