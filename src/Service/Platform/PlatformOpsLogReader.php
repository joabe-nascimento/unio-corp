<?php

namespace App\Service\Platform;

/**
 * Leitura paginada de arquivos de log (últimas N linhas, mais recentes primeiro).
 */
final class PlatformOpsLogReader
{
    private const DEFAULT_TAIL_LINES = 5000;

    public function __construct(
        private PlatformOpsLogParser $parser,
    ) {}

    /**
     * @return array{
     *     entries: list<array<string, mixed>>,
     *     pagination: array{page: int, per_page: int, total: int},
     *     meta: array{path: string, exists: bool, size: int, modified: ?string, window_lines: int, filename: string}
     * }
     */
    public function paginate(
        string $path,
        int $page,
        int $perPage,
        int $maxTailLines = self::DEFAULT_TAIL_LINES,
        string $levelFilter = '',
        string $channelFilter = '',
    ): array {
        $meta = $this->fileMeta($path);

        if (!$meta['exists']) {
            return [
                'entries' => [],
                'pagination' => ['page' => 1, 'per_page' => $perPage, 'total' => 0],
                'meta' => $meta,
            ];
        }

        $lines = $this->readLastLines($path, $maxTailLines);
        $entries = [];

        foreach ($lines as $line) {
            $parsed = $this->parser->parseLine($line);
            if ($parsed === null) {
                continue;
            }

            if ($levelFilter !== '' && strtoupper($parsed['level']) !== strtoupper($levelFilter)) {
                continue;
            }

            if ($channelFilter !== '' && $parsed['channel'] !== $channelFilter) {
                continue;
            }

            $entries[] = $parsed;
        }

        $entries = array_reverse($entries);
        $total = count($entries);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $meta['window_lines'] = count($lines);

        return [
            'entries' => array_slice($entries, $offset, $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'meta' => $meta,
        ];
    }

    /**
     * @return list<string>
     */
    private function readLastLines(string $path, int $maxLines): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = (int) $file->key();
        $start = max(0, $lastLine - $maxLines + 1);

        $lines = [];
        for ($i = $start; $i <= $lastLine; ++$i) {
            $file->seek($i);
            $line = trim((string) $file->current());
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return array{path: string, exists: bool, size: int, modified: ?string, window_lines: int, filename: string}
     */
    private function fileMeta(string $path): array
    {
        if (!is_readable($path)) {
            return [
                'path' => $path,
                'exists' => false,
                'size' => 0,
                'modified' => null,
                'window_lines' => 0,
                'filename' => basename($path),
            ];
        }

        $mtime = filemtime($path);

        return [
            'path' => $path,
            'exists' => true,
            'size' => (int) filesize($path),
            'modified' => $mtime !== false
                ? (new \DateTimeImmutable('@' . $mtime))->format('c')
                : null,
            'window_lines' => 0,
            'filename' => basename($path),
        ];
    }
}
