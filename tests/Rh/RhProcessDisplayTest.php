<?php

namespace App\Tests\Rh;

use App\Rh\RhProcessDisplay;
use PHPUnit\Framework\TestCase;

final class RhProcessDisplayTest extends TestCase
{
    public function testColaboradorNomeIgnoraNomeDeHub(): void
    {
        self::assertSame(
            'Admin',
            RhProcessDisplay::colaboradorNome('Hub Operações', 'admin@netflix.com'),
        );
    }

    public function testColaboradorNomeIgnoraNomeDaEmpresa(): void
    {
        self::assertSame(
            'Maria Silva',
            RhProcessDisplay::colaboradorNome('Netflix Inc', 'maria.silva@netflix.com', 'Netflix Inc'),
        );
    }

    public function testColaboradorNomeMantemNomeValido(): void
    {
        self::assertSame(
            'Maria Fernandes',
            RhProcessDisplay::colaboradorNome('Maria Fernandes', 'maria@empresa.com'),
        );
    }

    public function testIsGenericHubName(): void
    {
        self::assertTrue(RhProcessDisplay::isGenericHubName('Hub Operações'));
        self::assertTrue(RhProcessDisplay::isGenericHubName('Núcleo de Recrutamento'));
        self::assertFalse(RhProcessDisplay::isGenericHubName('Maria Silva'));
    }
}
