<?php

declare(strict_types=1);

namespace App\Tests\PosOperatorio;

use App\Entity\PosOperatorioEvento;
use App\PosOperatorio\PosOperatorioTimelineFormatter;
use PHPUnit\Framework\TestCase;

final class PosOperatorioTimelineFormatterTest extends TestCase
{
    public function testSanitizesLegacyAccessLogWithTenantName(): void
    {
        $event = (new PosOperatorioEvento())
            ->setTipo(PosOperatorioEvento::TIPO_ACESSO_FICHA)
            ->setDescricao('Acesso à ficha (ficha_paciente) por Tenant Master · IP 127.0.0.1');

        $formatted = PosOperatorioTimelineFormatter::format($event);

        self::assertSame('Acesso à ficha', $formatted['label']);
        self::assertSame('Ficha visualizada por Equipe clínica', $formatted['detail']);
        self::assertStringNotContainsString('IP', $formatted['detail']);
        self::assertStringNotContainsString('Tenant', $formatted['detail']);
    }

    public function testNormalizesCadastroCopy(): void
    {
        $event = (new PosOperatorioEvento())
            ->setTipo(PosOperatorioEvento::TIPO_CADASTRO)
            ->setDescricao('Paciente cadastrado no núcleo');

        self::assertSame('Paciente cadastrado', PosOperatorioTimelineFormatter::format($event)['detail']);
    }
}
