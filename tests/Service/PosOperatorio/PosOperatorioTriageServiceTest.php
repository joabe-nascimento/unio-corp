<?php

namespace App\Tests\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Service\PosOperatorio\PosOperatorioLocalTriage;
use PHPUnit\Framework\TestCase;

final class PosOperatorioTriageServiceTest extends TestCase
{
    private function paciente(): PosOperatorioPaciente
    {
        $empresa = (new Empresa())->setNome('Clinica Teste');
        // Empresa may need id — only used as relation
        return (new PosOperatorioPaciente())
            ->setEmpresa($empresa)
            ->setCodigo('PO-TEST')
            ->setNome('Teste')
            ->setObservacoes(null);
    }

    public function testSangramentoIntensoIsP1(): void
    {
        $result = PosOperatorioLocalTriage::evaluate(
            ['dor' => 2, 'sangramento' => 'intenso'],
            $this->paciente(),
        );

        self::assertSame('P1', $result['prioridade']);
        self::assertTrue($result['requer_contato_imediato']);
        self::assertStringContainsString('Sangramento intenso', $result['motivo']);
    }

    public function testProtocolRulesOverrideDorThreshold(): void
    {
        $result = PosOperatorioLocalTriage::evaluate(
            ['dor' => 7],
            $this->paciente(),
            ['dor_p1_min' => 7, 'dor_p2_min' => 4, 'febre_p2_min' => 38.5],
        );

        self::assertSame('P1', $result['prioridade']);
    }

    public function testNauseaPersistenteRaisesPriority(): void
    {
        $result = PosOperatorioLocalTriage::evaluate(
            ['dor' => 1, 'nausea' => 'persistente'],
            $this->paciente(),
        );

        self::assertSame('P2', $result['prioridade']);
    }
}
