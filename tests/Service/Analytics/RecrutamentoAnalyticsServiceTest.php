<?php

namespace App\Tests\Service\Analytics;

use App\Entity\Empresa;
use App\Repository\RhCandidatoRepository;
use App\Repository\RhVagaRepository;
use App\Chart\ChartPanelFactory;
use App\Service\Analytics\RecrutamentoAnalyticsService;
use PHPUnit\Framework\TestCase;

final class RecrutamentoAnalyticsServiceTest extends TestCase
{
    public function testBuildSectionsReturnsEmptyWhenNoData(): void
    {
        $candidatoRepo = $this->createMock(RhCandidatoRepository::class);
        $vagaRepo = $this->createMock(RhVagaRepository::class);
        $empresa = new Empresa();

        $candidatoRepo->method('countByOrigemForEmpresa')->willReturn([
            'MANUAL' => 0,
            'INDICACAO' => 0,
            'LINKEDIN' => 0,
            'SITE' => 0,
            'INDEED' => 0,
            'EVENTO' => 0,
            'BANCO_TALENTOS' => 0,
        ]);
        $candidatoRepo->method('countByEtapaForEmpresa')->willReturn(0);
        $candidatoRepo->method('countOrigemEtapaForEmpresa')->willReturn([]);
        $vagaRepo->method('countByStatusForEmpresa')->willReturn([
            'ABERTA' => 0,
            'PAUSADA' => 0,
            'FECHADA' => 0,
        ]);
        $vagaRepo->method('countGroupedByDepartamentoForEmpresa')->willReturn(['labels' => [], 'values' => []]);
        $vagaRepo->method('countGroupedByLocalForEmpresa')->willReturn(['labels' => [], 'values' => []]);

        $service = new RecrutamentoAnalyticsService($candidatoRepo, $vagaRepo, new ChartPanelFactory());

        self::assertSame([], $service->buildSections($empresa));
    }

    public function testBuildSectionsIncludesCaptacaoWhenOrigemHasData(): void
    {
        $candidatoRepo = $this->createMock(RhCandidatoRepository::class);
        $vagaRepo = $this->createMock(RhVagaRepository::class);
        $empresa = new Empresa();

        $candidatoRepo->method('countByOrigemForEmpresa')->willReturn([
            'MANUAL' => 2,
            'INDICACAO' => 0,
            'LINKEDIN' => 1,
            'SITE' => 0,
            'INDEED' => 0,
            'EVENTO' => 0,
            'BANCO_TALENTOS' => 0,
        ]);
        $candidatoRepo->method('countByEtapaForEmpresa')->willReturnCallback(
            static fn (Empresa $e, string $etapa): int => $etapa === 'TRIAGEM' ? 3 : 0,
        );
        $candidatoRepo->method('countOrigemEtapaForEmpresa')->willReturn([
            ['origem' => 'MANUAL', 'etapa' => 'TRIAGEM', 'total' => 2],
            ['origem' => 'LINKEDIN', 'etapa' => 'TRIAGEM', 'total' => 1],
        ]);
        $vagaRepo->method('countByStatusForEmpresa')->willReturn([
            'ABERTA' => 0,
            'PAUSADA' => 0,
            'FECHADA' => 0,
        ]);
        $vagaRepo->method('countGroupedByDepartamentoForEmpresa')->willReturn(['labels' => [], 'values' => []]);
        $vagaRepo->method('countGroupedByLocalForEmpresa')->willReturn(['labels' => [], 'values' => []]);

        $service = new RecrutamentoAnalyticsService($candidatoRepo, $vagaRepo, new ChartPanelFactory());
        $sections = $service->buildSections($empresa);

        self::assertCount(1, $sections);
        self::assertSame('recrutamento-captacao', $sections[0]['id']);
        self::assertGreaterThanOrEqual(2, \count($sections[0]['charts']));
    }
}
