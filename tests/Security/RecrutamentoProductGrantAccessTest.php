<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Entity\UserProductGrant;
use App\Repository\UserProductGrantRepository;
use App\Security\ProductGrantAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Matriz de permissões do Núcleo Recrutamento (hub_recrutamento + fallback product_rh/recrutamento).
 */
class RecrutamentoProductGrantAccessTest extends KernelTestCase
{
    private ProductGrantAccess $grants;

    private EntityManagerInterface $em;

    private UserPasswordHasherInterface $hasher;

    private UserProductGrantRepository $grantRepo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->grants = $container->get(ProductGrantAccess::class);
        $this->em = $container->get(EntityManagerInterface::class);
        $this->hasher = $container->get(UserPasswordHasherInterface::class);
        $this->grantRepo = $container->get(UserProductGrantRepository::class);

        try {
            $this->em->getConnection()->executeQuery('SELECT 1');
        } catch (\Throwable) {
            self::markTestSkipped('Banco indisponível — configure .env.test.local ou .env.local.');
        }
    }

    public function testGestorHubAcessaHubAnalyticsVagasEPipeline(): void
    {
        $user = $this->createUserWithGrants([
            ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'perfil' => 'GESTOR'],
            ['scope' => 'hub_recrutamento', 'product' => 'pipeline', 'perfil' => 'GESTOR'],
        ]);
        $this->loginAs($user);

        foreach ([
            'app_recrutamento',
            'app_recrutamento_analytics',
            'app_recrutamento_vagas',
            'app_recrutamento_pipeline',
            'app_recrutamento_integracoes',
        ] as $route) {
            self::assertTrue($this->grants->isRouteAllowed($user, $route), "Gestor hub deve acessar {$route}");
        }
    }

    public function testGestorHubGerenciaRotasDeEscrita(): void
    {
        $user = $this->createUserWithGrants([
            ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'perfil' => 'GESTOR'],
            ['scope' => 'hub_recrutamento', 'product' => 'pipeline', 'perfil' => 'GESTOR'],
        ]);
        $this->loginAs($user);

        foreach ([
            'app_recrutamento_vaga_status',
            'app_recrutamento_candidato_etapa',
            'app_recrutamento_vaga_publicar',
            'app_recrutamento_candidato_entrevista',
            'app_recrutamento_aprovacao_decidir',
        ] as $route) {
            self::assertTrue($this->grants->canManageRoute($user, $route), "Gestor hub deve gerenciar {$route}");
        }
    }

    public function testMembroSemGrantNaoAcessaRecrutamento(): void
    {
        $user = $this->createUserWithGrants([]);
        $this->loginAs($user);

        self::assertFalse($this->grants->isRouteAllowed($user, 'app_recrutamento'));
        self::assertFalse($this->grants->isRouteAllowed($user, 'app_recrutamento_pipeline'));
    }

    public function testSupervisorPipelineVeMasNaoGerenciaEtapa(): void
    {
        $user = $this->createUserWithGrants([
            ['scope' => 'hub_recrutamento', 'product' => 'pipeline', 'perfil' => 'SUPERVISOR_EQUIPE'],
        ]);
        $this->loginAs($user);

        self::assertTrue($this->grants->isRouteAllowed($user, 'app_recrutamento_pipeline'));
        self::assertFalse($this->grants->canManageRoute($user, 'app_recrutamento_candidato_etapa'));
        self::assertFalse($this->grants->canManageRoute($user, 'app_recrutamento_candidato_entrevista'));
    }

    public function testGestorEquipeVagasGerenciaVagasMasNaoPipelineEscrita(): void
    {
        $user = $this->createUserWithGrants([
            ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'perfil' => 'GESTOR_EQUIPE'],
            ['scope' => 'hub_recrutamento', 'product' => 'pipeline', 'perfil' => 'SUPERVISOR'],
        ]);
        $this->loginAs($user);

        self::assertTrue($this->grants->canManageRoute($user, 'app_recrutamento_vaga_status'));
        self::assertTrue($this->grants->canManageRoute($user, 'app_recrutamento_vaga_publicar'));
        self::assertFalse($this->grants->canManageRoute($user, 'app_recrutamento_candidato_entrevista'));
    }

    public function testSomentePipelineGrantAcessaHubMasNaoGerenciaVagas(): void
    {
        $user = $this->createUserWithGrants([
            ['scope' => 'hub_recrutamento', 'product' => 'pipeline', 'perfil' => 'GESTOR_EQUIPE'],
        ]);
        $this->loginAs($user);

        self::assertTrue($this->grants->isRouteAllowed($user, 'app_recrutamento'));
        self::assertTrue($this->grants->isRouteAllowed($user, 'app_recrutamento_pipeline'));
        self::assertTrue($this->grants->isRouteAllowed($user, 'app_recrutamento_analytics'));
        self::assertTrue($this->grants->canManageRoute($user, 'app_recrutamento_candidato_etapa'));
        self::assertFalse($this->grants->canManageRoute($user, 'app_recrutamento_vaga_publicar'));
    }

    public function testSomenteVagasGrantAcessaHubMasNaoGerenciaPipeline(): void
    {
        $user = $this->createUserWithGrants([
            ['scope' => 'hub_recrutamento', 'product' => 'vagas', 'perfil' => 'GESTOR_EQUIPE'],
        ]);
        $this->loginAs($user);

        self::assertTrue($this->grants->isRouteAllowed($user, 'app_recrutamento_vagas'));
        self::assertTrue($this->grants->canManageRoute($user, 'app_recrutamento_vaga_despublicar'));
        self::assertFalse($this->grants->canManageRoute($user, 'app_recrutamento_candidato_scorecard'));
    }

    public function testFallbackProductRhRecrutamentoAcessaHub(): void
    {
        $user = $this->createUserWithGrants([
            ['scope' => 'product_rh', 'product' => 'recrutamento', 'perfil' => 'GESTOR'],
        ]);
        $this->loginAs($user);

        self::assertTrue($this->grants->isRouteAllowed($user, 'app_recrutamento'));
        self::assertTrue($this->grants->isRouteAllowed($user, 'app_recrutamento_analytics'));
        self::assertTrue($this->grants->isRouteAllowed($user, 'app_recrutamento_carreiras'));
        self::assertTrue($this->grants->canManageRoute($user, 'app_recrutamento_talento_inscrever'));
    }

    /**
     * @param list<array{scope: string, product: string, perfil: string}> $grants
     */
    private function createUserWithGrants(array $grants): User
    {
        $user = (new User())
            ->setNome('Teste Recrutamento')
            ->setEmail('recrutamento-grant-test-' . uniqid('', true) . '@unio.dev')
            ->setPerfil('MEMBRO');
        $user->setPassword($this->hasher->hashPassword($user, 'test'));

        $this->em->persist($user);

        foreach ($grants as $grant) {
            $this->em->persist(
                (new UserProductGrant())
                    ->setUser($user)
                    ->setScope($grant['scope'])
                    ->setProductId($grant['product'])
                    ->setPerfilGrant($grant['perfil']),
            );
        }

        $this->em->flush();
        $this->grantRepo->ensureConfiguredMarker($user);

        return $user;
    }

    private function loginAs(User $user): void
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        static::getContainer()->get('security.token_storage')->setToken($token);
    }
}
