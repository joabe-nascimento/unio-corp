<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PulsoApiTest extends WebTestCase
{
    public function testPulsoApiRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/pulso');
        self::assertResponseRedirects();
    }

    public function testPulsoApiReturnsSnapshotForGestor(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $client->submitForm('Entrar na plataforma', [
            'email' => 'gestor@unio.dev',
            'password' => 'unio123',
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();

        $client->request('GET', '/api/pulso');
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('pulso', $data);
        self::assertArrayHasKey('cenas', $data);
        self::assertArrayHasKey('sinais', $data);
        self::assertIsArray($data['cenas']);
    }

    public function testPulsoPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $client->submitForm('Entrar na plataforma', [
            'email' => 'gestor@unio.dev',
            'password' => 'unio123',
        ]);
        $client->followRedirect();

        $client->request('GET', '/pulso');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('pulso-root', $client->getResponse()->getContent());
    }
}
