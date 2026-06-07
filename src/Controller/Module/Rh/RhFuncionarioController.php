<?php

namespace App\Controller\Module\Rh;

use App\Exception\RhProcessException;
use App\Service\FuncionarioService;
use App\Service\RhUserProvisioningService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/funcionarios')]
#[IsGranted('ROLE_USER')]
class RhFuncionarioController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/';

    public function __construct(
        private WorkspaceService $workspace,
        private FuncionarioService $funcionarios,
        private RhUserProvisioningService $userProvisioning,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_funcionarios')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->renderFuncionariosList($empresa, $request);
    }

    #[Route('/novo', name: 'app_rh_funcionario_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_rh_funcionarios', ['open_novo' => 1]);
        }

        try {
            $this->requireCsrf($request, 'rh_funcionario_form');
            $this->funcionarios->create($empresa, $request->request->all(), $request->files->get('foto'));
            $this->addFlash('success', 'Funcionário cadastrado.');

            return $this->redirectToRoute('app_rh_funcionarios');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->renderFuncionariosList($empresa, $request, [
                'open_funcionario_offcanvas' => true,
                'form_data' => $request->request->all(),
            ]);
        }
    }

    #[Route('/{id}', name: 'app_rh_funcionario_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $f = $this->funcionarios->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_funcionario_action');
                $action = (string) $request->request->get('action');
                if ($action === 'link_existing_user') {
                    $this->userProvisioning->linkExistingUserForFuncionario(
                        $f,
                        (string) $request->request->get('perfil', 'MEMBRO'),
                    );
                    $this->addFlash('success', 'Conta vinculada ao funcionário e empresa atualizada.');
                } else {
                    throw new RhProcessException('Ação inválida.');
                }
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_funcionario_show', ['id' => $id]);
        }

        return $this->render(self::T . 'funcionario_show.html.twig', [
            'funcionario' => $f,
            'platformAccount' => $this->userProvisioning->resolvePlatformAccountStateForFuncionario($f),
        ]);
    }

    #[Route('/{id}/editar', name: 'app_rh_funcionario_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $f = $this->funcionarios->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_funcionario_form');
                $this->funcionarios->update(
                    $f,
                    $request->request->all(),
                    $request->files->get('foto'),
                    $request->request->getBoolean('remove_foto')
                );
                $this->addFlash('success', 'Funcionário atualizado.');

                return $this->redirectToRoute('app_rh_funcionario_show', ['id' => $id]);
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->renderForm($empresa, $f);
    }

    private function renderFuncionariosList(\App\Entity\Empresa $empresa, Request $request, array $extra = []): Response
    {
        $status = (string) $request->query->get('status', '');
        $q = trim((string) $request->query->get('q', ''));

        return $this->render(self::T . 'funcionarios.html.twig', array_merge([
            'funcionarios' => $this->funcionarios->findForEmpresa($empresa, $status !== '' ? $status : null, $q !== '' ? $q : null),
            'filter_status' => $status,
            'filter_q' => $q,
            'empresa' => $empresa,
            'departamentos' => $this->funcionarios->listDepartamentos($empresa),
            'gestores' => $this->funcionarios->listGestores($empresa),
            'open_funcionario_offcanvas' => $request->query->getBoolean('open_novo'),
        ], $extra));
    }

    private function renderForm(\App\Entity\Empresa $empresa, ?\App\Entity\Funcionario $f = null): Response
    {
        return $this->render(self::T . 'funcionario_form.html.twig', [
            'empresa' => $empresa,
            'funcionario' => $f,
            'departamentos' => $this->funcionarios->listDepartamentos($empresa),
            'gestores' => $this->funcionarios->listGestores($empresa, $f?->getId()),
        ]);
    }
}
