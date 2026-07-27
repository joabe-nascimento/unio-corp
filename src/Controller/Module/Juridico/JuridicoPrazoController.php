<?php

namespace App\Controller\Module\Juridico;

use App\Exception\JuridicoProcessException;
use App\Repository\UserRepository;
use App\Service\Juridico\JuridicoPrazoService;
use App\Service\Juridico\JuridicoProcessoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/prazos')]
#[IsGranted('ROLE_USER')]
class JuridicoPrazoController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoPrazoService $prazos,
        private JuridicoProcessoService $processos,
        private UserRepository $userRepo,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_prazos')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $situacao = (string) $request->query->get('situacao', '');
        $q = (string) $request->query->get('q', '');

        return $this->render('modules/juridico/prazos_list.html.twig', [
            'prazos' => $this->prazos->findForEmpresa($empresa, $situacao ?: null, $q ?: null),
            'processos' => $this->processos->listForSelect($empresa),
            'responsaveis' => $this->userRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']),
            'filter_situacao' => $situacao,
            'filter_q' => $q,
            'open_novo' => $request->query->getBoolean('open_novo'),
        ]);
    }

    #[Route('/novo', name: 'app_juridico_prazo_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_juridico_prazos', ['open_novo' => 1]);
        }

        try {
            $this->requireCsrf($request, 'juridico_prazo_form');
            $this->prazos->create($empresa, $request->request->all());
            $this->addFlash('success', 'Prazo cadastrado.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_prazos');
    }

    #[Route('/{id}/editar', name: 'app_juridico_prazo_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $prazo = $this->prazos->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'juridico_prazo_form');
                $this->prazos->update($prazo, $request->request->all());
                $this->addFlash('success', 'Prazo atualizado.');

                return $this->redirectToRoute('app_juridico_prazos');
            } catch (JuridicoProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('modules/juridico/prazo_editar.html.twig', [
            'prazo' => $prazo,
            'processos' => $this->processos->listForSelect($empresa),
            'responsaveis' => $this->userRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']),
        ]);
    }

    #[Route('/{id}/marcar-cumprido', name: 'app_juridico_prazo_marcar_cumprido', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function marcarCumprido(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $prazo = $this->prazos->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_prazo_cumprido_' . $id);
        $this->prazos->marcarCumprido($prazo, !$prazo->isCumprido());
        $this->addFlash('success', $prazo->isCumprido() ? 'Prazo marcado como cumprido.' : 'Prazo reaberto.');

        return $this->redirectToRoute('app_juridico_prazos');
    }

    #[Route('/{id}/excluir', name: 'app_juridico_prazo_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $prazo = $this->prazos->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_prazo_excluir_' . $id);
        $this->prazos->delete($prazo);
        $this->addFlash('success', 'Prazo excluído.');

        return $this->redirectToRoute('app_juridico_prazos');
    }
}
