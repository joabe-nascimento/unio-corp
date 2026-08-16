<?php

namespace App\Controller\Module\Juridico;

use App\Entity\JuridicoProcesso;
use App\Exception\JuridicoProcessException;
use App\Repository\UserRepository;
use App\Service\Juridico\JuridicoClienteService;
use App\Service\Juridico\JuridicoProcessoParteService;
use App\Service\Juridico\JuridicoProcessoService;
use App\Service\Juridico\JuridicoProcessoTarefaService;
use App\Service\Juridico\JuridicoRiscoAlertaService;
use App\Service\Juridico\JuridicoProcessoTimelineService;
use App\Service\Juridico\PrevisaoExitoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private JuridicoProcessoTarefaService $tarefas,
        private JuridicoProcessoParteService $partes,
        private JuridicoRiscoAlertaService $riscos,
        private PrevisaoExitoService $previsaoExito,
        private JuridicoProcessoTimelineService $timeline,
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
        $view = (string) $request->query->get('view', 'lista') === 'kanban' ? 'kanban' : 'lista';

        $processos = $this->processos->findForEmpresa($empresa, $status ?: null, $q ?: null);

        $kanbanColunas = [];
        foreach (JuridicoProcesso::FASES as $fase) {
            $kanbanColunas[$fase] = [];
        }
        foreach ($processos as $processo) {
            $kanbanColunas[$processo->getFase()][] = $processo;
        }

        return $this->render('modules/juridico/processos_list.html.twig', [
            'processos' => $processos,
            'clientes' => $this->clientes->listForSelect($empresa),
            'responsaveis' => $this->userRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']),
            'filter_status' => $status,
            'filter_q' => $q,
            'open_novo' => $request->query->getBoolean('open_novo'),
            'stats' => $this->processos->estatisticas($empresa),
            'view' => $view,
            'kanban_colunas' => $kanbanColunas,
            'fases' => JuridicoProcesso::FASES,
            'alertas' => $this->riscos->gerarAlertas($empresa),
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

    #[Route('/{id}/fase', name: 'app_juridico_processo_fase', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function atualizarFase(int $id, Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $processo = $this->processos->loadForEmpresa($empresa, $id);
        $payload = json_decode($request->getContent(), true);
        $payload = \is_array($payload) ? $payload : [];

        if (!$this->isCsrfTokenValid('juridico_processo_kanban', (string) ($payload['_token'] ?? ''))) {
            return $this->json(['error' => 'Token de segurança inválido.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->processos->atualizarFase($processo, (string) ($payload['fase'] ?? ''));

            return $this->json(['ok' => true, 'status' => $processo->getStatus()]);
        } catch (JuridicoProcessException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/{id}', name: 'app_juridico_processo_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $processo = $this->processos->loadForEmpresa($empresa, $id);
        $tarefas = $this->tarefas->findForProcesso($processo);
        $pendentes = array_values(array_filter($tarefas, fn ($t) => !$t->isConcluida()));

        return $this->render('modules/juridico/processo_show.html.twig', [
            'processo' => $processo,
            'clientes' => $this->clientes->listForSelect($empresa),
            'responsaveis' => $this->userRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']),
            'tarefas' => $tarefas,
            'partes' => $this->partes->findForProcesso($processo),
            'alertas_processo' => $this->riscos->avaliarProcesso($processo, $pendentes),
            'previsao' => $processo->getStatus() !== JuridicoProcesso::STATUS_ENCERRADO ? $this->previsaoExito->preverAuto($processo) : null,
            'timeline' => $this->timeline->montar($processo),
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

    #[Route('/{id}/tarefas', name: 'app_juridico_processo_tarefa_novo', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function tarefaNova(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $processo = $this->processos->loadForEmpresa($empresa, $id);

        try {
            $this->requireCsrf($request, 'juridico_processo_tarefa_form');
            $this->tarefas->create($processo, $request->request->all());
            $this->addFlash('success', 'Tarefa adicionada.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_processo_show', ['id' => $id]);
    }

    #[Route('/{id}/tarefas/{tarefaId}/concluir', name: 'app_juridico_processo_tarefa_concluir', requirements: ['id' => '\d+', 'tarefaId' => '\d+'], methods: ['POST'])]
    public function tarefaConcluir(int $id, int $tarefaId, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $processo = $this->processos->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_processo_tarefa_concluir_' . $tarefaId);

        try {
            $tarefa = $this->tarefas->loadForProcesso($processo, $tarefaId);
            $this->tarefas->alternarConclusao($tarefa);
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_processo_show', ['id' => $id]);
    }

    #[Route('/{id}/tarefas/{tarefaId}/excluir', name: 'app_juridico_processo_tarefa_excluir', requirements: ['id' => '\d+', 'tarefaId' => '\d+'], methods: ['POST'])]
    public function tarefaExcluir(int $id, int $tarefaId, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $processo = $this->processos->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_processo_tarefa_excluir_' . $tarefaId);

        try {
            $tarefa = $this->tarefas->loadForProcesso($processo, $tarefaId);
            $this->tarefas->delete($tarefa);
            $this->addFlash('success', 'Tarefa removida.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_processo_show', ['id' => $id]);
    }

    #[Route('/{id}/partes', name: 'app_juridico_processo_parte_novo', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function parteNova(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $processo = $this->processos->loadForEmpresa($empresa, $id);

        try {
            $this->requireCsrf($request, 'juridico_processo_parte_form');
            $this->partes->create($processo, $request->request->all());
            $this->addFlash('success', 'Parte adicionada ao processo.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_processo_show', ['id' => $id]);
    }

    #[Route('/{id}/partes/{parteId}/excluir', name: 'app_juridico_processo_parte_excluir', requirements: ['id' => '\d+', 'parteId' => '\d+'], methods: ['POST'])]
    public function parteExcluir(int $id, int $parteId, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $processo = $this->processos->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_processo_parte_excluir_' . $parteId);

        try {
            $parte = $this->partes->loadForProcesso($processo, $parteId);
            $this->partes->delete($parte);
            $this->addFlash('success', 'Parte removida.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_processo_show', ['id' => $id]);
    }
}
