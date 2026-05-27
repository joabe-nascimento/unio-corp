<?php

namespace App\Controller\Module\Rh;

use App\Entity\User;
use App\Exception\RhProcessException;
use App\Service\Rh\RhPortalService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/portal')]
#[IsGranted('ROLE_USER')]
class RhPortalController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/portal/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhPortalService $portal,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_portal')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        /** @var User $user */
        $user = $this->getUser();
        $funcionario = $this->portal->resolveFuncionarioForUser($empresa, $user);

        if ($request->isMethod('POST') && $funcionario) {
            try {
                $this->requireCsrf($request, 'rh_portal_profile');
                $this->portal->updateProfile($funcionario, [
                    'telefone' => (string) $request->request->get('telefone', ''),
                    'cargo' => (string) $request->request->get('cargo', ''),
                ], $user);
                $this->addFlash('success', 'Dados atualizados com sucesso.');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_portal');
        }

        return $this->render(self::T . 'index.html.twig', [
            'funcionario' => $funcionario,
            'holerites' => $funcionario ? $this->portal->listHolerites($funcionario) : [],
        ]);
    }
}
