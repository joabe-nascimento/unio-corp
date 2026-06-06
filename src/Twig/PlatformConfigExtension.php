<?php

namespace App\Twig;

use App\Entity\Empresa;
use App\Service\EmpresaBrandingService;
use App\Service\PlatformConfigService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Injeta `platform_config` e helpers de marca em todos os templates Twig.
 */
class PlatformConfigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private PlatformConfigService $config,
        private EmpresaBrandingService $empresaBranding,
    ) {}

    /** @return array<string,mixed> */
    public function getGlobals(): array
    {
        return [
            'platform_config' => $this->config->all(),
        ];
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('platform_asset_src', [$this, 'assetSrc']),
            new TwigFunction('platform_asset_custom', [$this, 'assetCustom']),
            new TwigFunction('public_asset_href', [$this, 'publicAssetHref']),
            new TwigFunction('empresa_logo_src', [$this, 'empresaLogoSrc']),
            new TwigFunction('empresa_logo_custom', [$this, 'empresaLogoCustom']),
        ];
    }

    /** Caminho público absoluto (/…) ou URL externa — sem passar pelo AssetMapper. */
    public function publicAssetHref(string $path): string
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

    /** @param 'logo'|'full'|'mark'|'favicon' $variant */
    public function assetSrc(string $variant = 'full'): string
    {
        $key = match ($variant) {
            'logo', 'main' => 'logo_url',
            'mark'           => 'logo_mark_url',
            'favicon'        => 'favicon_url',
            default          => 'logo_full_url',
        };

        return $this->config->resolveAssetUrl($key);
    }

    /** @param 'logo'|'full'|'mark'|'favicon' $variant */
    public function assetCustom(string $variant = 'full'): bool
    {
        $key = match ($variant) {
            'logo', 'main' => 'logo_url',
            'mark'           => 'logo_mark_url',
            'favicon'        => 'favicon_url',
            default          => 'logo_full_url',
        };

        return $this->config->hasCustomAsset($key);
    }

    /** Logo efetivo da empresa (cadastro ou fallback de Configurações). */
    public function empresaLogoSrc(?Empresa $empresa, string $variant = 'full'): string
    {
        return $this->empresaBranding->resolveLogoSrc($empresa, $variant);
    }

    public function empresaLogoCustom(?Empresa $empresa, string $variant = 'full'): bool
    {
        return $this->empresaBranding->hasDisplayLogo($empresa, $variant);
    }
}
