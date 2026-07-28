<?php

namespace App\Tests\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoProcesso;
use App\Entity\JuridicoProcessoTarefa;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\JuridicoProcessoTarefaRepository;
use App\Service\Juridico\PrevisaoExitoService;
use PHPUnit\Framework\TestCase;

final class PrevisaoExitoServiceTest extends TestCase
{
    private JuridicoProcessoRepository&\PHPUnit\Framework\MockObject\MockObject $processoRepo;
    private JuridicoProcessoTarefaRepository&\PHPUnit\Framework\MockObject\MockObject $tarefaRepo;
    private PrevisaoExitoService $service;

    protected function setUp(): void
    {
        $this->processoRepo = $this->createMock(JuridicoProcessoRepository::class);
        $this->tarefaRepo = $this->createMock(JuridicoProcessoTarefaRepository::class);
        $this->service = new PrevisaoExitoService($this->processoRepo, $this->tarefaRepo);
    }

    private function criarProcesso(): JuridicoProcesso
    {
        $processo = new JuridicoProcesso();
        $processo->setEmpresa(new Empresa());
        $processo->setNumero('0001234-56.2026.8.26.0100');

        return $processo;
    }

    public function testScoreFicaEntreCincoENoventaECincoSempre(): void
    {
        $this->processoRepo->method('taxaExitoPorArea')->willReturn([]);
        $this->tarefaRepo->method('findForProcesso')->willReturn([]);

        $processo = $this->criarProcesso();
        $resultado = $this->service->prever($processo);

        self::assertGreaterThanOrEqual(5, $resultado['score']);
        self::assertLessThanOrEqual(95, $resultado['score']);
        self::assertNotEmpty($resultado['fatores']);
    }

    public function testHistoricoFavoravelAumentaOScore(): void
    {
        $this->processoRepo->method('taxaExitoPorArea')->willReturn([
            'trabalhista' => ['taxa' => 90.0, 'total' => 10],
        ]);
        $this->tarefaRepo->method('findForProcesso')->willReturn([]);

        $processo = $this->criarProcesso();
        $processo->setArea('trabalhista');
        $resultado = $this->service->prever($processo);

        $fatorHistorico = current(array_filter($resultado['fatores'], static fn (array $f) => str_starts_with($f['label'], 'Histórico da área')));
        self::assertNotFalse($fatorHistorico);
        self::assertGreaterThan(0, $fatorHistorico['peso']);
        self::assertTrue($fatorHistorico['favoravel']);
    }

    public function testHistoricoInsuficienteNaoAlteraOScore(): void
    {
        $this->processoRepo->method('taxaExitoPorArea')->willReturn([
            'civel' => ['taxa' => 10.0, 'total' => 1],
        ]);
        $this->tarefaRepo->method('findForProcesso')->willReturn([]);

        $processo = $this->criarProcesso();
        $processo->setArea('civel');
        $resultado = $this->service->prever($processo);

        $fatorHistorico = current(array_filter($resultado['fatores'], static fn (array $f) => str_starts_with($f['label'], 'Histórico da área')));
        self::assertSame(0, $fatorHistorico['peso']);
    }

    public function testFaseDeExecucaoAumentaOScoreEFaseRecursalReduz(): void
    {
        $this->processoRepo->method('taxaExitoPorArea')->willReturn([]);
        $this->tarefaRepo->method('findForProcesso')->willReturn([]);

        $emExecucao = $this->criarProcesso();
        $emExecucao->setFase(JuridicoProcesso::FASE_EXECUCAO);
        $scoreExecucao = $this->service->prever($emExecucao)['score'];

        $emRecursal = $this->criarProcesso();
        $emRecursal->setFase(JuridicoProcesso::FASE_RECURSAL);
        $scoreRecursal = $this->service->prever($emRecursal)['score'];

        self::assertGreaterThan($scoreRecursal, $scoreExecucao);
    }

    public function testTarefasAtrasadasReduzemOScore(): void
    {
        $this->processoRepo->method('taxaExitoPorArea')->willReturn([]);

        $tarefaAtrasada = $this->createMock(JuridicoProcessoTarefa::class);
        $tarefaAtrasada->method('isConcluida')->willReturn(false);
        $tarefaAtrasada->method('getPrazo')->willReturn(new \DateTimeImmutable('-5 days'));

        $this->tarefaRepo->method('findForProcesso')->willReturn([$tarefaAtrasada]);

        $processo = $this->criarProcesso();
        $resultado = $this->service->prever($processo);

        $fatorExecucao = current(array_filter($resultado['fatores'], static fn (array $f) => $f['label'] === 'Execução do caso'));
        self::assertLessThan(0, $fatorExecucao['peso']);
        self::assertFalse($fatorExecucao['favoravel']);
    }

    public function testNivelECorCondizemComScore(): void
    {
        $this->processoRepo->method('taxaExitoPorArea')->willReturn([
            'geral' => ['taxa' => 95.0, 'total' => 20],
        ]);
        $this->tarefaRepo->method('findForProcesso')->willReturn([]);

        $processo = $this->criarProcesso();
        $processo->setFase(JuridicoProcesso::FASE_EXECUCAO);
        $resultado = $this->service->prever($processo);

        if ($resultado['score'] >= 70) {
            self::assertSame('alto', $resultado['nivel']);
            self::assertSame('Probabilidade alta', $resultado['label']);
            self::assertSame('#2fbf71', $resultado['cor']);
        }
    }
}
