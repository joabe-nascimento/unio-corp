<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Service\Clinic\ClinicPlatformScope;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ClinicScopeFunctionalTest extends WebTestCase
{
    public function testClinicRoutesBlockedWhenStudioProfile(): void
    {
        $client = static::createClient();
        if (static::getContainer()->get(ClinicPlatformScope::class)->isActive()) {
            self::markTestSkipped('Ambiente de teste está no perfil clínica.');
        }

        $client->request('GET', '/paciente');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/carteirinha-digital');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/modulo/carteirinha-digital');
        self::assertResponseStatusCodeSame(404);
    }

    public function testClinicHubAvailableWhenClinicProfile(): void
    {
        $client = static::createClient();
        if (!static::getContainer()->get(ClinicPlatformScope::class)->isActive()) {
            self::markTestSkipped('Ambiente de teste está no perfil studio.');
        }

        $client->request('GET', '/paciente');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Área do paciente');
    }
}
