<?php

namespace App\Controller\Core;

use App\Service\NavigationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dev/components')]
#[IsGranted('ROLE_USER')]
class DevComponentsController extends AbstractController
{
    private const T = 'core/dev/';

    public function __construct(
        private NavigationService $navigation,
    ) {}

    #[Route('', name: 'app_dev_components')]
    public function index(): Response
    {
        $this->denyUnlessAllowed();

        return $this->render(self::T . 'components.html.twig', [
            'page_title' => 'Guia de componentes',
        ]);
    }

    private function denyUnlessAllowed(): void
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$this->navigation->showDevComponents($user)) {
            throw $this->createAccessDeniedException('Guia de componentes não disponível para seu perfil.');
        }
    }
}
