<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\NavigationService;
use App\Service\Organismo\OrganismoFeature;
use App\Service\Organismo\PulsoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/pulso')]
#[IsGranted('ROLE_USER')]
final class PulsoApiController extends AbstractController
{
    public function __construct(
        private OrganismoFeature $organismo,
        private PulsoService $pulso,
        private WorkspaceService $workspace,
        private NavigationService $navigation,
    ) {
    }

    #[Route('', name: 'api_pulso', methods: ['GET'])]
    public function snapshot(): JsonResponse
    {
        if (!$this->organismo->isEnabled()) {
            return $this->json(['error' => 'Organismo desabilitado'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user);
        $empresas = $this->workspace->getAvailableEmpresas($user);
        $layout = $this->navigation->getLayout($user);

        return $this->json(
            $this->pulso->buildSnapshot($user, $empresa, $layout, \count($empresas)),
        );
    }
}
