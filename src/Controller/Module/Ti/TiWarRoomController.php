<?php



namespace App\Controller\Module\Ti;



use App\Service\Ti\TiService;
use App\Service\Ti\TiWarRoomService;
use App\Service\WorkspaceService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\Security\Http\Attribute\IsGranted;



#[Route('/ti/war-room')]

#[IsGranted('ROLE_USER')]

final class TiWarRoomController extends AbstractController

{

    public function __construct(
        private WorkspaceService $workspace,
        private TiService $tiService,
        private TiWarRoomService $warRoom,
    ) {}



    #[Route('', name: 'app_ti_war_room', methods: ['GET'])]

    public function index(): Response

    {

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa();

        return $this->render('modules/ti/war_room.html.twig', array_merge(
            $this->tiService->getDashboard($user),
            $this->warRoom->build($empresa),
            ['ti_section' => 'war_room'],
        ));

    }



    #[Route('/poll', name: 'app_ti_war_room_poll', methods: ['GET'])]

    public function poll(): JsonResponse

    {

        $empresa = $this->requireEmpresa();



        return $this->json($this->warRoom->build($empresa));

    }



    private function requireEmpresa(): \App\Entity\Empresa

    {

        /** @var \App\Entity\User $user */

        $user = $this->getUser();

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();

        if (!$empresa) {

            throw $this->createAccessDeniedException();

        }



        return $empresa;

    }

}


