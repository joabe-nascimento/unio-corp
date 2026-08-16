<?php

namespace App\Controller\Module\Juridico;

use App\Service\Juridico\JurisFlowAiClient;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/ia-ops')]
#[IsGranted('ROLE_GESTOR')]
class JuridicoAiOpsController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JurisFlowAiClient $ai,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_ia_ops')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();
        $status = $this->ai->status();
        $available = $this->ai->isAvailable();

        return $this->render('modules/juridico/ia_ops.html.twig', [
            'status' => $status,
            'available' => $available,
            'escritorio' => $empresa->getNome(),
        ]);
    }
}
