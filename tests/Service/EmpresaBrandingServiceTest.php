<?php

namespace App\Tests\Service;

use App\Entity\Empresa;
use App\Service\EmpresaBrandingService;
use App\Service\PlatformConfigService;
use PHPUnit\Framework\TestCase;

final class EmpresaBrandingServiceTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = dirname(__DIR__, 2);
    }

    public function testUsesPlatformLogoWhenEmpresaHasSeedLogo(): void
    {
        $platform = $this->createMock(PlatformConfigService::class);
        $platform->method('hasCustomAsset')->willReturnCallback(
            static fn (string $key): bool => $key === 'logo_full_url'
        );
        $platform->method('resolveAssetUrl')->willReturnCallback(
            static fn (string $key): string => $key === 'logo_full_url'
                ? '/uploads/config/logo-custom.svg'
                : ''
        );

        $service = new EmpresaBrandingService($platform);
        $empresa = (new Empresa())->setNome('Teste')->setLogo('images/logos/unio-demo.svg');

        self::assertSame('/uploads/config/logo-custom.svg', $service->resolveLogoSrc($empresa));
    }

    public function testUsesEmpresaLogoWhenCustom(): void
    {
        $platform = $this->createMock(PlatformConfigService::class);
        $service = new EmpresaBrandingService($platform);
        $empresa = (new Empresa())->setNome('Acme')->setLogo('/uploads/empresas/acme.png');

        self::assertSame('/uploads/empresas/acme.png', $service->resolveLogoSrc($empresa));
    }

    public function testReturnsEmptyWhenNoLogoAvailable(): void
    {
        $platform = $this->createMock(PlatformConfigService::class);
        $platform->method('hasCustomAsset')->willReturn(false);
        $service = new EmpresaBrandingService($platform);
        $empresa = (new Empresa())->setNome('Vazia');

        self::assertSame('', $service->resolveLogoSrc($empresa));
    }
}
