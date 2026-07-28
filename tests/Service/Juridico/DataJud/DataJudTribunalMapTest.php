<?php

namespace App\Tests\Service\Juridico\DataJud;

use App\Service\Juridico\DataJud\DataJudTribunalMap;
use PHPUnit\Framework\TestCase;

final class DataJudTribunalMapTest extends TestCase
{
    public function testResolveProcessoDoTjsp(): void
    {
        $resultado = DataJudTribunalMap::resolver('0001234-56.2026.8.25.0100');

        self::assertNotNull($resultado);
        self::assertSame('tjsp', $resultado['alias']);
        self::assertSame('0001234-56.2026.8.25.0100', $resultado['numero']);
    }

    public function testResolveProcessoDoTrf3(): void
    {
        $resultado = DataJudTribunalMap::resolver('0001234-56.2026.4.03.0100');

        self::assertNotNull($resultado);
        self::assertSame('trf3', $resultado['alias']);
    }

    public function testResolveProcessoDoTrt2(): void
    {
        $resultado = DataJudTribunalMap::resolver('0001234-56.2026.5.02.0100');

        self::assertNotNull($resultado);
        self::assertSame('trt2', $resultado['alias']);
    }

    public function testResolveTst(): void
    {
        $resultado = DataJudTribunalMap::resolver('0001234-56.2026.5.00.0100');

        self::assertNotNull($resultado);
        self::assertSame('tst', $resultado['alias']);
    }

    public function testResolveStj(): void
    {
        $resultado = DataJudTribunalMap::resolver('0001234-56.2026.3.00.0000');

        self::assertNotNull($resultado);
        self::assertSame('stj', $resultado['alias']);
    }

    public function testAceitaNumeroSemFormatacao(): void
    {
        $resultado = DataJudTribunalMap::resolver('00012345620268250100');

        self::assertNotNull($resultado);
        self::assertSame('tjsp', $resultado['alias']);
    }

    public function testRetornaNuloParaNumeroInvalido(): void
    {
        self::assertNull(DataJudTribunalMap::resolver('não é um número de processo'));
        self::assertNull(DataJudTribunalMap::resolver('123456'));
    }

    public function testRetornaNuloParaSegmentoDesconhecido(): void
    {
        self::assertNull(DataJudTribunalMap::resolver('0001234-56.2026.2.00.0000'));
    }
}
