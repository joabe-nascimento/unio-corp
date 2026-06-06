<?php

namespace App\Service;

use App\Entity\Empresa;

/**
 * Resolve logotipo exibido por empresa: cadastro da empresa ou fallback da plataforma (Configurações).
 */
final class EmpresaBrandingService
{
    /** Logos de seed/demo — não devem sobrepor a marca configurada em Admin → Configurações. */
    private const SEED_LOGO_PATHS = [
        'images/logos/unio-demo.svg',
        'images/logos/nexus-saude.svg',
        'images/logos/edu360.svg',
        'images/logos/logo-placeholder-full.svg',
        'images/logos/logo-placeholder-mark.svg',
    ];

    public function __construct(private PlatformConfigService $platformConfig) {}

    /** Caminho relativo (/…) ou URL absoluta; vazio se nenhum logo disponível. */
    public function resolveLogoSrc(?Empresa $empresa, string $variant = 'full'): string
    {
        $empresaLogo = trim((string) ($empresa?->getLogo() ?? ''));
        if ($empresaLogo !== '' && !$this->isSeedOrPlaceholder($empresaLogo)) {
            return $this->normalizePublicPath($empresaLogo);
        }

        $platformKey = match ($variant) {
            'mark' => 'logo_mark_url',
            'logo', 'main' => 'logo_url',
            default => 'logo_full_url',
        };

        if ($this->platformConfig->hasCustomAsset($platformKey)) {
            return $this->platformConfig->resolveAssetUrl($platformKey);
        }

        if ($platformKey !== 'logo_url' && $this->platformConfig->hasCustomAsset('logo_url')) {
            return $this->platformConfig->resolveAssetUrl('logo_url');
        }

        if ($empresaLogo !== '') {
            return $this->normalizePublicPath($empresaLogo);
        }

        return '';
    }

    public function hasDisplayLogo(?Empresa $empresa, string $variant = 'full'): bool
    {
        return $this->resolveLogoSrc($empresa, $variant) !== '';
    }

    private function isSeedOrPlaceholder(string $path): bool
    {
        $normalized = ltrim(trim($path), '/');

        foreach (self::SEED_LOGO_PATHS as $seed) {
            if (ltrim($seed, '/') === $normalized) {
                return true;
            }
        }

        foreach (PlatformConfigService::DEFAULT_ASSET_PATHS as $placeholder) {
            if (ltrim($placeholder, '/') === $normalized) {
                return true;
            }
        }

        return false;
    }

    private function normalizePublicPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return str_starts_with($path, '/') ? $path : '/' . ltrim($path, '/');
    }
}
