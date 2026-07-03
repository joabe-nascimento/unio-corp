<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VitoriaChatApiTest extends WebTestCase
{
    public function testAuthenticatedChatProxyReturnsVitoriaReply(): void
    {
        if (!getenv('VITORIA_AI_URL') && !($_ENV['VITORIA_AI_URL'] ?? '')) {
            self::markTestSkipped('VITORIA_AI_URL não configurado.');
        }

        $client = static::createClient();
        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $client->submitForm('Entrar na plataforma', [
            'email' => 'tenant@unio.dev',
            'password' => 'unio123',
        ]);
        self::assertResponseRedirects();

        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->request(
            'POST',
            '/api/vitoria/chat',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'message' => 'Como priorizo alertas P1 no pós-operatório?',
                'history' => [],
                'context' => ['hub' => 'hub_pos_operatorio'],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($data['reply'] ?? '');
        self::assertStringNotContainsString('indisponível', strtolower($data['reply']));
        self::assertContains($data['source'] ?? '', ['fallback', 'llm', 'guardrail']);
    }

    public function testVitoriaStatusReportsOnlineWhenPythonRunning(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $client->submitForm('Entrar na plataforma', [
            'email' => 'tenant@unio.dev',
            'password' => 'unio123',
        ]);
        $client->followRedirect();

        $client->request('GET', '/api/vitoria/status');
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($data['enabled'] ?? false);
        self::assertTrue($data['online'] ?? false, 'Vitória Python deve estar online na porta 8100');
    }
}
