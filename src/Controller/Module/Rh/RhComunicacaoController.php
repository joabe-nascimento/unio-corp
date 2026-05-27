<?php

namespace App\Controller\Module\Rh;

use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhComunicadoRepository;
use App\Service\Rh\RhComunicacaoService;
use App\Service\Rh\RhPortalService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/comunicacao')]
#[IsGranted('ROLE_USER')]
class RhComunicacaoController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/comunicacao/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhComunicacaoService $comunicacao,
        private RhPortalService $portal,
        private RhComunicadoRepository $comunicadoRepo,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_comunicacao', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', 'criar');
            try {
                if ($action === 'criar') {
                    $this->requireCsrf($request, 'rh_comunicacao_form');
                    $this->comunicacao->create(
                        $empresa,
                        (string) $request->request->get('titulo', ''),
                        (string) $request->request->get('corpo', ''),
                        $user,
                    );
                    $this->addFlash('success', 'Comunicado publicado.');
                } elseif ($action === 'ler') {
                    $this->requireCsrf($request, 'rh_comunicacao_ler');
                    $func = $this->portal->requireFuncionarioForUser($empresa, $user);
                    $com = $this->comunicadoRepo->find((int) $request->request->get('comunicado_id', 0));
                    if ($com && $com->getEmpresa()->getId() === $empresa->getId()) {
                        $this->comunicacao->markRead($com, $func);
                        $this->addFlash('success', 'Comunicado marcado como lido.');
                    }
                }
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_comunicacao');
        }

        return $this->render(self::T . 'index.html.twig', [
            'comunicados' => $this->comunicacao->listForEmpresa($empresa),
            'funcionario' => $this->portal->resolveFuncionarioForUser($empresa, $user),
        ]);
    }
}
