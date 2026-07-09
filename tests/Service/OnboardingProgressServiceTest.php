<?php



namespace App\Tests\Service;



use App\Entity\Empresa;

use App\Entity\User;

use App\Repository\UserProductGrantRepository;

use App\Security\ProductGrantAccess;

use App\Service\NavigationService;

use App\Service\OnboardingProgressService;

use App\Service\WelcomeService;

use Doctrine\ORM\EntityManagerInterface;

use PHPUnit\Framework\TestCase;

use Symfony\Bundle\SecurityBundle\Security;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\RequestStack;

use Symfony\Component\HttpFoundation\Session\Session;

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;



final class OnboardingProgressServiceTest extends TestCase

{

    public function testBuildStartsWithShellTourStep(): void
    {
        $user = $this->userWithPerfil('GESTOR');
        $empresa = (new Empresa())->setNome('Unio Demo');
        $service = $this->createService()['service'];

        $result = $service->build($user, $empresa, 1);
        $ids = array_column($result['steps'], 'id');

        self::assertContains('shell_tour', $ids);
        self::assertSame('shell_tour', $ids[0]);
    }



    public function testMarkStepCompletePersistsShellTourOnUser(): void

    {

        $user = $this->userWithPerfil('GESTOR');

        $service = $this->createService()['service'];



        self::assertFalse($service->isStepComplete('shell_tour', $user));



        $service->markStepComplete('shell_tour', $user);



        self::assertTrue($user->isOnboardingStepComplete('shell_tour'));

        self::assertTrue($service->isStepComplete('shell_tour', $user));

    }



    public function testMigratesLegacySessionStepsToUser(): void

    {

        $session = new Session(new MockArraySessionStorage());

        $session->start();

        $session->set(OnboardingProgressService::SESSION_COMPLETED, ['shell_tour', 'workspace']);

        $user = $this->userWithPerfil('GESTOR');
        $service = $this->createService($session)['service'];

        $service->build($user, null, 0);

        self::assertTrue($user->isOnboardingStepComplete('shell_tour'));
        self::assertFalse($user->isOnboardingStepComplete('workspace'));
        self::assertFalse($session->has(OnboardingProgressService::SESSION_COMPLETED));

    }



    public function testShellTourStepUsesDashboardWithoutAutoTourParam(): void

    {

        $built = $this->createService()['service']->build(

            $this->userWithPerfil('GESTOR'),

            (new Empresa())->setNome('Unio Demo'),

            1,

        );



        $shellTour = null;

        foreach ($built['steps'] as $step) {

            if ($step['id'] === 'shell_tour') {

                $shellTour = $step;

                break;

            }

        }



        self::assertNotNull($shellTour);

        self::assertSame('app_dashboard', $shellTour['route']);

        self::assertSame([], $shellTour['route_params']);

        self::assertFalse($shellTour['done']);

        self::assertStringContainsString('Ajuda', $shellTour['hint']);

    }



    /**

     * @return array{service: OnboardingProgressService, requestStack: RequestStack}

     */

    private function createService(?Session $session = null): array

    {

        $request = new Request();

        $session ??= new Session(new MockArraySessionStorage());

        if (!$session->isStarted()) {

            $session->start();

        }

        $request->setSession($session);



        $requestStack = new RequestStack();

        $requestStack->push($request);



        $navigation = $this->createMock(NavigationService::class);

        $navigation->method('showPlataforma')->willReturn(false);



        $welcome = $this->createMock(WelcomeService::class);

        $welcome->method('getHubsForUser')->willReturn([]);



        $grantRepo = $this->createMock(UserProductGrantRepository::class);

        $grantRepo->method('userHasConfiguredMatrix')->willReturn(false);

        $grantRepo->method('userHasAnyGrant')->willReturn(false);

        $grantRepo->method('findOneForUserScopeProduct')->willReturn(null);

        $grantRepo->method('findAllGrantKeysForUser')->willReturn([]);

        $security = $this->createMock(Security::class);

        $grants = new ProductGrantAccess($security, $grantRepo);



        $em = $this->createMock(EntityManagerInterface::class);

        $em->method('flush');



        return [

            'service' => new OnboardingProgressService($requestStack, $navigation, $welcome, $grants, $security, $em),

            'requestStack' => $requestStack,

        ];

    }



    private function userWithPerfil(string $perfil): User

    {

        $user = new User();

        $user->setEmail('progress-test@unio.dev');

        $user->setNome('Progress Test');

        $user->setPassword('secret');

        $user->setPerfil($perfil);



        return $user;

    }

}

