<?php

namespace App\Service\Platform;

use App\Entity\PlatformAuditLog;
use App\Repository\EmpresaRepository;
use App\Repository\PlatformAuditLogRepository;
use App\Service\PlatformConfigService;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Painel exclusivo PLATFORM_OWNER — ambientes, backups, governança e segurança.
 */
final class PlatformCommandCenterService
{
    private const GITHUB_REPO = 'joabe-nascimento/unio-corp';

    /** @var array<string, array{label: string, url: string}> */
    private const KNOWN_HOMOLOGS = [
        'product/rh' => [
            'label' => 'Homolog RH',
            'url' => 'https://rh.uniowork.com.br',
        ],
    ];

    public function __construct(
        private KernelInterface $kernel,
        private PlatformConfigService $platformConfig,
        private PlatformAuditLogRepository $auditRepo,
        private EmpresaRepository $empresaRepo,
        private string $defaultUri,
    ) {}

    public function failedLoginsLast24h(): int
    {
        return $this->auditRepo->countAuthEventsSince(
            new \DateTimeImmutable('-24 hours'),
            PlatformAuditLog::ACTION_LOGIN_FAILED,
        );
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $since24h = new \DateTimeImmutable('-24 hours');
        $projectDir = $this->kernel->getProjectDir();

        return [
            'generated_at' => (new \DateTimeImmutable())->format('c'),
            'environments' => $this->buildEnvironments($projectDir),
            'backups' => $this->listDatabaseBackups($projectDir),
            'governance' => $this->buildGovernance(),
            'security' => $this->buildSecurity($since24h),
            'shortcuts' => $this->buildShortcuts(),
            'deploy_report' => $this->readDeployReportStatus($projectDir),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildEnvironments(string $projectDir): array
    {
        $items = [];
        $productionUrl = rtrim($this->platformConfig->get('website', '') ?: $this->defaultUri, '/');

        $items[] = $this->environmentRow(
            id: 'production',
            label: 'Produção',
            url: $productionUrl,
            branch: 'production',
            current: true,
        );

        foreach ($this->readDeployBranches($projectDir) as $branch) {
            if ($branch === 'production') {
                continue;
            }
            $known = self::KNOWN_HOMOLOGS[$branch] ?? null;
            $slug = str_replace('product/', '', $branch);
            $items[] = $this->environmentRow(
                id: $branch,
                label: $known['label'] ?? ('Homolog ' . ucfirst(str_replace('-', ' ', $slug))),
                url: $known['url'] ?? ('https://' . $slug . '.uniowork.com.br'),
                branch: $branch,
                current: false,
            );
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentRow(
        string $id,
        string $label,
        string $url,
        string $branch,
        bool $current,
    ): array {
        $probe = $this->probeHttp($url);

        return [
            'id' => $id,
            'label' => $label,
            'url' => $url,
            'branch' => $branch,
            'current' => $current,
            'http_status' => $probe['status'],
            'http_ok' => $probe['ok'],
            'http_ms' => $probe['ms'],
            'http_error' => $probe['error'],
        ];
    }

    /**
     * @return array{status: int|null, ok: bool, ms: int|null, error: ?string}
     */
    private function probeHttp(string $url): array
    {
        $start = hrtime(true);
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 4,
                'ignore_errors' => true,
                'header' => "User-Agent: Unio-PlatformOps/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $headers = @get_headers($url, true, $context);
        $ms = (int) round((hrtime(true) - $start) / 1_000_000);

        if ($headers === false) {
            return [
                'status' => null,
                'ok' => false,
                'ms' => $ms,
                'error' => 'Sem resposta HTTP',
            ];
        }

        $statusLine = is_array($headers[0] ?? null) ? ($headers[0][0] ?? '') : (string) ($headers[0] ?? '');
        preg_match('/\d{3}/', $statusLine, $matches);
        $status = isset($matches[0]) ? (int) $matches[0] : null;

        return [
            'status' => $status,
            'ok' => $status !== null && $status >= 200 && $status < 400,
            'ms' => $ms,
            'error' => null,
        ];
    }

    /**
     * @return list<string>
     */
    private function readDeployBranches(string $projectDir): array
    {
        $file = $projectDir . '/config/deploy-branches.txt';
        if (!is_readable($file)) {
            return array_keys(self::KNOWN_HOMOLOGS);
        }

        $branches = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $branches[] = $line;
        }

        return $branches !== [] ? $branches : array_keys(self::KNOWN_HOMOLOGS);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listDatabaseBackups(string $projectDir): array
    {
        $dir = $projectDir . '/var/backups/db';
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.sql.gz') ?: [];
        usort($files, static fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
        $files = array_slice($files, 0, 8);

        $items = [];
        foreach ($files as $path) {
            $mtime = filemtime($path);
            $items[] = [
                'name' => basename($path),
                'path' => $path,
                'size_bytes' => (int) filesize($path),
                'modified_at' => $mtime !== false
                    ? (new \DateTimeImmutable('@' . $mtime))->format('c')
                    : null,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGovernance(): array
    {
        $empresasTotal = (int) $this->empresaRepo->count([]);
        $empresasAtivas = (int) $this->empresaRepo->count(['ativo' => true]);

        return [
            'plataforma_nome' => $this->platformConfig->get('plataforma_nome', 'Unio'),
            'manutencao' => $this->platformConfig->isMaintenanceMode(),
            'registro_publico' => (bool) $this->platformConfig->get('registro_publico', true),
            'encarregado_email' => (string) $this->platformConfig->get('encarregado_dados_email', ''),
            'operadora_cnpj' => (string) $this->platformConfig->get('operadora_cnpj', ''),
            'empresas_total' => $empresasTotal,
            'empresas_ativas' => $empresasAtivas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSecurity(\DateTimeImmutable $since): array
    {
        $failedLogins = $this->auditRepo->countAuthEventsSince(
            $since,
            PlatformAuditLog::ACTION_LOGIN_FAILED,
        );
        $successfulLogins = $this->auditRepo->countAuthEventsSince(
            $since,
            PlatformAuditLog::ACTION_LOGIN,
        );

        return [
            'failed_logins_24h' => $failedLogins,
            'successful_logins_24h' => $successfulLogins,
            'recent_auth' => array_map(
                static fn (PlatformAuditLog $log) => $log->toRow(),
                $this->auditRepo->findRecentAuth(8),
            ),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function buildShortcuts(): array
    {
        $repo = self::GITHUB_REPO;

        return [
            [
                'label' => 'GitHub Actions',
                'description' => 'Pipelines e deploys',
                'url' => 'https://github.com/' . $repo . '/actions',
                'icon' => 'fa-github',
                'external' => '1',
            ],
            [
                'label' => 'Repositório',
                'description' => $repo,
                'url' => 'https://github.com/' . $repo,
                'icon' => 'fa-code-branch',
                'external' => '1',
            ],
            [
                'label' => 'Canal LGPD',
                'description' => 'Formulário público',
                'route' => 'app_legal_lgpd',
                'icon' => 'fa-user-shield',
            ],
            [
                'label' => 'Configurações',
                'description' => 'Marca, LGPD, manutenção',
                'route' => 'app_admin_configuracoes',
                'icon' => 'fa-sliders',
            ],
            [
                'label' => 'Empresas',
                'description' => 'Workspaces cadastrados',
                'route' => 'app_admin_empresas',
                'icon' => 'fa-building',
            ],
        ];
    }

    /**
     * @return array{exists: bool, status: string, excerpt: string}
     */
    private function readDeployReportStatus(string $projectDir): array
    {
        $path = $projectDir . '/var/log/deploy-report.txt';
        if (!is_readable($path)) {
            return ['exists' => false, 'status' => 'missing', 'excerpt' => ''];
        }

        $content = (string) file_get_contents($path);
        $status = 'unknown';
        if (str_contains($content, 'RELATÓRIO DE SUCESSO')) {
            $status = 'success';
        } elseif (str_contains($content, 'RELATÓRIO DE FALHA')) {
            $status = 'failure';
        }

        $lines = array_slice(explode("\n", trim($content)), 0, 6);

        return [
            'exists' => true,
            'status' => $status,
            'excerpt' => implode("\n", $lines),
        ];
    }
}
