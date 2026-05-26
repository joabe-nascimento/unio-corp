<?php

namespace App\Tests\Service;

use App\Service\PlatformConfigService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PlatformConfigServiceTest extends KernelTestCase
{
    private PlatformConfigService $config;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->config = static::getContainer()->get(PlatformConfigService::class);
        $this->config->save([
            'senha_min'       => 8,
            'senha_maiuscula' => true,
            'senha_numero'    => true,
            'sessao_timeout'  => 30,
        ]);
    }

    protected function tearDown(): void
    {
        $this->config->save([
            'senha_min'       => 8,
            'senha_maiuscula' => false,
            'senha_numero'    => false,
            'sessao_timeout'  => 120,
            'manutencao'      => false,
        ]);
        parent::tearDown();
    }

    public function testValidatePasswordRejectsWeakPasswords(): void
    {
        self::assertNotNull($this->config->validatePassword('abc'));
        self::assertNotNull($this->config->validatePassword('abcdefgh'));
        self::assertNotNull($this->config->validatePassword('abcdefg1'));
        self::assertNull($this->config->validatePassword('Abcdefg1'));
    }

    public function testSessaoTimeoutSeconds(): void
    {
        self::assertSame(30 * 60, $this->config->getSessaoTimeoutSeconds());
    }

    public function testPasswordConstraintsCount(): void
    {
        self::assertCount(5, $this->config->getPasswordConstraints());
    }
}
