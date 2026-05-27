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
                ], $user);
                $this->addFlash('success', 'Dados atualizados com sucesso.');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_portal');
        }

        return $this->render(self::T . 'index.html.twig', [
            'funcionario' => $funcionario,
            'summary' => $funcionario ? $this->portal->dashboardSummary($funcionario) : null,
            'holerites_recentes' => $funcionario ? \array_slice($this->portal->listHolerites($funcionario), 0, 3) : [],
            'comunicados_preview' => $funcionario ? \array_slice($this->portal->listComunicados($funcionario), 0, 3) : [],
        ]);
    }

    #[Route('/ferias', name: 'app_rh_portal_ferias', methods: ['GET', 'POST'])]
    public function ferias(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        /** @var User $user */
        $user = $this->getUser();

        try {
            $funcionario = $this->portal->requireFuncionarioForUser($empresa, $user);
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_rh_portal');
        }

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_portal_ferias');
                $inicio = $this->parseDate($request->request->get('data_inicio'));
                $fim = $this->parseDate($request->request->get('data_fim'));
                if (!$inicio || !$fim) {
                    throw new RhProcessException('Informe as datas de início e fim.');
                }
                $this->portal->solicitarFerias(
                    $empresa,
                    $funcionario,
                    $inicio,
                    $fim,
                    $request->request->get('observacoes'),
                    $user
                );
                $this->addFlash('success', 'Solicitação de férias enviada ao RH.');

                return $this->redirectToRoute('app_rh_portal_ferias');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(self::T . 'ferias.html.twig', [
            'funcionario' => $funcionario,
            'ferias' => $this->portal->listFerias($funcionario),
        ]);
    }

    #[Route('/holerites', name: 'app_rh_portal_holerites')]
    public function holerites(): Response
    {
        $empresa = $this->requireEmpresa();
        /** @var User $user */
        $user = $this->getUser();

        try {
            $funcionario = $this->portal->requireFuncionarioForUser($empresa, $user);
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_rh_portal');
        }

        return $this->render(self::T . 'holerites.html.twig', [
            'funcionario' => $funcionario,
            'holerites' => $this->portal->listHolerites($funcionario),
        ]);
    }

    #[Route('/holerites/{id}', name: 'app_rh_portal_holerite', requirements: ['id' => '\d+'])]
    public function holerite(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        /** @var User $user */
        $user = $this->getUser();

        try {
            $funcionario = $this->portal->requireFuncionarioForUser($empresa, $user);
            $holerite = $this->portal->getHoleriteForFuncionario($id, $funcionario);
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_rh_portal_holerites');
        }

        return $this->render(self::T . 'holerite.html.twig', [
            'funcionario' => $funcionario,
            'holerite' => $holerite,
        ]);
    }

    #[Route('/comunicados', name: 'app_rh_portal_comunicados', methods: ['GET', 'POST'])]
    public function comunicados(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        /** @var User $user */
        $user = $this->getUser();

        try {
            $funcionario = $this->portal->requireFuncionarioForUser($empresa, $user);
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_rh_portal');
        }

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_portal_comunicado');
                $comId = (int) $request->request->get('comunicado_id', 0);
                foreach ($this->portal->listComunicados($funcionario) as $item) {
                    if ($item['comunicado']->getId() === $comId) {
                        $this->portal->markComunicadoRead($item['comunicado'], $funcionario, $user);
                        break;
                    }
                }
                $this->addFlash('success', 'Comunicado marcado como lido.');

                return $this->redirectToRoute('app_rh_portal_comunicados');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(self::T . 'comunicados.html.twig', [
            'funcionario' => $funcionario,
            'comunicados' => $this->portal->listComunicados($funcionario),
        ]);
    }
}
