<?php

namespace App\Tests\Entity;

use App\Entity\TiChamado;
use PHPUnit\Framework\TestCase;

class TiChamadoTimelineTest extends TestCase
{
    public function testAddTimelineEventReassignsJsonArray(): void
    {
        $chamado = new TiChamado();
        $before = $chamado->getTimeline();

        $chamado->addTimelineEvent('Resposta do solicitante: teste', 'João');
        $after = $chamado->getTimeline();

        self::assertNotSame($before, $after);
        self::assertCount(1, $after);
        self::assertSame('Resposta do solicitante: teste', $after[0]['event']);
        self::assertSame('João', $after[0]['actor']);
    }

    public function testAddTimelineEventAppendsMultipleEntries(): void
    {
        $chamado = new TiChamado();
        $chamado->addTimelineEvent('Chamado registrado', 'Sistema');
        $chamado->addTimelineEvent('Resposta da TI: ok', 'Técnico');

        self::assertCount(2, $chamado->getTimeline());
    }
}
