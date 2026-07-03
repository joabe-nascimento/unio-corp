<?php

namespace App\Controller\Module\Admin;

use App\Service\PlatformOpsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/operacoes')]
#[IsGranted('ROLE_PLATFORM_OWNER')]
class PlatformOpsController extends AbstractController
{
    private const T = 'modules/admin/';

    #[Route('', name: 'app_admin_operacoes')]
    public function index(PlatformOpsService $ops): Response
    {
        return $this->render(self::T . 'operacoes.html.twig', [
            'snapshot' => $ops->getSnapshot(),
        ]);
    }
}
