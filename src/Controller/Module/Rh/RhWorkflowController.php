<?php

namespace App\Controller\Module\Rh;

use App\Entity\User;
use App\Exception\RhProcessException;
use App\Service\Rh\RhWorkflowService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/workflows')]
#[IsGranted('ROLE_USER')]
class RhWorkflowController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/workflows/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhWorkflowService $workflows,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_workflows', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_workflow_form');
                /** @var User $user */
                $user = $this->getUser();
                $checklistRaw = (string) $request->request->get('checklist', '');
                $items = [];
                foreach (preg_split('/\r\n|\r|\n/', $checklistRaw) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $items[] = ['id' => md5($line), 'label' => $line, 'done' => false];
                }
                $this->workflows->save(
                    $empresa,
                    (string) $request->request->get('codigo', ''),
                    (string) $request->request->get('nome', ''),
                    (string) $request->request->get('tipo_processo', 'onboarding'),
                    $items,
                    true,
                    null,
                    $user,
                );
                $this->addFlash('success', 'Template de workflow salvo.');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_workflows');
        }

        return $this->render(self::T . 'index.html.twig', [
            'templates' => $this->workflows->listForEmpresa($empresa),
        ]);
    }
}
