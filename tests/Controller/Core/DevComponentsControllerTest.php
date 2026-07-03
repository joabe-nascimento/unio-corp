<?php

namespace App\Tests\Controller\Core;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DevComponentsControllerTest extends WebTestCase
{
    private const GESTOR_EMAIL = 'gestor@unio.dev';
    private const GESTOR_PASS = 'unio123';

    public function testGestorCanAccessComponentsGuide(): void
    {
        $client = $this->createLoggedInClient(self::GESTOR_EMAIL, self::GESTOR_PASS);
        $client->request('GET', '/dev/components');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Guia visual de componentes');
        self::assertSelectorTextContains('body', 'Badges e status');
        self::assertSelectorExists('[data-shell-tour-restart]');
    }

    public function testAnonymousUserIsDenied(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dev/components');

        self::assertResponseStatusCodeSame(403);
    }

    private function createLoggedInClient(string $email, string $password): KernelBrowser
    {
        $client = static::createClient();
        $this->skipIfUnavailable($email);

        $client->restart();
        $crawler = $client->request('GET', '/login');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => $password,
            '_csrf_token' => $csrf,
        ]);

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
            self::markTestSkipped('Execute app:seed-users antes dos testes.');
        }
    }
}
