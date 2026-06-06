<?php

namespace App\Tests\Rh;

use App\Entity\RhCandidato;
use App\Entity\RhVaga;
use App\Rh\RhEntrevistaTipo;
use App\Rh\RhRecrutamentoDisplay;
use PHPUnit\Framework\TestCase;

final class RhRecrutamentoDisplayTest extends TestCase
{
    public function testFormatNomeSubstituiHub(): void
    {
        self::assertSame('Núcleo de Operações', RhRecrutamentoDisplay::formatNome('Hub Operações'));
    }

    public function testEntrevistaTitulo(): void
    {
        $vaga = $this->createMock(RhVaga::class);
        $vaga->method('getTitulo')->willReturn('Analista PHP');

        $candidato = $this->createMock(RhCandidato::class);
        $candidato->method('getNome')->willReturn('Maria Silva');
        $candidato->method('getVaga')->willReturn($vaga);

        self::assertSame(
            'Entrevista — Maria Silva — Analista PHP',
            RhRecrutamentoDisplay::entrevistaTitulo($candidato),
        );
    }

    public function testEntrevistaTipoLabels(): void
    {
        self::assertSame('Online', RhEntrevistaTipo::label(RhEntrevistaTipo::ONLINE));
        self::assertSame('Presencial', RhEntrevistaTipo::label(RhEntrevistaTipo::PRESENCIAL));
        self::assertTrue(RhEntrevistaTipo::isValid(RhEntrevistaTipo::ONLINE));
        self::assertFalse(RhEntrevistaTipo::isValid('INVALID'));
    }
}
