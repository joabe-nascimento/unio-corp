<?php

namespace App\Tests\Controller\Security;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Grants HTTP — rotas protegidas redirecionam ao dashboard sem permissão.
 */
final class ProductGrantHttpTest extends WebTestCase
{
    private const GESTOR_EMAIL = 'gestor@unio.dev';
    private const SUPERVISOR_EMAIL = 'supervisor@unio.dev';
    private const PASS = 'unio123';

    public function testGestorAccessesRhFeriasAndRecrutamento(): void
    {
        $client = $this->createLoggedInClient(self::GESTOR_EMAIL);

        $client->request('GET', '/rh/ferias');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/recrutamento');
        self::assertResponseIsSuccessful();
    }

    public function testGestorDeniedAdminArea(): void
    {
        $client = $this->createLoggedInClient(self::GESTOR_EMAIL);

        $client->request('GET', '/admin');
        self::assertTrue(
            $client->getResponse()->isRedirect('/dashboard')
            || $client->getResponse()->getStatusCode() === 403,
        );
    }

    public function testSupervisorCanAccessRhFuncionariosWhenGranted(): void
    {
        $client = $this->createLoggedInClient(self::SUPERVISOR_EMAIL);

        $client->request('GET', '/rh/funcionarios');
        self::assertResponseIsSuccessful();
    }

    private function createLoggedInClient(string $email): KernelBrowser
    {
        $client = static::createClient();
        $this->skipIfUnavailable($email);

        $client->restart();
        $crawler = $client->request('GET', '/login');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => self::PASS,
            '_csrf_token' => $csrf,
        ]);

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        $client->request('GET', '/workspace');
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        return $client;
    }

    private function skipIfUnavailable(string $email): void
    {
        try {
            static::getContainer()->get('doctrine')->getConnection()->executeQuery('SELECT 1');
        } catch (\Throwable) {
            self::markTestSkipped('Banco indisponível.');
        }

        if (!static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email])) {
            self::markTestSkipped('Execute app:seed-users e app:seed-product-grants antes dos testes.');
        }
    }
}
