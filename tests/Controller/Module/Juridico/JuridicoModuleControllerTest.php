<?php

namespace App\Tests\Controller\Module\Juridico;

use App\Dev\DevSeedEmails;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class JuridicoModuleControllerTest extends WebTestCase
{
    private const GESTOR_EMAIL = DevSeedEmails::RENATA;
    private const PASS = 'unio123';

    public function testHubLoadsForGestor(): void
    {
        $client = $this->createLoggedInClient();

        $client->request('GET', '/juridico');
        self::assertResponseIsSuccessful();
    }

    public function testProcessosListLoads(): void
    {
        $client = $this->createLoggedInClient();

        $client->request('GET', '/juridico/processos');
        self::assertResponseIsSuccessful();
    }

    public function testPrazosListLoads(): void
    {
        $client = $this->createLoggedInClient();

        $client->request('GET', '/juridico/prazos');
        self::assertResponseIsSuccessful();
    }

    public function testClientesListLoads(): void
    {
        $client = $this->createLoggedInClient();

        $client->request('GET', '/juridico/clientes');
        self::assertResponseIsSuccessful();
    }

    public function testDocumentosListLoads(): void
    {
        $client = $this->createLoggedInClient();

        $client->request('GET', '/juridico/documentos');
        self::assertResponseIsSuccessful();
    }

    public function testHonorariosListLoads(): void
    {
        $client = $this->createLoggedInClient();

        $client->request('GET', '/juridico/honorarios');
        self::assertResponseIsSuccessful();
    }

    public function testJurisprudenciaListLoads(): void
    {
        $client = $this->createLoggedInClient();

        $client->request('GET', '/juridico/jurisprudencia');
        self::assertResponseIsSuccessful();
    }

    public function testCrudLifecycleForCliente(): void
    {
        $client = $this->createLoggedInClient();

        $crawler = $client->request('GET', '/juridico/clientes');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action$="/juridico/clientes/novo"] input[name="_token"]')->attr('value');

        $client->request('POST', '/juridico/clientes/novo', [
            'nome' => 'Cliente Teste PHPUnit',
            'tipo' => 'PJ',
            'status' => 'standard',
            '_token' => $token,
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Cliente Teste PHPUnit');
    }

    public function testCrudLifecycleForProcesso(): void
    {
        $client = $this->createLoggedInClient();

        $crawler = $client->request('GET', '/juridico/processos');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action$="/juridico/processos/novo"] input[name="_token"]')->attr('value');

        $client->request('POST', '/juridico/processos/novo', [
            'numero' => '0001234-56.2026.8.26.0100',
            'fase' => 'conhecimento',
            'status' => 'ativo',
            '_token' => $token,
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testCrudLifecycleForPrazo(): void
    {
        $client = $this->createLoggedInClient();

        $crawler = $client->request('GET', '/juridico/prazos');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action$="/juridico/prazos/novo"] input[name="_token"]')->attr('value');

        $client->request('POST', '/juridico/prazos/novo', [
            'tipo' => 'Contestação',
            'data_limite' => (new \DateTimeImmutable('+10 days'))->format('Y-m-d'),
            '_token' => $token,
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testCrudLifecycleForJurisprudencia(): void
    {
        $client = $this->createLoggedInClient();

        $crawler = $client->request('GET', '/juridico/jurisprudencia');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action$="/juridico/jurisprudencia/novo"] input[name="_token"]')->attr('value');

        $client->request('POST', '/juridico/jurisprudencia/novo', [
            'tribunal' => 'STJ',
            'tema' => 'Dano moral em relação de consumo',
            'relevancia' => 'alta',
            '_token' => $token,
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testCrudLifecycleForHonorario(): void
    {
        $client = $this->createLoggedInClient();

        $crawler = $client->request('GET', '/juridico/honorarios');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/juridico/honorarios/novo"]');
        $token = $form->filter('input[name="_token"]')->attr('value');
        $advogadoId = $form->filter('select[name="advogado_id"] option')->last()->attr('value');
        self::assertNotSame('', $advogadoId, 'Esperava ao menos um advogado disponível para o lançamento.');

        $client->request('POST', '/juridico/honorarios/novo', [
            'advogado_id' => $advogadoId,
            'data' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'horas' => '2.5',
            'valor_hora' => '350.00',
            '_token' => $token,
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
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
