<?php

namespace App\Tests\Service\Juridico;

use App\Contract\LegalAiClientInterface;
use App\Service\Juridico\NullLegalAiClient;
use PHPUnit\Framework\TestCase;

/**
 * Testa o NullLegalAiClient — implementação stub que nunca chama a rede.
 *
 * Valida também que ele implementa corretamente a LegalAiClientInterface,
 * garantindo que qualquer consumidor que dependa da interface possa receber
 * o null-object sem quebrar contratos.
 */
final class NullLegalAiClientTest extends TestCase
{
    private NullLegalAiClient $client;

    protected function setUp(): void
    {
        $this->client = new NullLegalAiClient();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(LegalAiClientInterface::class, $this->client);
    }

    public function testIsAvailableReturnsFalse(): void
    {
        self::assertFalse($this->client->isAvailable());
    }

    public function testChatReturnsNull(): void
    {
        self::assertNull($this->client->chat('alguma mensagem'));
    }

    public function testPesquisarJurisprudenciaReturnsNull(): void
    {
        self::assertNull($this->client->pesquisarJurisprudencia('horas extras'));
    }

    public function testResumirDocumentoReturnsNull(): void
    {
        self::assertNull($this->client->resumirDocumento('texto da petição'));
    }

    public function testAnalisarContratoReturnsNull(): void
    {
        self::assertNull($this->client->analisarContrato('cláusula abusiva...'));
    }

    public function testGerarMinutaReturnsNull(): void
    {
        self::assertNull($this->client->gerarMinuta('petição', 'pedido de indenização'));
    }

    public function testAnalisarSentencaReturnsNull(): void
    {
        self::assertNull($this->client->analisarSentenca('sentença condenatória...'));
    }

    public function testCompararDocumentosReturnsNull(): void
    {
        self::assertNull($this->client->compararDocumentos('doc A', 'doc B'));
    }

    public function testIndexarDocumentoRagReturnsFalse(): void
    {
        self::assertFalse($this->client->indexarDocumentoRag('esc1', 'src', 'título', 'conteúdo'));
    }

    public function testBuscarNaRagReturnsEmptyArray(): void
    {
        self::assertSame([], $this->client->buscarNaRag('esc1', 'busca'));
    }

    public function testStatusReturnsNull(): void
    {
        self::assertNull($this->client->status());
    }

    public function testSubmitJobReturnsDisabled(): void
    {
        $result = $this->client->submitJob('document.analyze', 'esc1');
        self::assertSame('disabled', $result['status']);
    }

    public function testExtractMetadataReturnsEmptyArray(): void
    {
        self::assertSame([], $this->client->extractMetadata('texto qualquer'));
    }

    public function testRedactPiiMasksCpf(): void
    {
        $input    = 'CPF do cliente: 123.456.789-09 e e-mail teste@empresa.com';
        $redacted = $this->client->redactPii($input);

        self::assertStringContainsString('[CPF]', $redacted);
        self::assertStringNotContainsString('123.456.789-09', $redacted);
    }

    public function testRedactPiiPassesThroughTextWithoutPii(): void
    {
        $input = 'Petição sobre horas extras trabalhadas.';
        self::assertSame($input, $this->client->redactPii($input));
    }
}
