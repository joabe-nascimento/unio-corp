<?php

namespace App\Tests\Service;

use App\Clinic\ClinicStaffRole;
use App\Dev\DevSeedEmails;
use App\Repository\UserRepository;
use App\Security\ProductGrantAccess;
use App\Service\Organismo\OrganismoFeature;
use App\Service\SystemValidationService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SystemValidationServiceTest extends KernelTestCase
{
    private SystemValidationService $validation;

    private UserRepository $users;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->validation = $container->get(SystemValidationService::class);
        $this->users = $container->get(UserRepository::class);

        try {
            $container->get('doctrine')->getConnection()->executeQuery('SELECT 1');
        } catch (\Throwable) {
            self::markTestSkipped('Banco indisponível para testes de integração — configure .env.test.local ou .env.local.');
        }

        if (!$this->users->findOneBy(['email' => DevSeedEmails::CAMILA_RECEPCAO])) {
            self::markTestSkipped('Seed clínico ausente — execute app:seed-users antes dos testes de integração.');
        }
    }

    public function testValidateReturnsOk(): void
    {
        $result = $this->validation->validate();

        self::assertNotEmpty($result->reports, 'Deve gerar relatório de checagens');

        if (!$result->ok) {
            self::fail("Validação falhou:\n- " . implode("\n- ", $result->failures));
        }

        self::assertTrue($result->ok);
    }

    public function testRecepcaoCannotOpenAlertas(): void
    {
        $user = $this->users->findOneBy(['email' => DevSeedEmails::CAMILA_RECEPCAO]);
        self::assertNotNull($user);
        self::assertSame(ClinicStaffRole::RECEPCAO, $user->getPerfil());

        $grants = static::getContainer()->get(ProductGrantAccess::class);
        self::assertFalse($grants->isRouteAllowed($user, 'app_pos_operatorio_alertas'));
        self::assertTrue($grants->isRouteAllowed($user, 'app_pos_operatorio_pacientes'));
    }

    public function testCoordenacaoCanOpenConfig(): void
    {
        $user = $this->users->findOneBy(['email' => DevSeedEmails::HELENA_COORDENACAO]);
        self::assertNotNull($user);
        self::assertSame(ClinicStaffRole::COORDENACAO, $user->getPerfil());

        $grants = static::getContainer()->get(ProductGrantAccess::class);
        self::assertTrue($grants->isRouteAllowed($user, 'app_pos_operatorio_config'));
        self::assertFalse($grants->isRouteAllowed($user, 'app_pos_operatorio_pacientes'));
    }

    public function testTenantHasActiveClinic(): void
    {
        $user = $this->users->findOneBy(['email' => DevSeedEmails::JOABE]);
        if (!$user) {
            self::markTestSkipped(DevSeedEmails::JOABE . ' não encontrado no seed.');
        }

        $workspace = static::getContainer()->get(\App\Service\WorkspaceService::class);
        $organismo = static::getContainer()->get(OrganismoFeature::class);

        if ($organismo->isEnabled()) {
            self::assertGreaterThanOrEqual(1, \count($workspace->getAvailableEmpresas($user)));
        } else {
            self::assertGreaterThanOrEqual(2, \count($workspace->getAvailableEmpresas($user)));
        }
    }
}
