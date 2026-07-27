<?php

namespace App\Controller\Module\Juridico;

use App\Exception\JuridicoProcessException;
use App\Repository\UserRepository;
use App\Service\Juridico\JuridicoClienteService;
use App\Service\Juridico\JuridicoProcessoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/processos')]
#[IsGranted('ROLE_USER')]
class JuridicoProcessoController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoProcessoService $processos,
        private JuridicoClienteService $clientes,
        private UserRepository $userRepo,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_processos')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $status = (string) $request->query->get('status', '');
        $q = (string) $request->query->get('q', '');

        return $this->render('modules/juridico/processos_list.html.twig', [
            'processos' => $this->processos->findForEmpresa($empresa, $status ?: null, $q ?: null),
            'clientes' => $this->clientes->listForSelect($empresa),
            'responsaveis' => $this->userRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']),
            'filter_status' => $status,
            'filter_q' => $q,
            'open_novo' => $request->query->getBoolean('open_novo'),
        ]);
    }

    #[Route('/novo', name: 'app_juridico_processo_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_juridico_processos', ['open_novo' => 1]);
        }

        try {
            $this->requireCsrf($request, 'juridico_processo_form');
            $processo = $this->processos->create($empresa, $request->request->all());
            $this->addFlash('success', 'Processo cadastrado.');

            return $this->redirectToRoute('app_juridico_processo_show', ['id' => $processo->getId()]);
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_juridico_processos', ['open_novo' => 1]);
        }
    }

    #[Route('/{id}', name: 'app_juridico_processo_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $processo = $this->processos->loadForEmpresa($empresa, $id);

        return $this->render('modules/juridico/processo_show.html.twig', [
            'processo' => $processo,
            'clientes' => $this->clientes->listForSelect($empresa),
            'responsaveis' => $this->userRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']),
        ]);
    }

    #[Route('/{id}/editar', name: 'app_juridico_processo_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $processo = $this->processos->loadForEmpresa($empresa, $id);

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_juridico_processo_show', ['id' => $id, 'open_editar' => 1]);
        }

        try {
            $this->requireCsrf($request, 'juridico_processo_form');
            $this->processos->update($processo, $request->request->all());
            $this->addFlash('success', 'Processo atualizado.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_processo_show', ['id' => $id]);
    }

    #[Route('/{id}/excluir', name: 'app_juridico_processo_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $processo = $this->processos->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_processo_excluir_' . $id);
        $this->processos->delete($processo);
        $this->addFlash('success', 'Processo excluído.');

        return $this->redirectToRoute('app_juridico_processos');
    }
}
