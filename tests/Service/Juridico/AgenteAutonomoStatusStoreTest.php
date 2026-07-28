<?php

declare(strict_types=1);

namespace App\Tests\Service\Juridico;

use App\Service\Juridico\AgenteAutonomoStatusStore;
use PHPUnit\Framework\TestCase;

final class AgenteAutonomoStatusStoreTest extends TestCase
{
    private string $projectDir;
    private AgenteAutonomoStatusStore $store;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/agente_autonomo_test_' . uniqid('', true);
        mkdir($this->projectDir, 0755, true);
        $this->store = new AgenteAutonomoStatusStore($this->projectDir);
    }

    protected function tearDown(): void
    {
        $file = $this->projectDir . '/var/data/agente_autonomo_status.json';
        if (is_file($file)) {
            unlink($file);
        }
        @rmdir($this->projectDir . '/var/data');
        @rmdir($this->projectDir . '/var');
        @rmdir($this->projectDir);
    }

    public function testEstadoInicialEhVazio(): void
    {
        $estado = $this->store->load();

        self::assertNull($estado['last_run_at']);
        self::assertSame(0, $estado['total_runs']);
        self::assertSame([], $estado['notificados']);
    }

    public function testResumoSemExecucaoAnteriorNaoEstaAtivo(): void
    {
        $resumo = $this->store->resumo();

        self::assertFalse($resumo['ativo']);
        self::assertNull($resumo['last_run_at']);
        self::assertSame(0, $resumo['alertas_hoje']);
    }

    public function testRegistrarExecucaoMarcaChavesENaoNotificaDeNovoDentroDaJanela(): void
    {
        $estado = $this->store->load();
        $estado = $this->store->registrarExecucao($estado, 1, 'Escritório Teste', ['prazo:10', 'processo:5:critico']);
        $this->store->persist($estado);

        $recarregado = $this->store->load();
        self::assertTrue($this->store->jaNotificado($recarregado, 'prazo:10'));
        self::assertTrue($this->store->jaNotificado($recarregado, 'processo:5:critico'));
        self::assertFalse($this->store->jaNotificado($recarregado, 'prazo:99'));

        self::assertSame(1, $recarregado['total_runs']);
        self::assertSame(2, $recarregado['total_alertas']);
    }

    public function testResumoRefleteExecucaoRecente(): void
    {
        $estado = $this->store->load();
        $estado = $this->store->registrarExecucao($estado, 3, 'Escritório X', ['prazo:1']);
        $this->store->persist($estado);

        $resumo = $this->store->resumo();

        self::assertTrue($resumo['ativo']);
        self::assertNotNull($resumo['last_run_at']);
        self::assertSame(0, $resumo['minutos_desde_execucao']);
        self::assertSame(1, $resumo['alertas_hoje']);
        self::assertSame(1, $resumo['total_runs']);
        self::assertSame(1, $resumo['empresas_monitoradas']);
    }

    public function testChavesAntigasSaoPodadasAposJanelaDeRetencao(): void
    {
        $estado = $this->store->load();
        $antigo = (new \DateTimeImmutable('-73 hours'))->format(\DateTimeInterface::ATOM);
        $estado['notificados']['prazo:antigo'] = $antigo;

        $estado = $this->store->registrarExecucao($estado, 1, 'Escritório Teste', ['prazo:novo']);

        self::assertArrayNotHasKey('prazo:antigo', $estado['notificados']);
        self::assertArrayHasKey('prazo:novo', $estado['notificados']);
    }
}
