<?php

namespace App\Tests\Service\PosOperatorio\Whatsapp;

use App\Entity\Empresa;
use App\Service\PosOperatorio\Whatsapp\MetaCloudWhatsappSender;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MetaCloudWhatsappSenderTest extends TestCase
{
    public function testSendSuccessReturnsMessageId(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/v21.0/123456/messages', $url);
            $auth = $options['headers']['Authorization'] ?? null;
            if ($auth === null && isset($options['normalized_headers']['authorization'][0])) {
                $auth = $options['normalized_headers']['authorization'][0];
            }
            self::assertNotNull($auth);
            self::assertStringContainsString('test-token', (string) $auth);

            return new MockResponse(json_encode([
                'messages' => [['id' => 'wamid.ABC123']],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $sender = new MetaCloudWhatsappSender($client, 'test-token', '123456', 'v21.0', new NullLogger());
        $empresa = (new Empresa())->setNome('Clinica')->setCnpj('00.000.000/0001-00');

        $result = $sender->send($empresa, '5511999990000', 'Olá teste');

        self::assertTrue($result->sent);
        self::assertSame('sent', $result->status);
        self::assertSame('wamid.ABC123', $result->providerMessageId);
        self::assertTrue($sender->isLive());
    }

    public function testSendHttpErrorReturnsFailed(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'error' => ['message' => 'Invalid OAuth access token'],
            ], JSON_THROW_ON_ERROR), ['http_code' => 401]),
        ]);

        $sender = new MetaCloudWhatsappSender($client, 'bad-token', '123456', 'v21.0', new NullLogger());
        $empresa = (new Empresa())->setNome('Clinica')->setCnpj('00.000.000/0001-00');

        $result = $sender->send($empresa, '5511999990000', 'Olá teste');

        self::assertFalse($result->sent);
        self::assertSame('failed', $result->status);
        self::assertStringContainsString('Invalid OAuth', (string) $result->error);
    }

    public function testSendUsesTemplateWhenConfiguredThenSucceeds(): void
    {
        $seenTypes = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seenTypes): MockResponse {
            $body = json_decode((string) ($options['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            $seenTypes[] = $body['type'] ?? null;
            self::assertSame('template', $body['type']);
            self::assertSame('confirmacao_agenda', $body['template']['name']);
            self::assertSame('pt_BR', $body['template']['language']['code']);
            self::assertCount(2, $body['template']['components'][0]['parameters']);

            return new MockResponse(json_encode([
                'messages' => [['id' => 'wamid.TPL']],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $sender = new MetaCloudWhatsappSender(
            $client,
            'test-token',
            '123456',
            'v21.0',
            new NullLogger(),
            'confirmacao_agenda',
            'questionario_pendente',
            'pt_BR',
        );
        $empresa = (new Empresa())->setNome('Clinica')->setCnpj('00.000.000/0001-00');

        $result = $sender->send($empresa, '5511999990000', 'texto fallback', [
            'event' => 'agenda_confirmacao',
            'template_params' => ['João', 'Consulta'],
        ]);

        self::assertTrue($result->sent);
        self::assertSame(['template'], $seenTypes);
        self::assertSame('wamid.TPL', $result->providerMessageId);
    }

    public function testTemplateFailureFallsBackToText(): void
    {
        $seenTypes = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seenTypes): MockResponse {
            $body = json_decode((string) ($options['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            $seenTypes[] = $body['type'] ?? null;
            if (($body['type'] ?? '') === 'template') {
                return new MockResponse(json_encode([
                    'error' => ['message' => 'Template name does not exist'],
                ], JSON_THROW_ON_ERROR), ['http_code' => 400]);
            }

            return new MockResponse(json_encode([
                'messages' => [['id' => 'wamid.TXT']],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $sender = new MetaCloudWhatsappSender(
            $client,
            'test-token',
            '123456',
            'v21.0',
            new NullLogger(),
            'confirmacao_agenda',
            '',
            'pt_BR',
        );
        $empresa = (new Empresa())->setNome('Clinica')->setCnpj('00.000.000/0001-00');

        $result = $sender->send($empresa, '5511999990000', 'texto fallback', [
            'event' => 'agenda_confirmacao',
            'template_params' => ['João'],
        ]);

        self::assertTrue($result->sent);
        self::assertSame(['template', 'text'], $seenTypes);
        self::assertSame('wamid.TXT', $result->providerMessageId);
    }
}
