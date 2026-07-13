<?php

namespace App\Tests\Service\PosOperatorio;

use App\Entity\ClinicConta;
use App\Entity\ClinicGuiaItem;
use App\Entity\ClinicGuiaTiss;
use App\Entity\Empresa;
use App\Repository\ClinicContaRepository;
use App\Repository\ClinicConvenioRepository;
use App\Repository\ClinicGuiaTissRepository;
use App\Service\PosOperatorio\ClinicContaService;
use App\Service\PosOperatorio\ClinicGuiaTissService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ClinicBillingRulesTest extends TestCase
{
    public function testGuiaTransitionsForwardOnly(): void
    {
        self::assertSame(
            [ClinicGuiaTiss::STATUS_ENVIADO, ClinicGuiaTiss::STATUS_CANCELADO],
            ClinicGuiaTiss::allowedTransitionsFrom(ClinicGuiaTiss::STATUS_RASCUNHO),
        );
        self::assertSame(
            [ClinicGuiaTiss::STATUS_AUTORIZADO, ClinicGuiaTiss::STATUS_GLOSADO, ClinicGuiaTiss::STATUS_CANCELADO],
            ClinicGuiaTiss::allowedTransitionsFrom(ClinicGuiaTiss::STATUS_ENVIADO),
        );
        self::assertSame(
            [ClinicGuiaTiss::STATUS_PAGO, ClinicGuiaTiss::STATUS_GLOSADO, ClinicGuiaTiss::STATUS_CANCELADO],
            ClinicGuiaTiss::allowedTransitionsFrom(ClinicGuiaTiss::STATUS_AUTORIZADO),
        );
        self::assertSame([], ClinicGuiaTiss::allowedTransitionsFrom(ClinicGuiaTiss::STATUS_PAGO));
        self::assertSame([], ClinicGuiaTiss::allowedTransitionsFrom(ClinicGuiaTiss::STATUS_GLOSADO));
    }

    public function testGuiaEditableOnlyInRascunho(): void
    {
        $guia = new ClinicGuiaTiss();
        self::assertTrue($guia->isEditable());

        $guia->setStatus(ClinicGuiaTiss::STATUS_ENVIADO);
        self::assertFalse($guia->isEditable());

        $guia->setStatus(ClinicGuiaTiss::STATUS_GLOSADO);
        self::assertTrue($guia->canReabrirAposGlosa());
    }

    public function testGuiaTotalCentavos(): void
    {
        $guia = new ClinicGuiaTiss();
        $item = new ClinicGuiaItem();
        $item->setDescricao('Consulta');
        $item->setQuantidade(2);
        $item->setValorCentavos(15000);
        $guia->addItem($item);

        self::assertSame(30000, $guia->totalCentavos());
    }

    public function testAppendGlosaHistoricoMultipleAttempts(): void
    {
        $guia = new ClinicGuiaTiss();
        $guia->appendGlosaHistorico('Documentação incompleta');
        $guia->appendGlosaHistorico('Código TUSS inválido');

        $hist = $guia->getHistoricoGlosas();
        self::assertCount(2, $hist);
        self::assertSame(1, $hist[0]['tentativa']);
        self::assertSame('Documentação incompleta', $hist[0]['motivo']);
        self::assertSame(2, $hist[1]['tentativa']);
        self::assertSame('Código TUSS inválido', $hist[1]['motivo']);
        self::assertSame('Código TUSS inválido', $guia->getMotivoGlosa());
    }

    public function testChangeStatusRejectsInvalidJump(): void
    {
        $empresa = $this->empresa(1);
        $conta = $this->conta($empresa);
        $guia = new ClinicGuiaTiss();
        $guia->setEmpresa($empresa);
        $guia->setConta($conta);
        $guia->setStatus(ClinicGuiaTiss::STATUS_RASCUNHO);

        $service = $this->guiaService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Não é possível ir');
        $service->changeStatus($guia, $empresa, ClinicGuiaTiss::STATUS_PAGO);
    }

    public function testMarkPagoRequiresValor(): void
    {
        $empresa = $this->empresa(1);
        $conta = $this->conta($empresa);
        $service = $this->contaService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Informe o valor pago');
        $service->markPago($conta, $empresa, null);
    }

    public function testMarkPagoAcceptsValor(): void
    {
        $empresa = $this->empresa(1);
        $conta = $this->conta($empresa);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $service = new ClinicContaService(
            $this->createMock(ClinicContaRepository::class),
            $em,
        );

        $updated = $service->markPago($conta, $empresa, 25000);
        self::assertSame(ClinicConta::STATUS_PAGO, $updated->getStatus());
        self::assertSame(25000, $updated->getValorCentavos());
    }

    public function testReabrirAposGlosa(): void
    {
        $empresa = $this->empresa(1);
        $conta = $this->conta($empresa);
        $conta->setStatus(ClinicConta::STATUS_GLOSADO);
        $guia = new ClinicGuiaTiss();
        $guia->setEmpresa($empresa);
        $guia->setConta($conta);
        $guia->setStatus(ClinicGuiaTiss::STATUS_GLOSADO);
        $guia->appendGlosaHistorico('Glosa #1');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $contaRepo = $this->createMock(ClinicContaRepository::class);
        $contas = new ClinicContaService($contaRepo, $em);
        $service = new ClinicGuiaTissService(
            $this->createMock(ClinicGuiaTissRepository::class),
            $this->createMock(ClinicConvenioRepository::class),
            $contas,
            $em,
        );

        $reaberta = $service->reabrirAposGlosa($guia, $empresa);
        self::assertSame(ClinicGuiaTiss::STATUS_RASCUNHO, $reaberta->getStatus());
        self::assertTrue($reaberta->isEditable());
        self::assertSame(ClinicConta::STATUS_ABERTO, $conta->getStatus());
        self::assertCount(1, $reaberta->getHistoricoGlosas());
        self::assertNull($reaberta->getMotivoGlosa());
    }

    private function empresa(int $id): Empresa
    {
        $empresa = $this->createMock(Empresa::class);
        $empresa->method('getId')->willReturn($id);

        return $empresa;
    }

    private function conta(Empresa $empresa): ClinicConta
    {
        $conta = new ClinicConta();
        $conta->setEmpresa($empresa);
        $conta->setTipo(ClinicConta::TIPO_PARTICULAR);
        $conta->setStatus(ClinicConta::STATUS_ABERTO);

        return $conta;
    }

    private function guiaService(): ClinicGuiaTissService
    {
        return new ClinicGuiaTissService(
            $this->createMock(ClinicGuiaTissRepository::class),
            $this->createMock(ClinicConvenioRepository::class),
            $this->contaService(),
            $this->createMock(EntityManagerInterface::class),
        );
    }

    private function contaService(): ClinicContaService
    {
        return new ClinicContaService(
            $this->createMock(ClinicContaRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );
    }
}
