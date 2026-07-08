<?php

namespace App\Tests\Controller\Admin;

use App\Repository\UserRepository;
use App\Service\PlatformConfigService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ConfiguracoesControllerTest extends WebTestCase
{
    private const TENANT_EMAIL = 'tenant@unio.dev';
    private const TENANT_PASS  = 'unio123';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        try {
            static::getContainer()->get('doctrine')->getConnection()->executeQuery('SELECT 1');
        } catch (\Throwable) {
            self::markTestSkipped('Banco indisponível.');
        }

        if (!static::getContainer()->get(UserRepository::class)->findOneBy(['email' => self::TENANT_EMAIL])) {
            self::markTestSkipped('Execute app:seed-users antes dos testes.');
        }

        static::getContainer()->get(PlatformConfigService::class)->save([
            'manutencao' => false,
            'plataforma_nome' => 'Unio',
        ]);
    }

    protected function tearDown(): void
    {
        if (static::$booted ?? false) {
            static::getContainer()->get(PlatformConfigService::class)->save(['manutencao' => false]);
        }
        parent::tearDown();
    }

    public function testConfiguracoesPageLoads(): void
    {
        $this->loginAsTenant();
        $crawler = $this->client->request('GET', '/admin/configuracoes');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#cfgForm');
        self::assertSelectorExists('#cfg-plataforma');
        self::assertSelectorExists('#cfg-sistema');
        self::assertSelectorTextContains('button[type="submit"]', 'Salvar configurações');
        self::assertSelectorExists('form[action*="limpar-cache"]');
    }

    public function testSaveConfigPersistsToJson(): void
    {
        $this->loginAsTenant();
        $crawler = $this->client->request('GET', '/admin/configuracoes');
        $token   = $crawler->filter('#cfgForm input[name="_token"]')->attr('value');

        $uniqueName = 'Unio Test ' . uniqid();
        $this->client->request('POST', '/admin/configuracoes', [
            '_token'             => $token,
            'plataforma_nome'    => $uniqueName,
            'plataforma_tagline' => 'Tagline de teste',
            'cor_primaria'       => '#FF5500',
            'tema'               => 'dark',
            'suporte_email'      => 'suporte@teste.dev',
            'msg_manutencao'     => 'Mensagem de teste',
            'senha_min'          => '10',
            'sessao_timeout'     => '60',
            'registro_publico'   => '1',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/admin/configuracoes', (string) $this->client->getResponse()->headers->get('Location'));
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        static::ensureKernelShutdown();
        self::bootKernel();
        $config = static::getContainer()->get(PlatformConfigService::class);
        self::assertSame($uniqueName, $config->get('plataforma_nome'));
        self::assertSame('#FF5500', $config->get('cor_primaria'));
        self::assertSame(10, $config->getSenhaMin());
        self::assertTrue($config->isRegistroPublico());
    }

    public function testMaintenanceToggleAndClearCache(): void
    {
        $this->loginAsTenant();
        $crawler = $this->client->request('GET', '/admin/configuracoes');
        $token   = $crawler->filter('#cfgForm input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/configuracoes', [
            '_token'         => $token,
            'plataforma_nome'=> 'Unio',
            'cor_primaria'   => '#4F7FFF',
            'tema'           => 'dark',
            'manutencao'     => '1',
            'msg_manutencao' => 'Em manutenção — teste',
            'senha_min'      => '8',
            'sessao_timeout' => '120',
        ]);
        self::assertResponseRedirects();

        static::ensureKernelShutdown();
        self::bootKernel();
        self::assertTrue(static::getContainer()->get(PlatformConfigService::class)->isMaintenanceMode());

        // Com manutenção ativa, /login fica bloqueado para usuários sem sessão
        $this->client->restart(); // limpa sessão do tenant para simular visitante anônimo
        $this->client->request('GET', '/login');
        self::assertResponseRedirects('/manutencao');

        // Desativar manutenção diretamente via serviço (simula scripts/server-maintenance.sh off)
        static::getContainer()->get(PlatformConfigService::class)->save(['manutencao' => false]);
        static::ensureKernelShutdown();
        self::bootKernel();
        $config = static::getContainer()->get(PlatformConfigService::class);
        self::assertFalse($config->isMaintenanceMode());

        // Limpar cache
        $this->loginAsTenant();
        $crawler = $this->client->request('GET', '/admin/configuracoes');
        $cacheToken = $crawler->filter('form[action*="limpar-cache"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/configuracoes/limpar-cache', [
            '_token' => $cacheToken,
        ]);
        self::assertResponseRedirects();

        // cache:clear apaga sessões em dev — autenticar de novo
        $this->loginAsTenant();
        $this->client->request('GET', '/admin/configuracoes');
        self::assertResponseIsSuccessful();
    }

    public function testLogoFileUpload(): void
    {
        $this->loginAsTenant();
        $crawler = $this->client->request('GET', '/admin/configuracoes');
        $token   = $crawler->filter('#cfgForm input[name="_token"]')->attr('value');

        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $fixture    = $projectDir . '/assets/unio-logotipo.png';
        if (!is_file($fixture)) {
            self::markTestSkipped('Fixture de logo não encontrado.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'logo');
        copy($fixture, $tmp);
        $upload = new UploadedFile($tmp, 'logo-test.png', 'image/png', null, true);

        $this->client->request('POST', '/admin/configuracoes', [
            '_token'          => $token,
            'plataforma_nome' => 'Unio',
            'cor_primaria'    => '#4F7FFF',
            'tema'            => 'dark',
            'senha_min'       => '8',
            'sessao_timeout'  => '120',
        ], [
            'logo_file' => $upload,
        ]);

        self::assertResponseRedirects();
        @unlink($tmp);

        $logoUrl = static::getContainer()->get(PlatformConfigService::class)->get('logo_url');
        self::assertStringStartsWith('/uploads/config/', $logoUrl);
        self::assertFileExists($projectDir . '/public' . $logoUrl);
    }

    public function testImageUrlsPreservedWhenFormLeavesThemEmpty(): void
    {
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $uploadDir  = $projectDir . '/public/uploads/config';
        @mkdir($uploadDir, 0777, true);

        $logoPath = $uploadDir . '/existing-logo-test.png';
        copy($projectDir . '/assets/unio-logotipo.png', $logoPath);

        static::getContainer()->get(PlatformConfigService::class)->save([
            'logo_url' => '/uploads/config/existing-logo-test.png',
        ]);

        $this->loginAsTenant();
        $crawler = $this->client->request('GET', '/admin/configuracoes');
        $token   = $crawler->filter('#cfgForm input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/configuracoes', [
            '_token'          => $token,
            'plataforma_nome' => 'Unio Preservado',
            'logo_url'        => '',
            'cor_primaria'    => '#4F7FFF',
            'tema'            => 'dark',
            'senha_min'       => '8',
            'sessao_timeout'  => '120',
        ]);

        self::assertResponseRedirects();

        static::ensureKernelShutdown();
        self::bootKernel();
        $config = static::getContainer()->get(PlatformConfigService::class);
        self::assertSame('/uploads/config/existing-logo-test.png', $config->get('logo_url'));
        self::assertSame('Unio Preservado', $config->get('plataforma_nome'));
    }

    public function testLogoUploadNotClearedByClearFlag(): void
    {
        $this->loginAsTenant();
        $crawler = $this->client->request('GET', '/admin/configuracoes');
        $token   = $crawler->filter('#cfgForm input[name="_token"]')->attr('value');

        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $fixture    = $projectDir . '/assets/unio-logotipo.png';
        if (!is_file($fixture)) {
            self::markTestSkipped('Fixture de logo não encontrado.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'logo');
        copy($fixture, $tmp);
        $upload = new UploadedFile($tmp, 'logo-test.png', 'image/png', null, true);

        $this->client->request('POST', '/admin/configuracoes', [
            '_token'          => $token,
            'plataforma_nome' => 'Unio',
            'logo_url'        => '',
            'logo_url_clear'  => '1',
            'cor_primaria'    => '#4F7FFF',
            'tema'            => 'dark',
            'senha_min'       => '8',
            'sessao_timeout'  => '120',
        ], [
            'logo_file' => $upload,
        ]);

        self::assertResponseRedirects();
        @unlink($tmp);

        $logoUrl = static::getContainer()->get(PlatformConfigService::class)->get('logo_url');
        self::assertStringStartsWith('/uploads/config/', $logoUrl);
    }

    public function testInvalidCsrfRejected(): void
    {
        $this->loginAsTenant();
        $this->client->request('POST', '/admin/configuracoes', [
            '_token'          => 'invalid',
            'plataforma_nome' => 'Hack',
        ]);
        self::assertResponseRedirects('/admin/configuracoes');
    }

    private function loginAsTenant(): void
    {
        $this->client->restart();
        $crawler = $this->client->request('GET', '/login');
        $csrf    = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/login', [
            'email'         => self::TENANT_EMAIL,
            'password'      => self::TENANT_PASS,
            '_csrf_token'   => $csrf,
        ]);

        if ($this->client->getResponse()->isRedirect()) {
            $this->client->followRedirect();
        }
    }
}
