<?php

namespace App\Service;

use App\Repository\EmpresaRepository;
use App\Repository\PlatformAuditLogRepository;
use App\Repository\RhAuditLogRepository;
use App\Repository\UserRepository;
use App\Service\Platform\PlatformAuditService;
use App\Service\Platform\PlatformOpsLogParser;
use App\Service\Platform\PlatformOpsLogReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Snapshot operacional da plataforma (logs, ambiente, deploy, saúde) para a conta PLATFORM_OWNER.
 */
final class PlatformOpsService
{
    /** @var array<string, string> */
    private const LOG_FILE_MAP = [
        'log_prod' => 'prod.log',
        'log_errors' => 'platform-errors.log',
        'log_dev' => 'dev.log',
    ];

    public function __construct(
        private KernelInterface $kernel,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private EmpresaRepository $empresaRepository,
        private PlatformConfigService $platformConfig,
        private PlatformOpsLogParser $logParser,
        private PlatformOpsLogReader $logReader,
        private PlatformAuditService $platformAudit,
        private RhAuditLogRepository $rhAuditRepo,
        private PlatformAuditLogRepository $platformAuditRepo,
        private string $appEnv,
        private string $mercureUrl,
        private string $mercurePublicUrl,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildView(
        string $tab,
        int $page,
        int $perPage,
        string $levelFilter = '',
        string $auditCategory = '',
        string $auditAction = '',
        string $auditOutcome = '',
        string $auditSearch = '',
    ): array {
        $projectDir = $this->kernel->getProjectDir();
        $logDir = $projectDir . '/var/log';
        $prodLogPath = $logDir . '/prod.log';
        $logAnalysis = $this->logParser->analyzeFile($prodLogPath);

        $snapshot = [
            'generated_at' => (new \DateTimeImmutable())->format('c'),
            'environment' => $this->appEnv,
            'php_version' => PHP_VERSION,
            'symfony_version' => $this->resolveSymfonyVersion($projectDir),
            'project_dir' => $projectDir,
            'disk' => $this->diskUsage($projectDir),
            'deploy' => $this->readDeployInfo($projectDir),
            'health' => $this->runHealthChecks($projectDir, $prodLogPath),
            'platform' => $this->platformStats(),
            'reports' => $this->readReports($logDir),
            'integrations' => [
                'mercure_url' => $this->mercureUrl,
                'mercure_public_url' => $this->mercurePublicUrl,
                'mercure_configured' => $this->isMercureConfigured(),
            ],
        ];

        $pagination = ['page' => 1, 'per_page' => $perPage, 'total' => 0];
        $listItems = [];
        $logMeta = null;
        $auditSummary = null;
        $rhActivity = [];

        if ($tab === 'activity') {
            $auditPage = $this->platformAudit->paginateRows(
                $page,
                $perPage,
                $auditCategory,
                $auditAction,
                $auditOutcome,
                $auditSearch,
            );
            $listItems = $auditPage['items'];
            $pagination = $auditPage['pagination'];
            $rhActivity = array_map(
                static fn ($log) => [
                    'at' => $log->getCriadoEm()->format('c'),
                    'user' => $log->getUser()?->getNome() ?? $log->getUser()?->getEmail(),
                    'empresa' => $log->getEmpresa()->getNome(),
                    'modulo' => $log->getModulo(),
                    'acao' => $log->getAcao(),
                    'entidade' => $log->getEntidade(),
                    'entidade_id' => $log->getEntidadeId(),
                ],
                $this->rhAuditRepo->findRecentGlobal(15),
            );
        } elseif ($tab === 'reports') {
            $auditSummary = $this->platformAudit->summaryLast24h();
            $since = new \DateTimeImmutable('-24 hours');
            $auditSummary['rh_events_24h'] = $this->rhAuditRepo->countSince($since);
            $auditSummary['log_incidents_24h'] = $logAnalysis['counts'];
            $auditSummary['platform_audit_total'] = (int) $this->platformAuditRepo->count([]);
        } elseif (isset(self::LOG_FILE_MAP[$tab])) {
            $logView = $this->logReader->paginate(
                $logDir . '/' . self::LOG_FILE_MAP[$tab],
                $page,
                $perPage,
                5000,
                $levelFilter,
            );
            $listItems = $logView['entries'];
            $pagination = $logView['pagination'];
            $logMeta = $logView['meta'];
        } elseif ($this->isIncidentTab($tab)) {
            $all = $logAnalysis['incidents'][$tab] ?? [];
            $total = count($all);
            $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
            $page = max(1, min($page, $totalPages));
            $listItems = array_slice($all, ($page - 1) * $perPage, $perPage);
            $pagination = [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ];
        }

        return [
            'snapshot' => $snapshot,
            'log_analysis' => $logAnalysis,
            'list_items' => $listItems,
            'pagination' => $pagination,
            'log_meta' => $logMeta,
            'level_filter' => $levelFilter,
            'audit_summary' => $auditSummary,
            'rh_activity' => $rhActivity,
        ];
    }

    private function isIncidentTab(string $tab): bool
    {
        return in_array($tab, ['errors', 'warnings', 'routes', 'access', 'integrations', 'deprecations'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        return $this->buildView('overview', 1, 25)['snapshot'];
    }

    /**
     * @return array<string, int>
     */
    private function platformStats(): array
    {
        return [
            'users_total' => (int) $this->userRepository->count([]),
            'users_active' => (int) $this->userRepository->count(['ativo' => true]),
            'empresas_total' => (int) $this->empresaRepository->count([]),
        ];
    }

    /**
     * @return list<array{id: string, status: string, label: string, detail: string, hint: ?string}>
     */
    private function runHealthChecks(string $projectDir, string $prodLogPath): array
    {
        $checks = [];

        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1');
            $checks[] = [
                'id' => 'database',
                'status' => 'ok',
                'label' => 'Banco de dados',
                'detail' => 'Conexão respondendo',
                'hint' => null,
            ];
        } catch (\Throwable $e) {
            $checks[] = [
                'id' => 'database',
                'status' => 'error',
                'label' => 'Banco de dados',
                'detail' => $this->truncate($e->getMessage(), 160),
                'hint' => 'Verifique DATABASE_URL e credenciais no servidor.',
            ];
        }

        foreach ([
            'cache' => $projectDir . '/var/cache',
            'sessions' => $projectDir . '/var/sessions',
            'log' => $projectDir . '/var/log',
        ] as $id => $path) {
            $writable = is_dir($path) && is_writable($path);
            $checks[] = [
                'id' => $id,
                'status' => $writable ? 'ok' : 'error',
                'label' => match ($id) {
                    'cache' => 'Cache (var/cache)',
                    'sessions' => 'Sessões (var/sessions)',
                    default => 'Logs (var/log)',
                },
                'detail' => $writable ? 'Gravável' : 'Sem permissão de escrita',
                'hint' => $writable ? null : 'chmod/chown em var/ no servidor.',
            ];
        }

        $maintenance = $this->platformConfig->isMaintenanceMode();
        $checks[] = [
            'id' => 'maintenance',
            'status' => $maintenance ? 'warn' : 'ok',
            'label' => 'Modo manutenção',
            'detail' => $maintenance ? 'Ativo — usuários veem tela de manutenção' : 'Desligado',
            'hint' => $maintenance ? 'Desative em Admin → Configurações se não for intencional.' : null,
        ];

        $mercureOk = $this->isMercureConfigured();
        $checks[] = [
            'id' => 'mercure',
            'status' => $mercureOk ? 'ok' : 'warn',
            'label' => 'Mercure (tempo real)',
            'detail' => $mercureOk
                ? 'URL configurada'
                : ($this->mercureUrl === '' ? 'MERCURE_URL vazio' : 'Apontando para localhost — inválido em produção'),
            'hint' => $mercureOk ? null : 'Configure MERCURE_URL e MERCURE_JWT_SECRET no .env.local do servidor.',
        ];

        $cssFile = $projectDir . '/public/css/unio-app.css';
        $cssExists = is_file($cssFile);
        $checks[] = [
            'id' => 'assets',
            'status' => $cssExists ? 'ok' : 'error',
            'label' => 'Assets CSS',
            'detail' => $cssExists
                ? 'unio-app.css presente (' . number_format((int) filesize($cssFile), 0, ',', '.') . ' bytes)'
                : 'unio-app.css ausente',
            'hint' => $cssExists ? null : 'Rode deploy-server.sh ou confirme symlinks public_html/css.',
        ];

        if (is_file($prodLogPath)) {
            $logSize = (int) filesize($prodLogPath);
            $logMb = $logSize / 1024 / 1024;
            $checks[] = [
                'id' => 'prod_log_size',
                'status' => $logMb > 50 ? 'error' : ($logMb > 15 ? 'warn' : 'ok'),
                'label' => 'Tamanho prod.log',
                'detail' => number_format($logMb, 1, ',', '.') . ' MB',
                'hint' => $logMb > 15 ? 'Considere rotacionar ou truncar var/log/prod.log no servidor.' : null,
            ];
        }

        return $checks;
    }

    private function isMercureConfigured(): bool
    {
        if ($this->mercureUrl === '') {
            return false;
        }

        return !str_contains($this->mercureUrl, 'localhost')
            && !str_contains($this->mercureUrl, '127.0.0.1');
    }

    /**
     * @return array<string, mixed>
     */
    private function readDeployInfo(string $projectDir): array
    {
        $hints = [
            'console_mtime' => $this->fileMeta($projectDir . '/bin/console'),
            'public_index_mtime' => $this->fileMeta($projectDir . '/public/index.php'),
            'git_head' => null,
            'revision' => null,
        ];

        $revisionFile = $projectDir . '/var/deploy/revision.json';
        if (is_readable($revisionFile)) {
            try {
                /** @var array<string, mixed> $revision */
                $revision = json_decode((string) file_get_contents($revisionFile), true, 512, JSON_THROW_ON_ERROR);
                $hints['revision'] = $revision;
                if (isset($revision['commit']) && is_string($revision['commit']) && $revision['commit'] !== '') {
                    $hints['git_head'] = $revision['commit'];
                }
            } catch (\JsonException) {
                $hints['revision'] = ['raw' => trim((string) file_get_contents($revisionFile))];
            }
        }

        $headFile = $projectDir . '/.git/HEAD';
        if ($hints['git_head'] === null && is_readable($headFile)) {
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

    /**
     * @return array<string, array{path: string, exists: bool, content: string, status: string}>
     */
    private function readReports(string $logDir): array
    {
        $reports = [];

        foreach ([
            'deploy' => $logDir . '/deploy-report.txt',
            'ci_failure' => $logDir . '/ci-failure-report.txt',
        ] as $key => $path) {
            $reports[$key] = $this->readReportFile($path);
        }

        return $reports;
    }

    /**
     * @return array{path: string, exists: bool, content: string, status: string}
     */
    private function readReportFile(string $path): array
    {
        if (!is_readable($path)) {
            return [
                'path' => $path,
                'exists' => false,
                'content' => '',
                'status' => 'missing',
            ];
        }

        $content = (string) file_get_contents($path);
        $status = 'unknown';
        if (str_contains($content, 'RELATÓRIO DE SUCESSO')) {
            $status = 'success';
        } elseif (str_contains($content, 'RELATÓRIO DE FALHA')) {
            $status = 'failure';
        }

        return [
            'path' => $path,
            'exists' => true,
            'content' => $content,
            'status' => $status,
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

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max - 1) . '…';
    }
}
