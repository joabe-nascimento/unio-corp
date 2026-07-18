<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\User;
use App\Entity\Empresa;
use App\Service\Clinic\ClinicReceptionHomeService;
use App\Service\Organismo\OrganismoFeature;
use App\Service\PosOperatorio\PosOperatorioService;
use App\Service\WorkspaceService;
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
        private WorkspaceService $workspace,
        private ClinicReceptionHomeService $receptionHome,
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

        $payload = $this->service->getDashboard($user, $page, $perPage);
        $empresa = $this->workspace->getActiveEmpresa($user);
        if ($empresa instanceof Empresa) {
            $payload['reception_home'] = $this->receptionHome->build($empresa);
        }

        return $this->render(self::T . 'overview.html.twig', $payload);
    }
}
