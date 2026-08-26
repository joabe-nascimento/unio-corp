<?php

namespace App\Tests\Service\Juridico;

use App\Contract\LegalAiClientInterface;
use App\Service\Juridico\JurisFlowAiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Testa JurisFlowAiClient com MockHttpClient — sem chamadas de rede reais.
 *
 * Cobre:
 *  - conformidade com LegalAiClientInterface
 *  - respostas normais (2xx com JSON válido)
 *  - fallback quando o serviço retorna resposta vazia/inválida
 *  - redação de PII via regex local quando o endpoint de compliance falha
 */
final class JurisFlowAiClientTest extends TestCase
{
    private function makeClient(MockHttpClient $http, bool $enabled = true): JurisFlowAiClient
    {
        return new JurisFlowAiClient(
            httpClient: $http,
            logger: new NullLogger(),
            enabled: $enabled,
            baseUrl: 'http://jurisflow-test',
            defaultEscritorioId: 'esc-test',
        );
    }

    public function testImplementsInterface(): void
    {
        $client = $this->makeClient(new MockHttpClient());
        self::assertInstanceOf(LegalAiClientInterface::class, $client);
    }

    public function testIsAvailableReturnsTrueOnHealthy200(): void
    {
        $http   = new MockHttpClient(new MockResponse('{"status":"healthy"}', ['http_code' => 200]));
        $client = $this->makeClient($http);

        self::assertTrue($client->isAvailable());
    }

    public function testIsAvailableReturnsFalseWhenDisabled(): void
    {
        $client = $this->makeClient(new MockHttpClient(), enabled: false);

        self::assertFalse($client->isAvailable());
    }

    public function testChatReturnsReplyOnSuccess(): void
    {
        $body = json_encode(['answer' => 'Resposta da Sasha jurídica.']);
        $http = new MockHttpClient(new MockResponse($body, ['http_code' => 200]));

        $result = $this->makeClient($http)->chat('Qual o prazo para contestação?');

        self::assertNotNull($result);
        self::assertSame('Resposta da Sasha jurídica.', $result['reply']);
        self::assertSame('jurisflow', $result['source']);
    }

    public function testChatReturnsNullWhenDisabled(): void
    {
        $client = $this->makeClient(new MockHttpClient(), enabled: false);

        self::assertNull($client->chat('qualquer mensagem'));
    }

    public function testChatReturnsNullOnEmptyAnswer(): void
    {
        $body = json_encode(['answer' => '']);
        $http = new MockHttpClient(new MockResponse($body, ['http_code' => 200]));

        self::assertNull($this->makeClient($http)->chat('mensagem'));
    }

    public function testRedactPiiCallsEndpointAndReturnsRedactedText(): void
    {
        $body = json_encode(['text' => 'CPF: [CPF]']);
        $http = new MockHttpClient(new MockResponse($body, ['http_code' => 200]));

        $result = $this->makeClient($http)->redactPii('CPF: 123.456.789-09');

        self::assertSame('CPF: [CPF]', $result);
    }

    public function testRedactPiiFallsBackToRegexOnNetworkError(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        $result = $this->makeClient($http)->redactPii('Dados: 123.456.789-09');

        self::assertStringContainsString('[CPF]', $result);
        self::assertStringNotContainsString('123.456.789-09', $result);
    }

    public function testSubmitJobReturnsJobIdOnSuccess(): void
    {
        $body = json_encode(['job_id' => 'abc-123', 'status' => 'completed', 'result' => []]);
        $http = new MockHttpClient(new MockResponse($body, ['http_code' => 200]));

        $result = $this->makeClient($http)->submitJob('document.analyze', 'esc-test', ['texto' => 'lorem']);

        self::assertSame('abc-123', $result['job_id']);
        self::assertSame('completed', $result['status']);
    }

    public function testSubmitJobReturnsSkippedOnNetworkError(): void
    {
        $http = new MockHttpClient(static fn () => throw new \RuntimeException('connection refused'));

        $result = $this->makeClient($http)->submitJob('document.analyze', 'esc-test');

        self::assertSame('skipped', $result['status']);
    }

    public function testIndexarDocumentoRagReturnsTrueOnSuccess(): void
    {
        $http = new MockHttpClient(new MockResponse('{"ok":true}', ['http_code' => 200]));

        self::assertTrue(
            $this->makeClient($http)->indexarDocumentoRag('esc-test', 'processo/123', 'Petição', 'texto da peça')
        );
    }

    public function testBuscarNaRagReturnsChunksArray(): void
    {
        $chunks = [['document_id' => '1', 'document_title' => 'Peça 1', 'category' => 'peticao', 'content' => 'texto', 'score' => 0.9, 'source' => 'processo/1']];
        $body   = json_encode(['chunks' => $chunks]);
        $http   = new MockHttpClient(new MockResponse($body, ['http_code' => 200]));

        $result = $this->makeClient($http)->buscarNaRag('esc-test', 'horas extras');

        self::assertCount(1, $result);
        self::assertSame('Peça 1', $result[0]['document_title']);
    }
}
