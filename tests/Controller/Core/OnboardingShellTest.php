<?php

namespace App\Tests\Controller\Core;

use App\Dev\DevSeedEmails;

use App\Repository\UserRepository;
use App\Service\Organismo\OrganismoFeature;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OnboardingShellTest extends WebTestCase
{
    private const GESTOR_EMAIL = DevSeedEmails::RENATA;
    private const GESTOR_PASS = 'unio123';

    public function testDashboardExposesTourConfigAndChecklist(): void
    {
        $client = $this->createLoggedInClient();
        $crawler = $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#shellTourConfig');
        self::assertSelectorExists('[data-onboarding-checklist]');
        self::assertSelectorExists('[data-shell-help]');
        self::assertSelectorExists('#shellHelpBtn');
        self::assertSelectorExists('[data-shell-tour-start]');

        $json = $crawler->filter('#shellTourConfig')->text();
        $config = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        self::assertTrue($config['enabled']);
        self::assertArrayHasKey('checklist', $config);
        self::assertArrayHasKey('flows', $config);
        self::assertArrayNotHasKey('auto_start', $config);
        self::assertNotEmpty($config['steps']);
        self::assertNotEmpty($config['flows']);
    }

    public function testTourCompleteMarksShellTourStep(): void
    {
        $client = $this->createLoggedInClient();
        $crawler = $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('#shellTourMount')->attr('data-csrf-token');
        self::assertNotEmpty($token);

        $client->request('POST', '/onboarding/tour-complete', ['_token' => $token]);

        self::assertResponseIsSuccessful();
        /** @var array{ok: bool, shell_tour_done: bool} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertTrue($payload['shell_tour_done']);
    }

    public function testTourCompleteRejectsInvalidCsrf(): void
    {
        $client = $this->createLoggedInClient();
        $client->request('POST', '/onboarding/tour-complete', ['_token' => 'invalid']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testTourCompletePersistsAcrossNewSession(): void
    {
        $client = $this->createLoggedInClient();
        $crawler = $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('#shellTourMount')->attr('data-csrf-token');
        $client->request('POST', '/onboarding/tour-complete', ['_token' => $token]);
        self::assertResponseIsSuccessful();

        $client->restart();
        $crawler = $this->loginClient($client);
        $crawler = $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        $config = json_decode($crawler->filter('#shellTourConfig')->text(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($config['checklist']['shell_tour_done']);

        $shellTourStep = $crawler->filter('[data-onboarding-step="shell_tour"]');
        if ($shellTourStep->count() > 0) {
            self::assertStringContainsString('is-done', (string) $shellTourStep->attr('class'));
        }
    }

    private function createLoggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $this->skipIfUnavailable();
        $this->loginClient($client);

        return $client;
    }

    private function loginClient(KernelBrowser $client): \Symfony\Component\DomCrawler\Crawler
    {
        $client->restart();
        $crawler = $client->request('GET', '/login');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', [
            'email' => self::GESTOR_EMAIL,
            'password' => self::GESTOR_PASS,
            '_csrf_token' => $csrf,
        ]);

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        return $crawler;
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

        if (static::getContainer()->get(OrganismoFeature::class)->isEnabled()) {
            self::markTestSkipped('Tour do shell legado não se aplica com Organismo habilitado.');
        }
    }
}
