<?php

namespace App\Tests\Service\Juridico;

use App\Service\Juridico\LegalIntentDetector;
use PHPUnit\Framework\TestCase;

final class LegalIntentDetectorTest extends TestCase
{
    private LegalIntentDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new LegalIntentDetector();
    }

    public function testMensagemVaziaNaoSugereNada(): void
    {
        self::assertSame([], $this->detector->detect(''));
        self::assertSame([], $this->detector->detect('   '));
    }

    public function testMensagemNeutraNaoSugereNada(): void
    {
        self::assertSame([], $this->detector->detect('Bom dia, tudo bem?'));
    }

    public function testDetectaPrazoComPecaConhecidaSemInformarDias(): void
    {
        $acoes = $this->detector->detect('Preciso calcular o prazo da contestação a partir de hoje.');

        self::assertNotEmpty($acoes);
        self::assertSame('calcular_prazo', $acoes[0]['tool']);
        self::assertSame(15, $acoes[0]['params']['dias']);
    }

    public function testDetectaPrazoComDiasEDataExplicitos(): void
    {
        $acoes = $this->detector->detect('Calcule o prazo de 10 dias a partir de 05/03/2025, em dobro.');

        self::assertSame('calcular_prazo', $acoes[0]['tool']);
        self::assertSame(10, $acoes[0]['params']['dias']);
        self::assertSame('2025-03-05', $acoes[0]['params']['data_base']);
        self::assertTrue($acoes[0]['params']['dobro']);
    }

    public function testDetectaPrazoCorrido(): void
    {
        $acoes = $this->detector->detect('Qual o prazo de 30 dias corridos para o mandado de segurança?');

        self::assertSame('corrido', $acoes[0]['params']['tipo']);
    }

    public function testDetectaHonorariosComValorMonetario(): void
    {
        $acoes = $this->detector->detect('Calcule os honorários para uma causa de R$ 50.000,00 com 20% de êxito.');

        self::assertSame('calcular_honorarios', $acoes[0]['tool']);
        self::assertSame(50000.0, $acoes[0]['params']['valor_causa']);
        self::assertSame(20.0, $acoes[0]['params']['percentual_exito']);
    }

    public function testDetectaHonorariosComValorEmMil(): void
    {
        $acoes = $this->detector->detect('Quanto cobrar de honorários numa causa de 50 mil?');

        self::assertSame(50000.0, $acoes[0]['params']['valor_causa']);
    }

    public function testDetectaAnaliseDeCarteira(): void
    {
        $acoes = $this->detector->detect('Como está a saúde da carteira hoje?');

        self::assertSame('analisar_carteira', $acoes[0]['tool']);
    }

    public function testDetectaTarefasUrgentes(): void
    {
        $acoes = $this->detector->detect('Quais tarefas estão atrasadas?');

        self::assertSame('tarefas_urgentes', $acoes[0]['tool']);
    }

    public function testDetectaBuscaDeProcessoPorNumero(): void
    {
        $acoes = $this->detector->detect('Buscar processo 1234567-12.2024.8.26.0100 do cliente.');

        self::assertSame('buscar_processo', $acoes[0]['tool']);
        self::assertSame('1234567-12.2024.8.26.0100', $acoes[0]['params']['query']);
    }

    public function testDetectaJurisprudenciaComTribunal(): void
    {
        $acoes = $this->detector->detect('Pesquise jurisprudência sobre horas extras no STJ.');

        self::assertSame('pesquisar_jurisprudencia', $acoes[0]['tool']);
        self::assertSame('STJ', $acoes[0]['params']['tribunal']);
        self::assertStringContainsString('horas extras', $acoes[0]['params']['tema']);
    }

    public function testDetectaCriarTarefaComNumeroDeProcessoEData(): void
    {
        $acoes = $this->detector->detect('Crie uma tarefa de contestação no processo 1234567-12.2024.8.26.0100 para 05/03/2025.');

        self::assertSame('criar_tarefa', $acoes[0]['tool']);
        self::assertSame('1234567-12.2024.8.26.0100', $acoes[0]['params']['numero_processo']);
        self::assertSame('2025-03-05', $acoes[0]['params']['prazo']);
        self::assertArrayHasKey('titulo', $acoes[0]['params']);
    }

    public function testDetectaCriarTarefaTemPrioridadeSobreCalculoDePrazo(): void
    {
        $acoes = $this->detector->detect('Crie uma tarefa de audiência para amanhã.');

        self::assertSame('criar_tarefa', $acoes[0]['tool']);
    }

    public function testDetectaRegistrarPrazoComTipoEData(): void
    {
        $acoes = $this->detector->detect('Registrar prazo de audiência de conciliação para 17/08/2026.');

        self::assertSame('registrar_prazo', $acoes[0]['tool']);
        self::assertSame('2026-08-17', $acoes[0]['params']['data_limite']);
        self::assertArrayHasKey('tipo', $acoes[0]['params']);
    }

    public function testDetectaNumeroDeProcessoMesmoSemPalavraGatilho(): void
    {
        $acoes = $this->detector->detect('E o processo 1234567-89.2024.8.26.0100, como está a situação dele?');

        self::assertSame('buscar_processo', $acoes[0]['tool']);
        self::assertSame('1234567-89.2024.8.26.0100', $acoes[0]['params']['query']);
    }

    public function testNumeroDeProcessoNaoSobrepoeIntencaoJaDetectada(): void
    {
        $acoes = $this->detector->detect('Registrar prazo de audiência para 17/08/2026 no processo 1234567-89.2024.8.26.0100.');

        self::assertSame('registrar_prazo', $acoes[0]['tool']);
    }

    public function testLimitaNoMaximoDeDuasSugestoes(): void
    {
        $acoes = $this->detector->detect(
            'Calcule o prazo da contestação, os honorários de R$ 10.000, ' .
            'analise a carteira e veja as tarefas atrasadas.',
        );

        self::assertLessThanOrEqual(2, \count($acoes));
    }
}
