<?php

declare(strict_types=1);

namespace App\Tests\PosOperatorio;

use App\Entity\PosOperatorioPaciente;
use App\PosOperatorio\PosOperatorioDisplay;
use PHPUnit\Framework\TestCase;

final class PosOperatorioDisplayTest extends TestCase
{
    public function testRejectsGenericHubNameAsPatientName(): void
    {
        self::assertTrue(PosOperatorioDisplay::isInvalidPatientName('Hub Operações'));
    }

    public function testFallsBackWhenStoredNameIsGenericHubLabel(): void
    {
        $paciente = (new PosOperatorioPaciente())
            ->setCodigo('PO-1001')
            ->setNome('Hub Operações');

        self::assertSame('Paciente PO-1001', PosOperatorioDisplay::pacienteNome($paciente));
    }

    public function testStatusLabel(): void
    {
        self::assertSame('Ativo', PosOperatorioDisplay::statusLabel('ativo'));
    }
}
