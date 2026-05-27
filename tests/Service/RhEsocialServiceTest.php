<?php

namespace App\Tests\Service;

use App\Entity\Empresa;
use App\Entity\RhEsocialLote;
use App\Repository\FuncionarioRepository;
use App\Repository\RhEsocialLoteRepository;
use App\Service\Rh\RhAuditService;
use App\Service\Rh\RhEsocialGateway;
use App\Service\Rh\RhEsocialService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RhEsocialServiceTest extends TestCase
{
    public function testCreateLoteRejectsInvalidReference(): void
    {
        $service = $this->buildService();

        $this->expectException(\App\Exception\RhProcessException::class);
        $service->createLote(new Empresa(), 'invalid', 'S1200');
    }

    public function testProcessQueueMarksSentWhenWorkersExist(): void
    {
        $empresa = new Empresa();
        $refl = new \ReflectionProperty(Empresa::class, 'id');
        $refl->setAccessible(true);
        $refl->setValue($empresa, 1);

        $lote = new RhEsocialLote();
        $lote->setEmpresa($empresa);
        $lote->setReferencia('2026-05');
        $lote->setTipoEvento('S1200');
        $lote->setStatus(RhEsocialLote::STATUS_PENDENTE);
        $lote->setPayload(['trabalhadores' => 3]);

        $repo = $this->createMock(RhEsocialLoteRepository::class);
        $repo->method('findNextInQueue')->willReturn([$lote]);
        $repo->method('findPendingLote')->willReturn(null);

        $funcRepo = $this->createMock(FuncionarioRepository::class);
        $funcRepo->method('countByStatusGrouped')->willReturn(['ATIVO' => 2]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->atLeastOnce())->method('flush');

        $audit = $this->createMock(RhAuditService::class);

        $service = new RhEsocialService($em, $repo, $funcRepo, new RhEsocialGateway(), $audit);
        $stats = $service->processQueue($empresa, 5);

        $this->assertSame(1, $stats['processados']);
        $this->assertSame(1, $stats['enviados']);
        $this->assertSame(RhEsocialLote::STATUS_ENVIADO, $lote->getStatus());
        $this->assertNotNull($lote->getProtocolo());
    }

    private function buildService(): RhEsocialService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(RhEsocialLoteRepository::class);
        $funcRepo = $this->createMock(FuncionarioRepository::class);
        $audit = $this->createMock(RhAuditService::class);

        return new RhEsocialService($em, $repo, $funcRepo, new RhEsocialGateway(), $audit);
    }
}
