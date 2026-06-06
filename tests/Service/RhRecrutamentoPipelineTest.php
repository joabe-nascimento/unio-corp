<?php

namespace App\Tests\Service;

use App\Rh\RhCandidatoEtapa;
use PHPUnit\Framework\TestCase;

class RhRecrutamentoPipelineTest extends TestCase
{
    public function testEtapaPipelineOrder(): void
    {
        self::assertSame('ENTREVISTA', RhCandidatoEtapa::next(RhCandidatoEtapa::TRIAGEM));
        self::assertSame('PROPOSTA', RhCandidatoEtapa::next(RhCandidatoEtapa::ENTREVISTA));
        self::assertSame('CONTRATADO', RhCandidatoEtapa::next(RhCandidatoEtapa::PROPOSTA));
        self::assertNull(RhCandidatoEtapa::next(RhCandidatoEtapa::CONTRATADO));
        self::assertSame('TRIAGEM', RhCandidatoEtapa::prev(RhCandidatoEtapa::ENTREVISTA));
    }

    public function testBoardStagesIncludeReprovado(): void
    {
        $ids = array_column(RhCandidatoEtapa::boardStages(), 'id');
        self::assertContains(RhCandidatoEtapa::REPROVADO, $ids);
        self::assertCount(5, $ids);
    }

    public function testEtapaBadgeVariant(): void
    {
        self::assertSame('danger', RhCandidatoEtapa::badgeVariant(RhCandidatoEtapa::REPROVADO));
        self::assertSame('success', RhCandidatoEtapa::badgeVariant(RhCandidatoEtapa::CONTRATADO));
        self::assertSame('info', RhCandidatoEtapa::badgeVariant(RhCandidatoEtapa::TRIAGEM));
        self::assertSame('info', RhCandidatoEtapa::badgeVariant(RhCandidatoEtapa::ENTREVISTA));
    }
}
