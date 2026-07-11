<?php

namespace App\Tests\Controller\Pessoas;

use App\Dev\DevSeedEmails;

use App\Repository\PessoasCargoRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PessoasCargosControllerTest extends WebTestCase
{
    private const GESTOR_EMAIL = DevSeedEmails::RENATA;
    private const GESTOR_PASS  = 'unio123';

    public function testCargosPageLoadsWithOffcanvasForm(): void
    {
        $client = static::createClient();
        $this->skipIfUnavailable($client);

        $this->loginAsGestor($client);
        $crawler = $client->request('GET', '/pessoas/cargos');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-unio-offcanvas="pessoas-cargo-novo"]');
        self::assertSelectorExists('#pessoasCargoNovoForm');
        self::assertSelectorExists('form#pessoasCargoNovoForm input[name="titulo"]');
    }

    public function testCreateCargoPersistsAndRedirects(): void
    {
        $client = static::createClient();
        $this->skipIfUnavailable($client);

        $this->loginAsGestor($client);
        $crawler = $client->request('GET', '/pessoas/cargos');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('#pessoasCargoNovoForm input[name="_token"]')->attr('value');
        $titulo = 'Cargo Teste ' . uniqid();

        $client->request('POST', '/pessoas/cargos/novo', [
            '_token' => $token,
            'titulo' => $titulo,
            'descricao' => 'Descrição de teste automatizado',
            'area' => 'Tecnologia',
            'nivel' => 'PLENO',
        ]);

        self::assertResponseRedirects('/pessoas/cargos');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.pessoas-cargo-grid, .table-unio', $titulo);

        static::ensureKernelShutdown();
        self::bootKernel();
        $repo = static::getContainer()->get(PessoasCargoRepository::class);
        $cargo = $repo->findOneBy(['titulo' => $titulo]);
        self::assertNotNull($cargo);
        self::assertSame('Tecnologia', $cargo->getArea());
        self::assertSame('PLENO', $cargo->getNivel());
    }

    private function skipIfUnavailable(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
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

    private function loginAsGestor(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
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
    }
}
