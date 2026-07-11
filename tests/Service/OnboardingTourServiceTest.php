<?php



namespace App\Tests\Service;



use App\Entity\Empresa;

use App\Entity\User;

use App\Repository\UserProductGrantRepository;

use App\Security\ProductGrantAccess;
use App\Service\Organismo\OrganismoFeature;

use App\Service\NavigationService;

use App\Service\OnboardingProgressService;

use App\Service\OnboardingTourService;

use PHPUnit\Framework\TestCase;

use Symfony\Bundle\SecurityBundle\Security;



final class OnboardingTourServiceTest extends TestCase

{

    public function testGestorBuildIncludesProfileStepFlowsAndChecklistMeta(): void

    {

        $user = $this->userWithPerfil('GESTOR');

        $service = $this->createTourService(

            layout: 'gestor',

            checklist: [

                'visible' => true,

                'percent' => 40,

                'completed' => 2,

                'total' => 5,

                'steps' => [],

                'complete' => false,

            ],

            shellTourDone: false,

        );



        $result = $service->build($user, new Empresa(), 1, 'app_dashboard');



        self::assertTrue($result['enabled']);

        self::assertSame('gestor', $result['layout']);

        self::assertArrayNotHasKey('auto_start', $result);

        self::assertTrue($result['checklist']['visible']);

        self::assertFalse($result['checklist']['shell_tour_done']);



        $ids = array_column($result['steps'], 'id');

        self::assertContains('profile_gestor', $ids);

        self::assertContains('checklist', $ids);

        self::assertSame('[data-onboarding-checklist]', end($result['steps'])['target']);



        $flowIds = array_column($result['flows'], 'id');

        self::assertContains('full', $flowIds);

        self::assertContains('checklist-scroll', $flowIds);

        self::assertContains('shortcuts', $flowIds);



        $fullFlow = $this->findFlow($result['flows'], 'full');

        self::assertTrue($fullFlow['featured']);

        self::assertTrue($fullFlow['marks_complete']);

        self::assertFalse($fullFlow['done']);

    }



    public function testTenantBuildUsesTenantProfileStep(): void

    {

        $user = $this->userWithPerfil('TENANT');

        $service = $this->createTourService(

            layout: 'tenant',

            checklist: [

                'visible' => false,

                'percent' => 100,

                'completed' => 3,

                'total' => 3,

                'steps' => [],

                'complete' => true,

            ],

            shellTourDone: true,

        );



        $result = $service->build($user, new Empresa(), 1, 'app_welcome');



        self::assertTrue($result['checklist']['shell_tour_done']);

        self::assertContains('profile_tenant', array_column($result['steps'], 'id'));

        self::assertNotContains('checklist', array_column($result['steps'], 'id'));



        $tenantStep = null;

        foreach ($result['steps'] as $step) {

            if ($step['id'] === 'profile_tenant') {

                $tenantStep = $step;

                break;

            }

        }

        self::assertNotNull($tenantStep);

        self::assertSame('[data-tour="hub-admin"]', $tenantStep['target']);



        $fullFlow = $this->findFlow($result['flows'], 'full');

        self::assertTrue($fullFlow['done']);

    }



    public function testSupervisorBuildIncludesOperacoesHubTarget(): void

    {

        $user = $this->userWithPerfil('SUPERVISOR');

        $service = $this->createTourService(

            layout: 'supervisor',

            checklist: [

                'visible' => true,

                'percent' => 10,

                'completed' => 1,

                'total' => 4,

                'steps' => [],

                'complete' => false,

            ],

            shellTourDone: false,

        );



        $result = $service->build($user, new Empresa(), 1, 'app_chat');



        self::assertContains('profile_supervisor', array_column($result['steps'], 'id'));



        $supervisorStep = null;

        foreach ($result['steps'] as $step) {

            if ($step['id'] === 'profile_supervisor') {

                $supervisorStep = $step;

                break;

            }

        }

        self::assertNotNull($supervisorStep);

        self::assertSame('[data-tour="hub-operacoes"]', $supervisorStep['target']);



        $stepFlow = $this->findFlow($result['flows'], 'step-profile_supervisor');

        self::assertSame(['profile_supervisor'], $stepFlow['step_ids']);

        self::assertFalse($stepFlow['marks_complete']);

    }



    /**

     * @param list<array<string, mixed>> $flows

     *

     * @return array<string, mixed>

     */

    private function findFlow(array $flows, string $id): array

    {

        foreach ($flows as $flow) {

            if ($flow['id'] === $id) {

                return $flow;

            }

        }



        self::fail('Flow not found: ' . $id);

    }



    /**

     * @param array{visible: bool, percent: int, completed: int, total: int, steps: list<array<string, mixed>>, complete: bool} $checklist

     */

    private function createTourService(string $layout, array $checklist, bool $shellTourDone): OnboardingTourService

    {

        $navigation = $this->createMock(NavigationService::class);

        $navigation->method('getLayout')->willReturn($layout);

        $navigation->method('showCortex')->willReturn(true);

        $navigation->method('showHubOperacoes')->willReturn(true);

        $navigation->method('showHubTalentos')->willReturn(true);

        $navigation->method('showHubMaturidade')->willReturn(false);

        $navigation->method('showHubRecrutamento')->willReturn($layout === 'supervisor');

        $navigation->method('showPlataforma')->willReturn($layout === 'tenant');

        $navigation->method('getVisiblePlannedHubs')->willReturn([]);



        $grantRepo = $this->createMock(UserProductGrantRepository::class);

        $grantRepo->method('userHasConfiguredMatrix')->willReturn(false);

        $grantRepo->method('userHasAnyGrant')->willReturn(false);

        $grantRepo->method('findOneForUserScopeProduct')->willReturn(null);

        $grantRepo->method('findAllGrantKeysForUser')->willReturn([]);

        $security = $this->createMock(Security::class);

        $organismoFeature = $this->createMock(OrganismoFeature::class);
        $organismoFeature->method('isEnabled')->willReturn(false);

        $grants = new ProductGrantAccess($security, $grantRepo, $organismoFeature);



        $onboardingProgress = $this->createMock(OnboardingProgressService::class);

        $onboardingProgress->method('build')->willReturn($checklist);

        $onboardingProgress->method('isStepComplete')

            ->with('shell_tour')

            ->willReturn($shellTourDone);



        return new OnboardingTourService($navigation, $grants, $onboardingProgress);

    }



    private function userWithPerfil(string $perfil): User

    {

        $user = new User();

        $user->setEmail('tour-test@unio.dev');

        $user->setNome('Tour Test');

        $user->setPassword('secret');

        $user->setPerfil($perfil);



        return $user;

    }

}


