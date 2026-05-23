<?php

namespace App\Controller\Core;

use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/meu-perfil', name: 'app_profile')]
    public function index(WorkspaceService $workspaceService): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->render('core/profile/index.html.twig', [
            'empresa' => $workspaceService->getActiveEmpresa($user),
            'empresas' => $workspaceService->getAvailableEmpresas($user),
            'member_since' => $user->getCriadoEm()->format('d/m/Y'),
        ]);
    }
}
