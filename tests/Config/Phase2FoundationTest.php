<?php

namespace App\Tests\Config;

use App\Service\PosOperatorio\PosOperatorioMercureTopics;
use App\Service\PosOperatorio\SashaContextService;
use PHPUnit\Framework\TestCase;

final class Phase2FoundationTest extends TestCase
{
    public function testMercureTopicPerEmpresa(): void
    {
        $topics = new PosOperatorioMercureTopics('https://localhost/pos-operatorio');
        self::assertSame(
            'https://localhost/pos-operatorio/empresa/42/alertas',
            $topics->alertas(42),
        );
    }

    public function testSashaContextEnrichmentStructure(): void
    {
        $service = new SashaContextService(
            $this->createMock(\App\Repository\PosOperatorioPacienteRepository::class),
            $this->createMock(\App\Repository\PosOperatorioAlertaRepository::class),
        );

        $base = ['hub' => 'hub_pos_operatorio'];
        $enriched = $service->enrichChatContext(
            $this->createMock(\App\Entity\Empresa::class),
            $base,
            null,
        );

        self::assertSame('hub_pos_operatorio', $enriched['hub']);
        self::assertArrayNotHasKey('extra', $enriched);
    }
}
