<?php

namespace App\Tests\Service;

use App\Entity\Empresa;
use App\Entity\RhCandidato;
use App\Entity\RhOnboardingProcess;
use App\Entity\RhVaga;
use App\Repository\RhCandidatoRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Repository\RhVagaRepository;
use App\Rh\RhCandidatoEtapa;
use App\Service\Rh\RhAuditService;
use App\Service\Rh\RhRecruitmentNotificationService;
use App\Service\Rh\RhRecrutamentoService;
use App\Service\RhOnboardingService;
use App\Service\PlatformNotificationService;
use App\Service\PlatformNotificationPresenter;
use App\Security\ProductGrantAccess;
use App\Service\Organismo\OrganismoFeature;
use App\Repository\UserRepository;
use App\Repository\PlatformNotificacaoRepository;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\UserProductGrantRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RhRecrutamentoConvertTest extends TestCase
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
                new OrganismoFeature(false, false),
            ),
        );
    }

    public function testConvertToOnboardingCreatesProcessAndLinksCandidato(): void
    {
        $empresa = $this->createMock(Empresa::class);
        $vaga = $this->createConfiguredMock(RhVaga::class, [
            'getEmpresa' => $empresa,
            'getId' => 10,
            'getTitulo' => 'Analista RH',
            'getDepartamento' => 'People',
            'getDescricao' => 'Vaga teste',
        ]);

        $candidato = $this->createConfiguredMock(RhCandidato::class, [
            'getOnboardingProcess' => null,
            'getVaga' => $vaga,
            'getNome' => 'Maria Silva',
            'getEmail' => 'maria@empresa.com',
            'getTelefone' => '11999999999',
            'getId' => 5,
        ]);

        $process = $this->createConfiguredMock(RhOnboardingProcess::class, ['getId' => 99]);

        $onboardingRepo = $this->createMock(RhOnboardingProcessRepository::class);
        $onboardingRepo->method('findOpenByEmail')->willReturn(null);

        $onboarding = $this->createMock(RhOnboardingService::class);
        $onboarding->expects(self::once())
            ->method('create')
            ->with(
                $empresa,
                'Maria Silva',
                'maria@empresa.com',
                'Analista RH · People',
                null,
                self::stringContains('Núcleo de Recrutamento'),
            )
            ->willReturn($process);

        $candidato->expects(self::once())->method('setOnboardingProcess')->with($process);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $audit = $this->createMock(RhAuditService::class);
        $audit->expects(self::once())->method('log');

        $service = new RhRecrutamentoService(
            $em,
            $this->createMock(RhVagaRepository::class),
            $this->createMock(RhCandidatoRepository::class),
            $onboardingRepo,
            $this->createMock(\App\Repository\RhAuditLogRepository::class),
            $onboarding,
            $audit,
            $this->recruitmentNotifications(),
        );

        self::assertSame($process, $service->convertToOnboarding($candidato, null));
    }

    public function testConvertToOnboardingReusesExistingOpenProcess(): void
    {
        $empresa = $this->createMock(Empresa::class);
        $vaga = $this->createConfiguredMock(RhVaga::class, [
            'getEmpresa' => $empresa,
            'getId' => 3,
            'getTitulo' => 'Dev',
        ]);
        $existing = $this->createConfiguredMock(RhOnboardingProcess::class, ['getId' => 7]);
        $candidato = $this->createConfiguredMock(RhCandidato::class, [
            'getOnboardingProcess' => null,
            'getVaga' => $vaga,
            'getEmail' => 'dev@empresa.com',
            'getId' => 2,
        ]);

        $onboardingRepo = $this->createMock(RhOnboardingProcessRepository::class);
        $onboardingRepo->method('findOpenByEmail')->willReturn($existing);

        $onboarding = $this->createMock(RhOnboardingService::class);
        $onboarding->expects(self::never())->method('create');

        $candidato->expects(self::once())->method('setOnboardingProcess')->with($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $service = new RhRecrutamentoService(
            $em,
            $this->createMock(RhVagaRepository::class),
            $this->createMock(RhCandidatoRepository::class),
            $onboardingRepo,
            $this->createMock(\App\Repository\RhAuditLogRepository::class),
            $onboarding,
            $this->createMock(RhAuditService::class),
            $this->recruitmentNotifications(),
        );

        self::assertSame($existing, $service->convertToOnboarding($candidato, null));
    }

    public function testMoveToContratadoUsesTransaction(): void
    {
        $empresa = $this->createMock(Empresa::class);
        $vaga = $this->createConfiguredMock(RhVaga::class, [
            'getEmpresa' => $empresa,
            'getId' => 1,
            'getTitulo' => 'Cargo',
            'getDepartamento' => null,
            'getDescricao' => null,
        ]);
        $process = $this->createConfiguredMock(RhOnboardingProcess::class, ['getId' => 1]);

        $candidato = $this->getMockBuilder(RhCandidato::class)
            ->onlyMethods(['getEtapa', 'setEtapa', 'getOnboardingProcess', 'setOnboardingProcess', 'getVaga', 'getNome', 'getEmail', 'getTelefone', 'getId'])
            ->getMock();
        $candidato->method('getEtapa')->willReturn(RhCandidatoEtapa::PROPOSTA);
        $candidato->method('getOnboardingProcess')->willReturn(null);
        $candidato->method('getVaga')->willReturn($vaga);
        $candidato->method('getNome')->willReturn('João');
        $candidato->method('getEmail')->willReturn('joao@empresa.com');
        $candidato->method('getTelefone')->willReturn(null);
        $candidato->method('getId')->willReturn(1);
        $candidato->expects(self::once())->method('setEtapa')->with(RhCandidatoEtapa::CONTRATADO);
        $candidato->expects(self::once())->method('setOnboardingProcess')->with($process);

        $onboardingRepo = $this->createMock(RhOnboardingProcessRepository::class);
        $onboardingRepo->method('findOpenByEmail')->willReturn(null);

        $onboarding = $this->createMock(RhOnboardingService::class);
        $onboarding->method('create')->willReturn($process);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $cb) => $cb(),
        );
        $em->expects(self::exactly(2))->method('flush');

        $service = new RhRecrutamentoService(
            $em,
            $this->createMock(RhVagaRepository::class),
            $this->createMock(RhCandidatoRepository::class),
            $onboardingRepo,
            $this->createMock(\App\Repository\RhAuditLogRepository::class),
            $onboarding,
            $this->createMock(RhAuditService::class),
            $this->recruitmentNotifications(),
        );

        $service->moveCandidatoEtapa($candidato, RhCandidatoEtapa::CONTRATADO, null);
    }
}
