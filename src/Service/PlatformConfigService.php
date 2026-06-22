<?php

namespace App\Service;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Lê e persiste a configuração administrativa da plataforma (var/admin_config.json).
 * Fornece defaults para todas as chaves esperadas.
 */
class PlatformConfigService
{
    private string $projectDir;

    private string $cfgFile;

    /** @var array<string,mixed>|null */
    private ?array $cache = null;

    private ?int $cacheMtime = null;

    /** @var list<string> */
    private const ASSET_URL_KEYS = ['logo_url', 'logo_mark_url', 'logo_full_url', 'favicon_url'];

    /** Caminho público do logotipo padrão (fonte: assets/logotipo.svg). */
    public const DEFAULT_LOGO_ASSET = '/images/logos/logotipo.svg';

    /** @var array<string,string> Placeholders exibidos quando nenhum asset foi configurado. */
    public const DEFAULT_ASSET_PATHS = [
        'logo_url'      => self::DEFAULT_LOGO_ASSET,
        'logo_full_url' => self::DEFAULT_LOGO_ASSET,
        'logo_mark_url' => '/images/logos/logo-placeholder-mark.svg',
        'favicon_url'   => '/images/logos/favicon-placeholder.svg',
    ];

    private const MIN_ASSET_BYTES = 200;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
        $this->cfgFile    = $projectDir . '/var/admin_config.json';
    }

    // ── Defaults ──────────────────────────────────────────────────────

    private const DEFAULTS = [
        'plataforma_nome'     => 'Unio',
        'plataforma_tagline'  => 'Plataforma de Gestão de Pessoas',
        'logo_url'            => '',
        'logo_mark_url'       => '',
        'logo_full_url'       => '',
        'favicon_url'         => '',
        'cor_primaria'        => '#3D5AE8',
        'tema'                => 'dark',
        'suporte_email'       => '',
        'suporte_telefone'    => '',
        'website'             => '',
        'rodape_texto'        => '',
        'manutencao'          => false,
        'msg_manutencao'      => 'Estamos realizando melhorias. Voltamos em breve!',
        'senha_min'           => 8,
        'sessao_timeout'      => 120,
        'senha_maiuscula'     => false,
        'senha_numero'        => false,
        'registro_publico'    => true,
    ];

    // ── API pública ────────────────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        $mtime = is_file($this->cfgFile) ? (int) filemtime($this->cfgFile) : 0;
        if ($this->cache !== null && $this->cacheMtime === $mtime) {
            return $this->cache;
        }

        $this->cacheMtime = $mtime;
        $this->cache      = $this->sanitizeAssetUrls(array_merge(self::DEFAULTS, $this->loadRaw()));

        return $this->cache;
    }

    public function isMaintenanceMode(): bool
    {
        return (bool) $this->get('manutencao');
    }

    public function isRegistroPublico(): bool
    {
        return (bool) $this->get('registro_publico');
    }

    public function getSenhaMin(): int
    {
        return max(6, (int) $this->get('senha_min', 8));
    }

    public function requiresSenhaMaiuscula(): bool
    {
        return (bool) $this->get('senha_maiuscula');
    }

    public function requiresSenhaNumero(): bool
    {
        return (bool) $this->get('senha_numero');
    }

    /** Tempo máximo de inatividade antes do logout (segundos). */
    public function getSessaoTimeoutSeconds(): int
    {
        $minutes = max(15, min(1440, (int) $this->get('sessao_timeout', 120)));

        return $minutes * 60;
    }

    /** @return list<Constraint> Constraints para formulários de senha. */
    public function getPasswordConstraints(): array
    {
        $constraints = [
            new NotBlank(message: 'Informe uma senha.'),
            new Length(
                min: $this->getSenhaMin(),
                minMessage: 'A senha deve ter no mínimo {{ limit }} caracteres.',
                max: 72,
            ),
            new Regex(
                pattern: '/[A-Za-z]/',
                message: 'A senha deve conter ao menos uma letra.',
            ),
        ];

        if ($this->requiresSenhaMaiuscula()) {
            $constraints[] = new Regex(
                pattern: '/[A-Z]/',
                message: 'A senha deve conter ao menos uma letra maiúscula.',
            );
        }

        if ($this->requiresSenhaNumero()) {
            $constraints[] = new Regex(
                pattern: '/\d/',
                message: 'A senha deve conter ao menos um número.',
            );
        }

        return $constraints;
    }

    /** Retorna mensagem de erro ou null se a senha atende à política. */
    public function validatePassword(string $password): ?string
    {
        if (strlen($password) < $this->getSenhaMin()) {
            return sprintf('A senha deve ter ao menos %d caracteres.', $this->getSenhaMin());
        }

        if (!preg_match('/[A-Za-z]/', $password)) {
            return 'A senha deve conter ao menos uma letra.';
        }

        if ($this->requiresSenhaMaiuscula() && !preg_match('/[A-Z]/', $password)) {
            return 'A senha deve conter ao menos uma letra maiúscula.';
        }

        if ($this->requiresSenhaNumero() && !preg_match('/\d/', $password)) {
            return 'A senha deve conter ao menos um número.';
        }

        return null;
    }

    public function hasSupportContact(): bool
    {
        return trim((string) $this->get('suporte_email')) !== ''
            || trim((string) $this->get('suporte_telefone')) !== ''
            || trim((string) $this->get('website')) !== '';
    }

    public function getAccentRgbCsv(): string
    {
        return self::hexToRgbCsv((string) $this->get('cor_primaria', '#4F7FFF'));
    }

    public static function hexToRgbCsv(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if ($hex === '') {
            return '79, 127, 255';
        }
        if (\strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return '79, 127, 255';
        }

        return sprintf(
            '%d, %d, %d',
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    /** Caminho relativo (/…) ou URL absoluta do asset; usa placeholder quando vazio. */
    public function resolveAssetUrl(string $key): string
    {
        $custom = trim((string) $this->get($key, ''));

        return $custom !== '' ? $custom : (self::DEFAULT_ASSET_PATHS[$key] ?? '');
    }

    public function hasCustomAsset(string $key): bool
    {
        return trim((string) $this->get($key, '')) !== '';
    }

    /** @param array<string,mixed> $config */
    public function save(array $config): void
    {
        $merged = $this->sanitizeAssetUrls(array_merge(self::DEFAULTS, $this->loadRaw(), $config));

        @mkdir(dirname($this->cfgFile), 0777, true);
        file_put_contents(
            $this->cfgFile,
            json_encode($merged, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)
        );

        $this->cache      = $merged;
        $this->cacheMtime = is_file($this->cfgFile) ? (int) filemtime($this->cfgFile) : 0;
    }

    /** @return array<string,mixed> */
    private function loadRaw(): array
    {
        if (!is_file($this->cfgFile)) {
            return [];
        }

        return json_decode(file_get_contents($this->cfgFile) ?: '{}', true) ?? [];
    }

    /** @param array<string,mixed> $config */
    private function sanitizeAssetUrls(array $config): array
    {
        foreach (self::ASSET_URL_KEYS as $key) {
            $config[$key] = $this->sanitizeAssetUrl((string) ($config[$key] ?? ''));
        }

        return $config;
    }

    private function sanitizeAssetUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (!str_starts_with($url, '/')) {
            $url = '/' . ltrim($url, '/');
        }

        foreach (self::DEFAULT_ASSET_PATHS as $defaultPath) {
            if ($url === $defaultPath) {
                return $url;
            }
        }

        $path = $this->projectDir . '/public' . $url;
        if (!is_file($path)) {
            return '';
        }

        $minBytes = str_ends_with(strtolower($path), '.svg') ? 80 : self::MIN_ASSET_BYTES;
        if (filesize($path) < $minBytes) {
            return '';
        }

        return $url;
    }
}
