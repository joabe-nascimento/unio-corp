<?php

namespace App\Tests\Service;

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

        if (!$this->users->findOneBy(['email' => DevSeedEmails::RENATA])) {
            self::markTestSkipped('Seed ausente — execute app:seed-users antes dos testes de integração.');
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

    public function testMembroCannotCreateMemberRoute(): void
    {
        $user = $this->users->findOneBy(['email' => DevSeedEmails::LUCAS]);
        self::assertNotNull($user);

        $grants = static::getContainer()->get(ProductGrantAccess::class);
        self::assertFalse($grants->isRouteAllowed($user, 'app_pessoas_membro_novo'));
    }

    public function testGestorCanManagePessoasPermissions(): void
    {
        $user = $this->users->findOneBy(['email' => DevSeedEmails::RENATA]);
        self::assertNotNull($user);

        $permissions = static::getContainer()->get(\App\Service\PermissionService::class);
        self::assertTrue($permissions->canManagePermissions($user, 'product_pessoas'));
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
