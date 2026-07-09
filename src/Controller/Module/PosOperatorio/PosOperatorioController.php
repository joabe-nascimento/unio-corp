<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\User;
use App\Service\Organismo\OrganismoFeature;
use App\Service\PosOperatorio\PosOperatorioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioController extends AbstractController
{
    private const T = 'modules/pos-operatorio/';

    public function __construct(
        private PosOperatorioService $service,
        private OrganismoFeature $organismo,
    ) {}

    #[Route('', name: 'app_pos_operatorio')]
    public function overview(Request $request): Response
    {
        if ($this->organismo->isEnabled()) {
            return $this->redirectToRoute('app_dashboard');
        }

        /** @var User $user */
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', PosOperatorioService::PATIENTS_PER_PAGE_DEFAULT);

        return $this->render(self::T . 'overview.html.twig', $this->service->getDashboard($user, $page, $perPage));
    }
}
