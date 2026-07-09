<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VitoriaToolsApiTest extends WebTestCase
{
    public function testToolsListRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vitoria/tools');
        self::assertResponseRedirects();
    }

    public function testGestorCanListAndRunTools(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $client->submitForm('Entrar na plataforma', [
            'email' => 'gestor@unio.dev',
            'password' => 'unio123',
        ]);
        $client->followRedirect();

        $client->request('GET', '/api/vitoria/tools');
        self::assertResponseIsSuccessful();
        $list = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($list['tools'] ?? []);

        $client->request(
            'POST',
            '/api/vitoria/tools/abrir_admissao',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{}',
        );
        self::assertResponseIsSuccessful();
        $run = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('abrir_admissao', $run['tool'] ?? '');
        self::assertNotEmpty($run['results'] ?? []);
    }

    public function testBuscarMembroWithQuery(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $client->submitForm('Entrar na plataforma', [
            'email' => 'gestor@unio.dev',
            'password' => 'unio123',
        ]);
        $client->followRedirect();

        $client->request(
            'POST',
            '/api/vitoria/tools/buscar_membro',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['query' => 'a'], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('buscar_membro', $data['tool'] ?? '');
        self::assertArrayHasKey('summary', $data);
    }
}
