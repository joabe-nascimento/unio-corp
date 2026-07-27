<?php

namespace App\Tests\Service\Juridico;

use App\Service\Juridico\HonorariosCalculator;
use PHPUnit\Framework\TestCase;

final class HonorariosCalculatorTest extends TestCase
{
    private HonorariosCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new HonorariosCalculator();
    }

    public function testFaixaUnicaAbaixoDoPrimeiroLimite(): void
    {
        // R$ 8.000 * 20% = 1.600 > mínimo de 1.500, então usa o cálculo progressivo.
        $resultado = $this->calculator->calcular(8_000.0);

        self::assertSame(1_600.0, $resultado['honorario_contratual_estimado']);
    }

    public function testAplicaHonorarioMinimoQuandoProgressivoFicaAbaixo(): void
    {
        // R$ 1.000 * 20% = 200, abaixo do mínimo de 1.500 => aplica o mínimo.
        $resultado = $this->calculator->calcular(1_000.0);

        self::assertSame(1_500.0, $resultado['honorario_contratual_estimado']);
    }

    public function testCalculoProgressivoPorFaixas(): void
    {
        // 10.000 @ 20% = 2.000; próximos 40.000 (até 50.000) @ 15% = 6.000. Total = 8.000.
        $resultado = $this->calculator->calcular(50_000.0);

        self::assertSame(8_000.0, $resultado['honorario_contratual_estimado']);
        self::assertCount(2, $resultado['faixas_aplicadas']);
    }

    public function testHonorarioDeExitoSomaAoContratual(): void
    {
        $resultado = $this->calculator->calcular(50_000.0, 20.0);

        self::assertSame(10_000.0, $resultado['honorario_exito']);
        self::assertSame(18_000.0, $resultado['honorario_total_estimado']);
    }

    public function testValorZeradoNaoQuebra(): void
    {
        $resultado = $this->calculator->calcular(0.0);

        self::assertSame(0.0, $resultado['honorario_contratual_estimado']);
        self::assertSame(0.0, $resultado['honorario_total_estimado']);
    }

    public function testPercentualExitoForaDaFaixaEClampado(): void
    {
        $resultado = $this->calculator->calcular(10_000.0, 150.0);

        self::assertSame(100.0, $resultado['percentual_exito']);
    }
}
