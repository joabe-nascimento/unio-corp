<?php

namespace App\Tests\Service\Juridico;

use App\Service\Juridico\PrazoProcessualCalculator;
use PHPUnit\Framework\TestCase;

final class PrazoProcessualCalculatorTest extends TestCase
{
    private PrazoProcessualCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PrazoProcessualCalculator();
    }

    public function testDiasUteisPulaFimDeSemana(): void
    {
        // 2025-03-10 é segunda-feira (fora do recesso forense). 3 dias úteis => quinta-feira 2025-03-13.
        $base = new \DateTimeImmutable('2025-03-10');
        $resultado = $this->calculator->calcular($base, 3, PrazoProcessualCalculator::TIPO_UTIL);

        self::assertSame('2025-03-13', $resultado['data_final']->format('Y-m-d'));
    }

    public function testDiasUteisPulaFimDeSemanaQuandoAtravessa(): void
    {
        // 2025-03-14 é sexta-feira. 1 dia útil deve pular o fim de semana e cair na segunda 2025-03-17.
        $base = new \DateTimeImmutable('2025-03-14');
        $resultado = $this->calculator->calcular($base, 1, PrazoProcessualCalculator::TIPO_UTIL);

        self::assertSame('2025-03-17', $resultado['data_final']->format('Y-m-d'));
    }

    public function testDiasCorridosContaTodoMundo(): void
    {
        $base = new \DateTimeImmutable('2025-01-10');
        $resultado = $this->calculator->calcular($base, 5, PrazoProcessualCalculator::TIPO_CORRIDO, false, false);

        self::assertSame('2025-01-15', $resultado['data_final']->format('Y-m-d'));
    }

    public function testPrazoEmDobroDuplicaDias(): void
    {
        $base = new \DateTimeImmutable('2025-01-06');
        $simples = $this->calculator->calcular($base, 3, PrazoProcessualCalculator::TIPO_UTIL, false);
        $dobro = $this->calculator->calcular($base, 3, PrazoProcessualCalculator::TIPO_UTIL, true);

        self::assertSame(3, $simples['dias_efetivos']);
        self::assertSame(6, $dobro['dias_efetivos']);
        self::assertGreaterThan($simples['data_final'], $dobro['data_final']);
    }

    public function testRecessoForenseSuspendeContagem(): void
    {
        // Começando em 15/dez/2025 (segunda), 5 dias úteis com recesso forense (20/dez a 20/jan)
        // devem pular todo o recesso, terminando só em janeiro.
        $base = new \DateTimeImmutable('2025-12-15');
        $comRecesso = $this->calculator->calcular($base, 5, PrazoProcessualCalculator::TIPO_UTIL, false, true);
        $semRecesso = $this->calculator->calcular($base, 5, PrazoProcessualCalculator::TIPO_UTIL, false, false);

        self::assertGreaterThan($semRecesso['data_final'], $comRecesso['data_final']);
        self::assertSame('2026-01-21', $comRecesso['data_final']->format('Y-m-d'));
    }

    public function testFeriadoNacionalFixoENaoContado(): void
    {
        // Tiradentes 2025-04-21 (segunda) e Sexta-feira Santa 2025-04-18 não contam como dia útil.
        $base = new \DateTimeImmutable('2025-04-17'); // quinta
        $resultado = $this->calculator->calcular($base, 2, PrazoProcessualCalculator::TIPO_UTIL, false, false);

        // 18/04 (Sexta-feira Santa, pulado); 19-20 fim de semana; 21/04 (Tiradentes, pulado);
        // 22/04 (terça) = dia 1; 23/04 (quarta) = dia 2.
        self::assertSame('2025-04-23', $resultado['data_final']->format('Y-m-d'));
        self::assertCount(2, $resultado['feriados_no_periodo']);
    }

    public function testFeriadoMovelSextaFeiraSanta(): void
    {
        // Páscoa de 2025 é 20/abr; sexta-feira santa é 18/abr/2025.
        $base = new \DateTimeImmutable('2025-04-16'); // quarta
        $resultado = $this->calculator->calcular($base, 2, PrazoProcessualCalculator::TIPO_UTIL, false, false);

        $nomes = array_column($resultado['feriados_no_periodo'], 'nome');
        self::assertContains('Sexta-feira Santa', $nomes);
    }

    public function testPrazosComunsTemContestacao(): void
    {
        $prazos = PrazoProcessualCalculator::prazosComuns();
        self::assertSame(15, $prazos['contestação']);
        self::assertSame(5, $prazos['embargos de declaração']);
    }
}
