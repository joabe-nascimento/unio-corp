<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BeneficiaryLegacyRedirectTest extends WebTestCase
{
    public function testLegacyCarteirinhaUrlRedirects(): void
    {
        $client = static::createClient();
        $client->request('GET', '/carterinha-digital?passo=2');

        self::assertResponseRedirects('/carteirinha-digital?passo=2', 301);
    }
}
