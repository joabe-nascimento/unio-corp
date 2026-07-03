<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Snapshot operacional da plataforma (logs, ambiente, deploy) para a conta PLATFORM_OWNER.
 */
final class PlatformOpsService
{
    public function __construct(
        private KernelInterface $kernel,
        private string $appEnv,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        $projectDir = $this->kernel->getProjectDir();
        $logDir = $projectDir . '/var/log';

        return [
            'generated_at' => (new \DateTimeImmutable())->format('c'),
            'environment' => $this->appEnv,
            'php_version' => PHP_VERSION,
            'symfony_version' => $this->resolveSymfonyVersion($projectDir),
            'project_dir' => $projectDir,
            'disk' => $this->diskUsage($projectDir),
            'deploy' => $this->readDeployHints($projectDir),
            'logs' => [
                'prod' => $this->tailLog($logDir . '/prod.log'),
                'dev' => $this->tailLog($logDir . '/dev.log'),
            ],
        ];
    }

    /**
     * @return array{path: string, exists: bool, size: int, modified: ?string, lines: list<string>}
     */
    private function tailLog(string $path, int $maxLines = 100): array
    {
        if (!is_readable($path)) {
            return [
                'path' => $path,
                'exists' => false,
                'size' => 0,
                'modified' => null,
                'lines' => [],
            ];
        }

        $size = (int) filesize($path);
        $modified = filemtime($path) !== false
            ? (new \DateTimeImmutable('@' . filemtime($path)))->format('c')
            : null;

        $lines = [];
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = (int) $file->key();
        $start = max(0, $lastLine - $maxLines);

        for ($i = $start; $i <= $lastLine; ++$i) {
            $file->seek($i);
            $line = trim((string) $file->current());
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return [
            'path' => $path,
            'exists' => true,
            'size' => $size,
            'modified' => $modified,
            'lines' => $lines,
        ];
    }

    /**
     * @return array{project_bytes: int, var_log_bytes: int|null}
     */
    private function diskUsage(string $projectDir): array
    {
        return [
            'project_bytes' => $this->directorySize($projectDir, 3),
            'var_log_bytes' => is_dir($projectDir . '/var/log')
                ? $this->directorySize($projectDir . '/var/log', 1)
                : null,
        ];
    }

    private function directorySize(string $dir, int $maxDepth, int $depth = 0): int
    {
        if ($depth > $maxDepth || !is_dir($dir)) {
            return 0;
        }

        $total = 0;
        $items = @scandir($dir);
        if ($items === false) {
            return 0;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_file($path)) {
                $total += (int) filesize($path);
            } elseif (is_dir($path)) {
                $total += $this->directorySize($path, $maxDepth, $depth + 1);
            }
        }

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    private function readDeployHints(string $projectDir): array
    {
        $hints = [
            'console_mtime' => $this->fileMeta($projectDir . '/bin/console'),
            'public_index_mtime' => $this->fileMeta($projectDir . '/public/index.php'),
            'git_head' => null,
        ];

        $headFile = $projectDir . '/.git/HEAD';
        if (is_readable($headFile)) {
            $head = trim((string) file_get_contents($headFile));
            if (str_starts_with($head, 'ref: ')) {
                $ref = substr($head, 5);
                $refPath = $projectDir . '/.git/' . $ref;
                if (is_readable($refPath)) {
                    $hints['git_head'] = trim((string) file_get_contents($refPath));
                }
            } else {
                $hints['git_head'] = $head;
            }
        }

        return $hints;
    }

    private function resolveSymfonyVersion(string $projectDir): string
    {
        $lockFile = $projectDir . '/composer.lock';
        if (!is_readable($lockFile)) {
            return 'indisponível';
        }

        try {
            $data = json_decode((string) file_get_contents($lockFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return 'indisponível';
        }

        foreach ($data['packages'] ?? [] as $package) {
            if (($package['name'] ?? '') === 'symfony/framework-bundle') {
                return ltrim((string) ($package['version'] ?? ''), 'v') ?: 'indisponível';
            }
        }

        return 'indisponível';
    }

    /**
     * @return array{exists: bool, modified: ?string}
     */
    private function fileMeta(string $path): array
    {
        if (!is_file($path)) {
            return ['exists' => false, 'modified' => null];
        }

        $mtime = filemtime($path);

        return [
            'exists' => true,
            'modified' => $mtime !== false
                ? (new \DateTimeImmutable('@' . $mtime))->format('c')
                : null,
        ];
    }
}
