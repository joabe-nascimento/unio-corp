<?php

namespace App\Tests\Service;

use App\Rh\RhRecruitmentApprovalPolicy;
use App\Rh\RhScorecardCriteria;
use App\Rh\RhCandidatoEtapa;
use App\Rh\RhSlugger;
use PHPUnit\Framework\TestCase;

class RhRecruitmentRoadmapTest extends TestCase
{
    public function testSluggerUnique(): void
    {
        $slug = RhSlugger::unique('Desenvolvedor PHP', static fn () => false);
        self::assertSame('desenvolvedor-php', $slug);

        $slug2 = RhSlugger::unique('Desenvolvedor PHP', static fn (string $s) => $s === 'desenvolvedor-php');
        self::assertSame('desenvolvedor-php-2', $slug2);
    }

    public function testScorecardCriteriaPerEtapa(): void
    {
        $entrevista = RhScorecardCriteria::forEtapa(RhCandidatoEtapa::ENTREVISTA);
        self::assertNotEmpty($entrevista);
        self::assertSame('comunicacao', $entrevista[0]['id']);
    }

    public function testApprovalPolicy(): void
    {
        self::assertTrue(RhRecruitmentApprovalPolicy::exigeAprovacao(RhCandidatoEtapa::CONTRATADO));
        self::assertFalse(RhRecruitmentApprovalPolicy::exigeAprovacao(RhCandidatoEtapa::ENTREVISTA));
    }
}
