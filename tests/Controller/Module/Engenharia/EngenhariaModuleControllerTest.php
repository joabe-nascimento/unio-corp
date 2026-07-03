<?php

namespace App\Tests\Controller\Module\Engenharia;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EngenhariaModuleControllerTest extends WebTestCase
{
    private const GESTOR_EMAIL = 'gestor@unio.dev';
    private const PASS = 'unio123';

    public function testEngenhariaHubLoadsForGestor(): void
    {
        $client = $this->createLoggedInClient();

        $client->request('GET', '/engenharia');
        self::assertResponseIsSuccessful();
    }

    public function testStubSubRouteRedirectsToHub(): void
    {
        $client = $this->createLoggedInClient();

        $client->request('GET', '/engenharia/projetos');
        self::assertResponseRedirects('/engenharia');
    }

    private function createLoggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $this->skipIfUnavailable();

        $client->restart();
        $crawler = $client->request('GET', '/login');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', [
            'email' => self::GESTOR_EMAIL,
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

    private function skipIfUnavailable(): void
    {
        try {
            static::getContainer()->get('doctrine')->getConnection()->executeQuery('SELECT 1');
        } catch (\Throwable) {
            self::markTestSkipped('Banco indisponível.');
        }

        if (!static::getContainer()->get(UserRepository::class)->findOneBy(['email' => self::GESTOR_EMAIL])) {
            self::markTestSkipped('Execute app:seed-users antes dos testes.');
        }
    }
}
