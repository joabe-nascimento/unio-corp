<?php

namespace App\Tests\Service\PosOperatorio;

use App\Service\PosOperatorio\PosOperatorioProtocoloService;
use PHPUnit\Framework\TestCase;

final class PosOperatorioProtocoloServiceTest extends TestCase
{
    public function testParseChecklistText(): void
    {
        $service = new PosOperatorioProtocoloService(
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\App\Repository\PosOperatorioProtocoloRepository::class),
        );

        $items = $service->parseChecklistText("1: Repouso\n3: Curativo\n\n7: Retorno");
        self::assertCount(3, $items);
        self::assertSame(1, $items[0]['dia']);
        self::assertSame('Repouso', $items[0]['item']);
    }
}
