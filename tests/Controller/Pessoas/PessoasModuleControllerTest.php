<?php

namespace App\Tests\Controller\Pessoas;

use App\Dev\DevSeedEmails;

use App\Repository\DepartamentoRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\PessoasAvaliacaoRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verificação funcional do módulo Gestão de Pessoas (membros, equipes, avaliação, organograma).
 */
class PessoasModuleControllerTest extends WebTestCase
{
    private const GESTOR_EMAIL = DevSeedEmails::RENATA;
    private const GESTOR_PASS  = 'unio123';

    private static ?int $membroLiderId = null;

    private static ?int $membroSubId = null;

    private static ?int $equipeId = null;

    public function testIndexLoadsWithRealKpis(): void
    {
        $client = $this->createLoggedInClient();
        $crawler = $client->request('GET', '/pessoas');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.page-lead-kpis', 'Total de Membros');
        self::assertSelectorTextContains('body', 'Gestão de Pessoas');
    }

    public function testMembrosListAndCreateMember(): void
    {
        $client = $this->createLoggedInClient();
        $crawler = $client->request('GET', '/pessoas/membros');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/pessoas/membros"]');

        $crawler = $client->request('GET', '/pessoas/membros/novo');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.unio-form input[name="nome"]');

        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $email = 'membro-pessoas-test-' . uniqid('', true) . '@unio.dev';
        $nome = 'Membro Teste ' . uniqid();

        $client->request('POST', '/pessoas/membros/novo', [
            '_token' => $token,
            'nome' => $nome,
            'email' => $email,
            'cargo' => 'Analista de QA',
            'status' => 'ATIVO',
            'competencias' => 'PHPUnit, Symfony, Testes',
            'observacoes' => 'Membro criado por teste automatizado.',
        ]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/pessoas/membros/\d+$#', $location);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ficha-hero-name', $nome);
        self::assertSelectorTextContains('.skill-tags', 'PHPUnit');

        preg_match('#/pessoas/membros/(\d+)$#', $location, $m);
        self::$membroLiderId = (int) $m[1];

        static::ensureKernelShutdown();
        self::bootKernel();
        $repo = static::getContainer()->get(FuncionarioRepository::class);
        $func = $repo->find(self::$membroLiderId);
        self::assertNotNull($func);
        self::assertSame($email, $func->getEmail());
    }

    public function testEquipesCreateAndShowDetalhe(): void
    {
        $client = $this->createLoggedInClient();
        $crawler = $client->request('GET', '/pessoas/equipes');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/pessoas/equipes"]');

        $crawler = $client->request('GET', '/pessoas/equipes/nova');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $nomeEquipe = 'Equipe Teste ' . uniqid();

        $liderId = self::$membroLiderId;
        if ($liderId === null) {
            $liderId = $this->ensureMembroLider($client);
        }

        $client->request('POST', '/pessoas/equipes/nova', [
            '_token' => $token,
            'nome' => $nomeEquipe,
            'descricao' => 'Equipe criada em teste automatizado',
            'area' => 'Tecnologia',
            'lider_id' => (string) $liderId,
        ]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/pessoas/equipes/\d+$#', $location);

        preg_match('#/pessoas/equipes/(\d+)$#', $location, $m);
        self::$equipeId = (int) $m[1];

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ficha-hero-name', $nomeEquipe);
        self::assertSelectorTextContains('.ficha-hero-meta', 'Tecnologia');
    }

    public function testMembroSubordinadoAndOrganograma(): void
    {
        $client = $this->createLoggedInClient();
        $liderId = self::$membroLiderId ?? $this->ensureMembroLider($client);
        $equipeId = self::$equipeId;

        if ($equipeId === null) {
            self::markTestSkipped('Depende de equipe criada no teste anterior.');
        }

        $crawler = $client->request('GET', '/pessoas/membros/novo');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $email = 'sub-pessoas-test-' . uniqid('', true) . '@unio.dev';
        $nome = 'Subordinado Teste ' . uniqid();

        $client->request('POST', '/pessoas/membros/novo', [
            '_token' => $token,
            'nome' => $nome,
            'email' => $email,
            'cargo' => 'Dev Júnior',
            'status' => 'ATIVO',
            'departamento_id' => (string) $equipeId,
            'gestor_id' => (string) $liderId,
        ]);

        self::assertResponseRedirects();
        preg_match('#/pessoas/membros/(\d+)$#', (string) $client->getResponse()->headers->get('Location'), $m);
        self::$membroSubId = (int) $m[1];

        $client->request('GET', '/pessoas/organograma');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.organograma-tree');
        self::assertSelectorTextContains('.organograma-tree', $nome);

        $client->request('GET', '/pessoas/equipes/' . $equipeId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#equipe-membros-container', $nome);
    }

    public function testAvaliacaoCreateViaOffcanvas(): void
    {
        $client = $this->createLoggedInClient();
        $funcionarioId = self::$membroSubId ?? self::$membroLiderId ?? $this->ensureMembroLider($client);

        $crawler = $client->request('GET', '/pessoas/avaliacao');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-unio-offcanvas="pessoas-avaliacao-nova"]');
        self::assertSelectorExists('#pessoasAvaliacaoForm');

        $token = $crawler->filter('#pessoasAvaliacaoForm input[name="_token"]')->attr('value');
        $periodo = '2026-S1-' . uniqid();

        $client->request('POST', '/pessoas/avaliacao/nova', [
            '_token' => $token,
            'funcionario_id' => (string) $funcionarioId,
            'periodo' => $periodo,
            'nota' => '4.5',
            'comentario' => 'Desempenho acima da média no ciclo de testes.',
        ]);

        self::assertResponseRedirects('/pessoas/avaliacao');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.table-unio', $periodo);
        self::assertSelectorTextContains('table.table-unio', '4.5');

        static::ensureKernelShutdown();
        self::bootKernel();
        $avaliacoes = static::getContainer()->get(PessoasAvaliacaoRepository::class)
            ->findBy(['periodo' => $periodo]);
        self::assertCount(1, $avaliacoes);
        self::assertSame('4.5', $avaliacoes[0]->getNota());
    }

    public function testMembroEditUpdatesData(): void
    {
        $client = $this->createLoggedInClient();
        $id = self::$membroLiderId ?? $this->ensureMembroLider($client);

        $crawler = $client->request('GET', '/pessoas/membros/' . $id . '/editar');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $novoCargo = 'Tech Lead ' . uniqid();

        $client->request('POST', '/pessoas/membros/' . $id . '/editar', [
            '_token' => $token,
            'nome' => $crawler->filter('input[name="nome"]')->attr('value'),
            'email' => $crawler->filter('input[name="email"]')->attr('value'),
            'cargo' => $novoCargo,
            'status' => 'ATIVO',
        ]);

        self::assertResponseRedirects('/pessoas/membros/' . $id);
        $client->followRedirect();
        self::assertSelectorTextContains('.ficha-hero-role', $novoCargo);
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
            'password' => self::GESTOR_PASS,
            '_csrf_token' => $csrf,
        ]);

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

    private function ensureMembroLider(KernelBrowser $client): int
    {
        $crawler = $client->request('GET', '/pessoas/membros/novo');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $email = 'lider-fallback-' . uniqid('', true) . '@unio.dev';

        $client->request('POST', '/pessoas/membros/novo', [
            '_token' => $token,
            'nome' => 'Líder Fallback',
            'email' => $email,
            'status' => 'ATIVO',
        ]);

        preg_match('#/pessoas/membros/(\d+)$#', (string) $client->getResponse()->headers->get('Location'), $m);

        return (int) $m[1];
    }
}
