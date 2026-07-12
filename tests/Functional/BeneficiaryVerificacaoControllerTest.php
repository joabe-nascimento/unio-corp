<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BeneficiaryVerificacaoControllerTest extends WebTestCase
{
    public function testVerificarAceitaCodigoComHifen(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verificar/PM-24K9X7Q1');

        self::assertResponseIsSuccessful();
    }

    public function testVerificarAceitaCodigoAlfanumerico(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verificar/A7F2C91B');

        self::assertResponseIsSuccessful();
    }
}
