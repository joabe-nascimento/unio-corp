<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WalletPassControllerTest extends WebTestCase
{
    public function testInvalidWalletTokenReturnsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/wallet/apple/carteirinha/token-invalido.pkpass');

        self::assertResponseStatusCodeSame(404);
    }
}
