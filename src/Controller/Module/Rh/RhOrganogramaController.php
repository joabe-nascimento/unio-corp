<?php

namespace App\Controller\Module\Rh;

use App\Service\Rh\RhOrganogramaService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/organograma')]
#[IsGranted('ROLE_USER')]
class RhOrganogramaController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/organograma/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhOrganogramaService $organograma,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_organograma')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();
        $tree = $this->organograma->buildTree($empresa);

        return $this->render(self::T . 'index.html.twig', [
            'tree' => $tree,
            'total_nodes' => $this->organograma->countNodes($empresa),
        ]);
    }
}
