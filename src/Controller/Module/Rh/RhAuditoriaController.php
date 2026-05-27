<?php

namespace App\Controller\Module\Rh;

use App\Service\Rh\RhAuditService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/auditoria')]
#[IsGranted('ROLE_USER')]
class RhAuditoriaController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/auditoria/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhAuditService $audit,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_auditoria')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $modulo = trim((string) $request->query->get('modulo', ''));

        return $this->render(self::T . 'index.html.twig', [
            'logs' => $this->audit->listForEmpresa($empresa, $modulo !== '' ? $modulo : null),
            'filter_modulo' => $modulo,
        ]);
    }
}
