<?php

namespace App\Tests\Crm;

use App\Entity\Crm\CrmLead;
use App\Entity\Crm\CrmOportunidade;
use PHPUnit\Framework\TestCase;

final class CrmEntitiesTest extends TestCase
{
    public function testLeadStatusAndOrigemLists(): void
    {
        self::assertContains(CrmLead::STATUS_NOVO, CrmLead::statusList());
        self::assertContains(CrmLead::STATUS_CONVERTIDO, CrmLead::statusList());
        self::assertContains(CrmLead::ORIGEM_SITE, CrmLead::origemList());
    }

    public function testPipelineStages(): void
    {
        self::assertSame(
            ['lead', 'qualificacao', 'proposta', 'negociacao'],
            CrmOportunidade::stagesOpen()
        );
        self::assertContains(CrmOportunidade::STAGE_GANHO, CrmOportunidade::stagesAll());
        self::assertArrayHasKey(CrmOportunidade::STAGE_PROPOSTA, CrmOportunidade::stageMeta());
    }

    public function testOportunidadeProbabilidadeClamp(): void
    {
        $op = new CrmOportunidade();
        $op->setTitulo('Teste');
        $op->setProbabilidade(150);
        self::assertSame(100, $op->getProbabilidade());
        $op->setProbabilidade(-10);
        self::assertSame(0, $op->getProbabilidade());
    }
}
