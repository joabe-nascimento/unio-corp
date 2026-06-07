<?php

namespace App\Tests\Service;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhOnboardingProcess;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\UserRepository;
use App\Service\PlatformConfigService;
use App\Service\RhUserProvisioningService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RhUserProvisioningServiceTest extends TestCase
{
    private EntityManagerInterface $em;

    private UserRepository $userRepo;

    private FuncionarioRepository $funcionarioRepo;

    private UserPasswordHasherInterface $passwordHasher;

    private PlatformConfigService $platformConfig;

    private RhUserProvisioningService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->funcionarioRepo = $this->createMock(FuncionarioRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->platformConfig = $this->createMock(PlatformConfigService::class);

        $this->service = new RhUserProvisioningService(
            $this->em,
            $this->userRepo,
            $this->funcionarioRepo,
            $this->passwordHasher,
            $this->platformConfig,
        );
    }

    public function testResolvePlatformAccountStateCreateWhenNoUser(): void
    {
        $process = $this->makeProcess('novo@example.com');
        $process->setEmpresa(new Empresa());

        $this->userRepo->method('findOneBy')->willReturn(null);

        $state = $this->service->resolvePlatformAccountState($process);

        self::assertSame('create', $state['state']);
        self::assertNull($state['user']);
    }

    public function testResolvePlatformAccountStateLinkWhenUserWithoutEmpresa(): void
    {
        $empresa = new Empresa();
        $process = $this->makeProcess('self@example.com');
        $process->setEmpresa($empresa);

        $user = (new User())->setEmail('self@example.com')->setPerfil('MEMBRO');

        $this->userRepo->method('findOneBy')->willReturn($user);
        $this->funcionarioRepo->method('findOneByUser')->willReturn(null);

        $state = $this->service->resolvePlatformAccountState($process);

        self::assertSame('link', $state['state']);
        self::assertSame($user, $state['user']);
    }

    public function testResolvePlatformAccountStateBlockedForTenant(): void
    {
        $process = $this->makeProcess('tenant@example.com');
        $process->setEmpresa(new Empresa());
        $user = (new User())
            ->setEmail('tenant@example.com')
            ->setPerfil('TENANT')
            ->setRoles([User::ROLE_TENANT]);

        $this->userRepo->method('findOneBy')->willReturn($user);

        $state = $this->service->resolvePlatformAccountState($process);

        self::assertSame('blocked', $state['state']);
        self::assertStringContainsString('tenant', strtolower((string) $state['reason']));
    }

    public function testLinkExistingUserSetsEmpresaAndMarksChecklist(): void
    {
        $empresa = new Empresa();
        $this->setEntityId($empresa, 10);

        $process = $this->makeProcess('self@example.com');
        $process->setEmpresa($empresa);

        $user = (new User())
            ->setEmail('self@example.com')
            ->setNome('Self Register')
            ->setPerfil('MEMBRO')
            ->setRoles([User::ROLE_MEMBRO])
            ->setAtivo(false);

        $this->funcionarioRepo->method('findOneByUser')->willReturn(null);
        $this->em->expects(self::once())->method('flush');

        $this->service->linkExistingUser($process, $user, 'MEMBRO');

        self::assertSame($empresa, $user->getEmpresa());
        self::assertTrue($user->isAtivo());
        self::assertTrue($this->isPlataformaDone($process));
    }

    public function testLinkExistingUserLinksFuncionario(): void
    {
        $empresa = new Empresa();
        $this->setEntityId($empresa, 10);

        $funcionario = (new Funcionario())
            ->setNome('Colaborador')
            ->setEmail('self@example.com')
            ->setEmpresa($empresa);

        $process = $this->makeProcess('self@example.com');
        $process->setEmpresa($empresa);
        $process->setFuncionario($funcionario);

        $user = (new User())
            ->setEmail('self@example.com')
            ->setPerfil('MEMBRO')
            ->setRoles([User::ROLE_MEMBRO]);

        $this->funcionarioRepo->method('findOneByUser')->willReturn(null);

        $this->service->linkExistingUser($process, $user);

        self::assertSame($user, $funcionario->getUser());
    }

    public function testLinkExistingUserRejectsOtherEmpresa(): void
    {
        $empresaProcesso = new Empresa();
        $this->setEntityId($empresaProcesso, 1);
        $empresaUser = new Empresa();
        $this->setEntityId($empresaUser, 2);

        $process = $this->makeProcess('user@example.com');
        $process->setEmpresa($empresaProcesso);

        $user = (new User())
            ->setEmail('user@example.com')
            ->setEmpresa($empresaUser)
            ->setPerfil('MEMBRO');

        $this->expectException(RhProcessException::class);
        $this->expectExceptionMessage('outra empresa');

        $this->service->linkExistingUser($process, $user);
    }

    public function testResolvePlatformAccountStateLinkWhenChecklistMarkedButNotLinked(): void
    {
        $empresa = new Empresa();
        $this->setEntityId($empresa, 10);

        $funcionario = (new Funcionario())
            ->setNome('Colaborador')
            ->setEmail('self@example.com')
            ->setEmpresa($empresa);

        $process = $this->makeProcess('self@example.com');
        $process->setEmpresa($empresa);
        $process->setFuncionario($funcionario);

        $checklist = RhOnboardingProcess::defaultChecklist();
        foreach ($checklist as &$item) {
            if (($item['id'] ?? '') === 'plataforma') {
                $item['done'] = true;
            }
        }
        unset($item);
        $process->setChecklist($checklist);

        $user = (new User())->setEmail('self@example.com')->setPerfil('MEMBRO');
        $this->userRepo->method('findOneBy')->willReturn($user);
        $this->funcionarioRepo->method('findOneByUser')->willReturn(null);

        $state = $this->service->resolvePlatformAccountState($process);

        self::assertSame('link', $state['state']);
    }

    public function testLinkExistingUserForFuncionarioSetsEmpresa(): void
    {
        $empresa = new Empresa();
        $this->setEntityId($empresa, 10);

        $funcionario = (new Funcionario())
            ->setNome('Colaborador')
            ->setEmail('self@example.com')
            ->setEmpresa($empresa);

        $user = (new User())
            ->setEmail('self@example.com')
            ->setPerfil('MEMBRO')
            ->setRoles([User::ROLE_MEMBRO]);

        $this->userRepo->method('findOneBy')->willReturn($user);
        $this->funcionarioRepo->method('findOneByUser')->willReturn(null);
        $this->em->expects(self::once())->method('flush');

        $linked = $this->service->linkExistingUserForFuncionario($funcionario, 'MEMBRO');

        self::assertSame($user, $linked);
        self::assertSame($empresa, $user->getEmpresa());
        self::assertSame($user, $funcionario->getUser());
    }

    public function testProvisionFromOnboardingSuggestsLinkWhenEmailExists(): void
    {
        $process = $this->makeProcess('exists@example.com');
        $existing = (new User())->setEmail('exists@example.com');

        $this->userRepo->method('findOneBy')->willReturn($existing);

        $this->expectException(RhProcessException::class);
        $this->expectExceptionMessage('Vincular conta existente');

        $this->service->provisionFromOnboarding($process, 'SenhaForte123!');
    }

    private function makeProcess(string $email): RhOnboardingProcess
    {
        $process = new RhOnboardingProcess();
        $process->setNome('Colaborador Teste');
        $process->setEmail($email);
        $process->setStatus(RhOnboardingProcess::STATUS_EM_ANDAMENTO);
        $process->setChecklist(RhOnboardingProcess::defaultChecklist());

        return $process;
    }

    private function isPlataformaDone(RhOnboardingProcess $process): bool
    {
        foreach ($process->getChecklist() as $item) {
            if (($item['id'] ?? '') === 'plataforma') {
                return !empty($item['done']);
            }
        }

        return false;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
