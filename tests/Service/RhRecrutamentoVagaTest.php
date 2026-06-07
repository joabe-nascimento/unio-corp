<?php

namespace App\Tests\Service;

use App\Entity\Empresa;
use App\Entity\RhVaga;
use App\Entity\User;
use App\Service\Rh\RhAuditService;
use App\Service\Rh\RhRecruitmentNotificationService;
use App\Service\Rh\RhRecrutamentoService;
use App\Service\RhOnboardingService;
use App\Service\PlatformNotificationService;
use App\Service\PlatformNotificationPresenter;
use App\Security\ProductGrantAccess;
use App\Repository\UserRepository;
use App\Repository\PlatformNotificacaoRepository;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\UserProductGrantRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RhRecrutamentoVagaTest extends TestCase
{
    private function recruitmentNotifications(): RhRecruitmentNotificationService
    {
        return new RhRecruitmentNotificationService(
            new PlatformNotificationService(
                $this->createMock(EntityManagerInterface::class),
                $this->createMock(PlatformNotificacaoRepository::class),
                new PlatformNotificationPresenter(),
            ),
            $this->createMock(UserRepository::class),
            new ProductGrantAccess(
                $this->createMock(Security::class),
                $this->createMock(UserProductGrantRepository::class),
            ),
        );
    }

    public function testUpdateVagaStatusRejectsInvalidStatus(): void
    {
        $vaga = $this->createConfiguredMock(RhVaga::class, [
            'getEmpresa' => $this->createMock(Empresa::class),
            'getId' => 1,
        ]);

        $service = new RhRecrutamentoService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(\App\Repository\RhVagaRepository::class),
            $this->createMock(\App\Repository\RhCandidatoRepository::class),
            $this->createMock(\App\Repository\RhOnboardingProcessRepository::class),
            $this->createMock(\App\Repository\RhAuditLogRepository::class),
            $this->createMock(RhOnboardingService::class),
            $this->createMock(RhAuditService::class),
            $this->recruitmentNotifications(),
        );

        $this->expectException(\App\Exception\RhProcessException::class);
        $service->updateVagaStatus($vaga, 'INVALIDO', null);
    }

    public function testUpdateVagaStatusPersistsAndAudits(): void
    {
        $empresa = $this->createMock(Empresa::class);
        $vaga = $this->createConfiguredMock(RhVaga::class, [
            'getEmpresa' => $empresa,
            'getId' => 4,
        ]);
        $vaga->expects(self::once())->method('setStatus')->with(RhVaga::STATUS_PAUSADA);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $audit = $this->createMock(RhAuditService::class);
        $audit->expects(self::once())->method('log');

        $service = new RhRecrutamentoService(
            $em,
            $this->createMock(\App\Repository\RhVagaRepository::class),
            $this->createMock(\App\Repository\RhCandidatoRepository::class),
            $this->createMock(\App\Repository\RhOnboardingProcessRepository::class),
            $this->createMock(\App\Repository\RhAuditLogRepository::class),
            $this->createMock(RhOnboardingService::class),
            $audit,
            $this->recruitmentNotifications(),
        );

        self::assertSame($vaga, $service->updateVagaStatus($vaga, RhVaga::STATUS_PAUSADA, $this->createMock(User::class)));
    }
}
