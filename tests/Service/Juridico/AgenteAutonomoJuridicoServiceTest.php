<?php

declare(strict_types=1);

namespace App\Tests\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoPrazo;
use App\Entity\JuridicoProcesso;
use App\Entity\PlatformNotificacao;
use App\Entity\User;
use App\Repository\EmpresaRepository;
use App\Repository\JuridicoPrazoRepository;
use App\Repository\PlatformNotificacaoRepository;
use App\Repository\UserRepository;
use App\Service\Juridico\AgenteAutonomoJuridicoService;
use App\Service\Juridico\AgenteAutonomoStatusStore;
use App\Service\Juridico\JuridicoRiscoAlertaService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\PlatformNotificationPresenter;
use App\Service\PlatformNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AgenteAutonomoJuridicoServiceTest extends TestCase
{
    private string $projectDir;

    /** @var list<PlatformNotificacao> */
    private array $notificacoesPersistidas = [];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/agente_autonomo_service_test_' . uniqid('', true);
        mkdir($this->projectDir, 0755, true);
        $this->notificacoesPersistidas = [];
    }

    protected function tearDown(): void
    {
        $file = $this->projectDir . '/var/data/agente_autonomo_status.json';
        if (is_file($file)) {
            unlink($file);
        }
        @rmdir($this->projectDir . '/var/data');
        @rmdir($this->projectDir . '/var');
        @rmdir($this->projectDir);
    }

    public function testDesligadoQuandoPerfilNaoEhJuridico(): void
    {
        $service = $this->buildService($this->organismoCopy(juridico: false), []);
        $resultado = $service->executar();

        self::assertFalse($resultado['executado']);
        self::assertSame(0, $resultado['alertas_gerados']);
        self::assertSame([], $this->notificacoesPersistidas);
    }

    public function testGeraAlertaParaPrazoCriticoENotificaFallbackDeUsuarios(): void
    {
        $empresa = $this->criarEmpresa(1, 'Escritório Teste');
        $membro = $this->criarUsuario(9, 'ana@escritorio.dev');

        $prazo = new JuridicoPrazo();
        $prazo->setEmpresa($empresa);
        $prazo->setTipo('Contestação');
        $prazo->setDataLimite((new \DateTimeImmutable('today'))->modify('+1 day'));
        (new \ReflectionProperty(JuridicoPrazo::class, 'id'))->setValue($prazo, 10);

        $prazoRepo = $this->createMock(JuridicoPrazoRepository::class);
        $prazoRepo->method('findForEmpresa')->with($empresa, 'pendentes')->willReturn([$prazo]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findBy')->with(['empresa' => $empresa])->willReturn([$membro]);

        $service = $this->buildService($this->organismoCopy(juridico: true), [$empresa], prazoRepo: $prazoRepo, userRepo: $userRepo);
        $resultado = $service->executar();

        self::assertSame(1, $resultado['alertas_gerados']);
        self::assertCount(1, $this->notificacoesPersistidas);

        $n = $this->notificacoesPersistidas[0];
        self::assertSame($empresa, $n->getEmpresa());
        self::assertSame($membro, $n->getUser());
        self::assertSame('juridico_agente', $n->getModulo());
        self::assertSame('prazo_critico', $n->getTipo());
        self::assertStringContainsString('Contestação', $n->getMensagem());
        self::assertSame('app_juridico_prazos', $n->getRouteName());
        self::assertSame('warning', $n->getSeveridade());
    }

    public function testNaoRepeteAlertaJaEnviadoDentroDaJanelaDeDedup(): void
    {
        $empresa = $this->criarEmpresa(2, 'Escritório Dedup');
        $membro = $this->criarUsuario(7, 'bruno@escritorio.dev');

        $prazo = new JuridicoPrazo();
        $prazo->setEmpresa($empresa);
        $prazo->setTipo('Recurso');
        $prazo->setDataLimite((new \DateTimeImmutable('today'))->modify('-1 day'));
        (new \ReflectionProperty(JuridicoPrazo::class, 'id'))->setValue($prazo, 55);

        $prazoRepo = $this->createMock(JuridicoPrazoRepository::class);
        $prazoRepo->method('findForEmpresa')->willReturn([$prazo]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findBy')->willReturn([$membro]);

        $service = $this->buildService($this->organismoCopy(juridico: true), [$empresa], prazoRepo: $prazoRepo, userRepo: $userRepo);

        $primeira = $service->executar();
        $segunda = $service->executar();

        self::assertSame(1, $primeira['alertas_gerados']);
        self::assertSame(0, $segunda['alertas_gerados']);
        self::assertCount(1, $this->notificacoesPersistidas);
        self::assertSame('prazo_atrasado', $this->notificacoesPersistidas[0]->getTipo());
    }

    public function testGeraAlertaParaProcessoCriticoComResponsavelDefinido(): void
    {
        $empresa = $this->criarEmpresa(3, 'Escritório Processo');
        $advogado = $this->criarUsuario(4, 'carla@escritorio.dev');

        $processo = new JuridicoProcesso();
        $processo->setEmpresa($empresa);
        $processo->setNumero('1234567-89.2024.8.26.0100');
        $processo->setResponsavel($advogado);
        (new \ReflectionProperty(JuridicoProcesso::class, 'id'))->setValue($processo, 77);

        $prazoRepo = $this->createMock(JuridicoPrazoRepository::class);
        $prazoRepo->method('findForEmpresa')->willReturn([]);

        $risco = $this->createMock(JuridicoRiscoAlertaService::class);
        $risco->method('gerarAlertas')->willReturn([[
            'processo' => $processo,
            'tipo' => 'critico',
            'nivel' => 'alto',
            'icone' => 'fa-triangle-exclamation',
            'mensagem' => 'Processo marcado como crítico — priorize a análise.',
        ]]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects(self::never())->method('findBy');

        $service = $this->buildService($this->organismoCopy(juridico: true), [$empresa], prazoRepo: $prazoRepo, userRepo: $userRepo, risco: $risco);
        $resultado = $service->executar();

        self::assertSame(1, $resultado['alertas_gerados']);
        self::assertCount(1, $this->notificacoesPersistidas);

        $n = $this->notificacoesPersistidas[0];
        self::assertSame($advogado, $n->getUser());
        self::assertSame('critico', $n->getTipo());
        self::assertSame('app_juridico_processo_show', $n->getRouteName());
        self::assertSame(['id' => 77], $n->getRouteParams());
        self::assertSame('danger', $n->getSeveridade());
    }

    /** OrganismoCopyService é `final` — não dá para mockar, então construímos a real. */
    private function organismoCopy(bool $juridico): OrganismoCopyService
    {
        $brand = $juridico ? 'Unio Jurídico' : 'Unio Studio';
        $unit = $juridico ? 'Escritório' : 'Unidade';

        return new OrganismoCopyService(
            $brand, '', '', '', '', '', $unit, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
        );
    }

    /** PlatformNotificationService também é `final`: usamos a real, capturando o que é persistido. */
    private function notificationService(): PlatformNotificationService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $n): void {
            if ($n instanceof PlatformNotificacao) {
                $this->notificacoesPersistidas[] = $n;
            }
        });
        $em->method('flush');

        return new PlatformNotificationService(
            $em,
            $this->createMock(PlatformNotificacaoRepository::class),
            new PlatformNotificationPresenter(),
        );
    }

    /** @param list<Empresa> $empresas */
    private function buildService(
        OrganismoCopyService $organismo,
        array $empresas,
        ?JuridicoPrazoRepository $prazoRepo = null,
        ?UserRepository $userRepo = null,
        ?JuridicoRiscoAlertaService $risco = null,
    ): AgenteAutonomoJuridicoService {
        $empresaRepo = $this->createMock(EmpresaRepository::class);
        $empresaRepo->method('findBy')->willReturn($empresas);

        $riscoVazio = $this->createMock(JuridicoRiscoAlertaService::class);
        $riscoVazio->method('gerarAlertas')->willReturn([]);

        return new AgenteAutonomoJuridicoService(
            $organismo,
            $empresaRepo,
            $prazoRepo ?? $this->createMock(JuridicoPrazoRepository::class),
            $risco ?? $riscoVazio,
            $userRepo ?? $this->createMock(UserRepository::class),
            $this->notificationService(),
            new AgenteAutonomoStatusStore($this->projectDir),
        );
    }

    private function criarEmpresa(int $id, string $nome): Empresa
    {
        $empresa = new Empresa();
        $empresa->setNome($nome);
        (new \ReflectionProperty(Empresa::class, 'id'))->setValue($empresa, $id);

        return $empresa;
    }

    private function criarUsuario(int $id, string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setNome('Usuário ' . $id);
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
