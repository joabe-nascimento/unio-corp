<?php

namespace App\Controller\Core;

use App\Entity\DevMeta;
use App\Entity\DevProjeto;
use App\Entity\DevTarefa;
use App\Entity\Empresa;
use App\Repository\DevMetaRepository;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;
use App\Service\DevProjetoService;
use App\Service\NavigationService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/core/projetos')]
#[IsGranted('ROLE_USER')]
class DevProjetosController extends AbstractController
{
    private const T = 'modules/core/projetos/';

    public function __construct(
        private WorkspaceService $workspace,
        private DevProjetoService $service,
        private DevProjetoRepository $projetoRepo,
        private DevMetaRepository $metaRepo,
        private DevTarefaRepository $tarefaRepo,
        private NavigationService $navigation,
    ) {}

    #[Route('', name: 'app_core_projetos')]
    public function index(Request $request): Response
    {
        $this->denyUnlessAllowed();
        $empresa = $this->requireEmpresa();

        $view = $request->query->get('view', 'kanban');
        if (!in_array($view, ['lista', 'kanban', 'metas', 'permissions'], true)) {
            $view = 'kanban';
        }

        $projetoFilter = (int) $request->query->get('projeto', 0);
        $projetoFilter = $projetoFilter > 0 ? $projetoFilter : null;
        $tarefas = $this->tarefaRepo->findByEmpresa($empresa, $projetoFilter);

        $session = $request->getSession()->getFlashBag();
        $formFlash = $session->get('dev_projeto_form');
        $projetoFormRd = \is_array($formFlash[0] ?? null) ? $formFlash[0] : [];
        $metaFlash = $session->get('dev_meta_form');
        $metaFormRd = \is_array($metaFlash[0] ?? null) ? $metaFlash[0] : [];
        $tarefaFlash = $session->get('dev_tarefa_form');
        $tarefaFormRd = \is_array($tarefaFlash[0] ?? null) ? $tarefaFlash[0] : [];

        return $this->render(self::T . 'index.html.twig', [
            'active_view' => $view,
            'view' => $view,
            'projeto_form_rd' => $projetoFormRd,
            'meta_form_rd' => $metaFormRd,
            'open_projeto_offcanvas' => $request->query->getBoolean('open_novo'),
            'open_meta_offcanvas' => $request->query->getBoolean('open_nova_meta'),
            'open_tarefa_offcanvas' => $request->query->getBoolean('open_nova_tarefa'),
            'tarefa_form_rd' => $tarefaFormRd,
            'projetos' => $this->service->listProjetos($empresa),
            'metas' => $this->service->listMetas($empresa),
            'kanban' => $this->service->kanban($empresa, $projetoFilter),
            'kanban_columns' => DevTarefa::KANBAN_COLUMNS,
            'projeto_filter' => $projetoFilter,
            'has_kanban_tasks' => $tarefas !== [],
            'projetos_ativos' => $this->service->countProjetosAtivos($empresa),
            'tarefas_abertas' => count(array_filter(
                $tarefas,
                static fn (DevTarefa $t) => $t->getStatus() !== DevTarefa::STATUS_CONCLUIDO
            )),
            'move_url_template' => $this->generateUrl('app_core_tarefa_mover', ['id' => 0]),
            'csrf_move' => $this->container->get('security.csrf.token_manager')->getToken('dev_tarefa_move')->getValue(),
            'tarefa_edit_url_template' => $this->generateUrl('app_core_tarefa_editar', ['id' => 0]),
            'tarefa_delete_url_template' => $this->generateUrl('app_core_tarefa_excluir', ['id' => 0]),
            'csrf_tarefa_edit' => $this->container->get('security.csrf.token_manager')->getToken('dev_tarefa_edit')->getValue(),
            'csrf_tarefa_delete' => $this->container->get('security.csrf.token_manager')->getToken('dev_tarefa_delete')->getValue(),
        ]);
    }

    #[Route('/nova', name: 'app_core_projetos_nova', methods: ['GET', 'POST'])]
    public function nova(Request $request): Response
    {
        $this->denyUnlessAllowed();
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('dev_projeto_create', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $view = (string) $request->request->get('redirect_view', 'kanban');
            if (!\in_array($view, ['lista', 'kanban', 'metas'], true)) {
                $view = 'kanban';
            }

            $nome = trim((string) $request->request->get('nome', ''));
            if ($nome === '') {
                $this->addFlash('error', 'Nome do projeto é obrigatório.');
                $request->getSession()->getFlashBag()->add('dev_projeto_form', $request->request->all());

                return $this->redirectToRoute('app_core_projetos', ['view' => $view, 'open_novo' => 1]);
            }

            $this->service->createProjeto(
                $empresa,
                $nome,
                $request->request->get('codigo') ?: null,
                $request->request->get('descricao') ?: null,
                $request->request->get('area') ?: null,
                $request->request->get('status', DevProjeto::STATUS_EM_ANDAMENTO),
                $request->request->get('cor') ?: null,
                $this->service->parseDate($request->request->get('data_alvo')),
            );

            $this->addFlash('success', 'Projeto criado.');

            return $this->redirectToRoute('app_core_projetos', ['view' => $view]);
        }

        return $this->redirectToRoute('app_core_projetos', ['open_novo' => 1]);
    }

    #[Route('/meta/nova', name: 'app_core_metas_nova', methods: ['GET', 'POST'])]
    public function metaNova(Request $request): Response
    {
        $this->denyUnlessAllowed();
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('dev_meta_create', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $titulo = trim((string) $request->request->get('titulo', ''));
            if ($titulo === '') {
                $this->addFlash('error', 'Título da meta é obrigatório.');
                $request->getSession()->getFlashBag()->add('dev_meta_form', $request->request->all());

                return $this->redirectToRoute('app_core_projetos', ['view' => 'metas', 'open_nova_meta' => 1]);
            }

            $projetoId = (int) $request->request->get('projeto_id', 0);
            $projeto = $projetoId > 0 ? $this->projetoRepo->findOneBy(['id' => $projetoId, 'empresa' => $empresa]) : null;

            $this->service->createMeta(
                $empresa,
                $projeto,
                $titulo,
                $request->request->get('descricao') ?: null,
                $request->request->get('status', DevMeta::STATUS_PENDENTE),
                $request->request->get('prioridade', 'MEDIA'),
                (int) $request->request->get('progresso', 0),
                $this->service->parseDate($request->request->get('data_alvo')),
            );

            $this->addFlash('success', 'Meta criada.');

            return $this->redirectToRoute('app_core_projetos', ['view' => 'metas']);
        }

        return $this->redirectToRoute('app_core_projetos', ['view' => 'metas', 'open_nova_meta' => 1]);
    }

    #[Route('/tarefas/nova', name: 'app_core_tarefa_nova', methods: ['POST'])]
    public function novaTarefa(Request $request): Response
    {
        $this->denyUnlessAllowed();

        if (!$this->isCsrfTokenValid('dev_tarefa_create', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $empresa = $this->requireEmpresa();
        $projetoId = (int) $request->request->get('projeto_id', 0);
        $projeto = $projetoId > 0 ? $this->projetoRepo->findOneBy(['id' => $projetoId, 'empresa' => $empresa]) : null;

        $redirect = $request->request->get('redirect');
        $redirectView = (string) $request->request->get('redirect_view', 'kanban');
        if (!\in_array($redirectView, ['lista', 'kanban', 'metas'], true)) {
            $redirectView = 'kanban';
        }

        if (!$projeto) {
            $this->addFlash('error', 'Selecione um projeto para criar a tarefa.');
            $request->getSession()->getFlashBag()->add('dev_tarefa_form', $request->request->all());

            if ($redirect === 'show' && $projetoId > 0) {
                return $this->redirectToRoute('app_core_projetos_show', ['id' => $projetoId, 'open_nova_tarefa' => 1]);
            }

            return $this->redirectToRoute('app_core_projetos', ['view' => $redirectView, 'open_nova_tarefa' => 1]);
        }

        $titulo = trim((string) $request->request->get('titulo', ''));
        $status = (string) $request->request->get('status', DevTarefa::STATUS_BACKLOG);
        if (!isset(DevTarefa::KANBAN_COLUMNS[$status])) {
            $status = DevTarefa::STATUS_BACKLOG;
        }

        $prioridade = (string) $request->request->get('prioridade', 'MEDIA');
        if (!\in_array($prioridade, ['ALTA', 'MEDIA', 'BAIXA'], true)) {
            $prioridade = 'MEDIA';
        }

        $meta = null;
        $metaId = (int) $request->request->get('meta_id', 0);
        if ($metaId > 0) {
            $meta = $this->metaRepo->findOneBy(['id' => $metaId, 'projeto' => $projeto]);
        }

        if ($titulo === '') {
            $this->addFlash('error', 'Título da tarefa é obrigatório.');
            $request->getSession()->getFlashBag()->add('dev_tarefa_form', $request->request->all());

            if ($redirect === 'show') {
                return $this->redirectToRoute('app_core_projetos_show', ['id' => $projeto->getId(), 'open_nova_tarefa' => 1]);
            }

            return $this->redirectToRoute('app_core_projetos', [
                'view' => $redirectView,
                'projeto' => $projeto->getId(),
                'open_nova_tarefa' => 1,
            ]);
        }

        $this->service->createTarefa(
            $projeto,
            $titulo,
            $request->request->get('descricao') ?: null,
            $status,
            $prioridade,
            $meta,
        );
        $this->addFlash('success', 'Tarefa adicionada.');

        if ($redirect === 'show') {
            return $this->redirectToRoute('app_core_projetos_show', ['id' => $projeto->getId()]);
        }

        $params = ['view' => $redirectView, 'projeto' => $projeto->getId()];

        return $this->redirectToRoute('app_core_projetos', $params);
    }

    #[Route('/tarefas/{id}/editar', name: 'app_core_tarefa_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editarTarefa(int $id, Request $request): Response
    {
        $this->denyUnlessAllowed();

        if (!$this->isCsrfTokenValid('dev_tarefa_edit', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $empresa = $this->requireEmpresa();
        $tarefa = $this->tarefaRepo->find($id);
        if (!$tarefa || $tarefa->getEmpresa()->getId() !== $empresa->getId()) {
            throw $this->createNotFoundException();
        }

        $redirect = $request->request->get('redirect');
        $redirectView = (string) $request->request->get('redirect_view', 'kanban');
        if (!\in_array($redirectView, ['lista', 'kanban', 'metas'], true)) {
            $redirectView = 'kanban';
        }

        $projetoId = (int) $request->request->get('projeto_id', 0);
        $projeto = $projetoId > 0 ? $this->projetoRepo->findOneBy(['id' => $projetoId, 'empresa' => $empresa]) : null;
        if (!$projeto) {
            $this->addFlash('error', 'Selecione um projeto válido.');
            $request->getSession()->getFlashBag()->add('dev_tarefa_edit_form', array_merge($request->request->all(), ['id' => $id]));

            return $this->redirectAfterTarefaEdit($redirect, $redirectView, $tarefa, true);
        }

        $titulo = trim((string) $request->request->get('titulo', ''));
        $status = (string) $request->request->get('status', DevTarefa::STATUS_BACKLOG);
        if (!isset(DevTarefa::KANBAN_COLUMNS[$status])) {
            $status = DevTarefa::STATUS_BACKLOG;
        }

        $prioridade = (string) $request->request->get('prioridade', 'MEDIA');
        if (!\in_array($prioridade, ['ALTA', 'MEDIA', 'BAIXA'], true)) {
            $prioridade = 'MEDIA';
        }

        $meta = null;
        $metaId = (int) $request->request->get('meta_id', 0);
        if ($metaId > 0) {
            $meta = $this->metaRepo->findOneBy(['id' => $metaId, 'projeto' => $projeto]);
        }

        if ($titulo === '') {
            $this->addFlash('error', 'Título da tarefa é obrigatório.');
            $request->getSession()->getFlashBag()->add('dev_tarefa_edit_form', array_merge($request->request->all(), ['id' => $id]));

            return $this->redirectAfterTarefaEdit($redirect, $redirectView, $tarefa, true);
        }

        $this->service->updateTarefa(
            $tarefa,
            $projeto,
            $titulo,
            $request->request->get('descricao') ?: null,
            $status,
            $prioridade,
            $meta,
        );
        $this->addFlash('success', 'Tarefa atualizada.');

        return $this->redirectAfterTarefaEdit($redirect, $redirectView, $tarefa, false);
    }

    #[Route('/tarefas/{id}/excluir', name: 'app_core_tarefa_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluirTarefa(int $id, Request $request): Response
    {
        $this->denyUnlessAllowed();

        if (!$this->isCsrfTokenValid('dev_tarefa_delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $empresa = $this->requireEmpresa();
        $tarefa = $this->tarefaRepo->find($id);
        if (!$tarefa || $tarefa->getEmpresa()->getId() !== $empresa->getId()) {
            throw $this->createNotFoundException();
        }

        $projeto = $tarefa->getProjeto();
        $redirect = $request->request->get('redirect');
        $redirectView = (string) $request->request->get('redirect_view', 'kanban');
        if (!\in_array($redirectView, ['lista', 'kanban', 'metas'], true)) {
            $redirectView = 'kanban';
        }

        $this->service->deleteTarefa($tarefa);
        $this->addFlash('success', 'Tarefa excluída.');

        if ($redirect === 'show') {
            return $this->redirectToRoute('app_core_projetos_show', ['id' => $projeto->getId()]);
        }

        $params = ['view' => $redirectView];
        $projetoFilter = (int) $request->request->get('projeto_filter', 0);
        if ($projetoFilter > 0) {
            $params['projeto'] = $projetoFilter;
        }

        return $this->redirectToRoute('app_core_projetos', $params);
    }

    #[Route('/tarefas/{id}/mover', name: 'app_core_tarefa_mover', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function moverTarefa(int $id, Request $request): Response
    {
        $this->denyUnlessAllowed();

        if (!$this->isCsrfTokenValid('dev_tarefa_move', (string) $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => 'CSRF'], 403);
            }
            throw $this->createAccessDeniedException();
        }

        $empresa = $this->requireEmpresa();
        $tarefa = $this->tarefaRepo->find($id);

        if (!$tarefa || $tarefa->getEmpresa()->getId() !== $empresa->getId()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => 'not_found'], 404);
            }
            throw $this->createNotFoundException();
        }

        $status = (string) $request->request->get('status', '');
        $ordem = (int) $request->request->get('ordem', 0);
        if (!$this->service->moveTarefa($tarefa, $status, $ordem)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => 'invalid_status'], 400);
            }
            $this->addFlash('error', 'Status de coluna inválido.');

            return $this->redirectToRoute('app_core_projetos', ['view' => 'kanban']);
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['ok' => true, 'status' => $status, 'ordem' => $ordem]);
        }

        $view = $request->request->get('view', 'kanban');
        $params = ['view' => $view];
        $projetoFilter = (int) $request->request->get('projeto_filter', 0);
        if ($projetoFilter > 0) {
            $params['projeto'] = $projetoFilter;
        }

        if ($request->request->get('redirect') === 'show') {
            return $this->redirectToRoute('app_core_projetos_show', ['id' => $tarefa->getProjeto()->getId()]);
        }

        return $this->redirectToRoute('app_core_projetos', $params);
    }

    #[Route('/{id}', name: 'app_core_projetos_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request): Response
    {
        $this->denyUnlessAllowed();
        $projeto = $this->loadProjeto($id);

        if ($request->isMethod('POST')) {
            $titulo = trim((string) $request->request->get('titulo', ''));
            if ($titulo !== '') {
                $meta = null;
                $metaId = (int) $request->request->get('meta_id', 0);
                if ($metaId > 0) {
                    $meta = $this->metaRepo->findOneBy(['id' => $metaId, 'projeto' => $projeto]);
                }
                $this->service->createTarefa(
                    $projeto,
                    $titulo,
                    $request->request->get('descricao') ?: null,
                    $request->request->get('status', DevTarefa::STATUS_BACKLOG),
                    $request->request->get('prioridade', 'MEDIA'),
                    $meta,
                );
                $this->addFlash('success', 'Tarefa adicionada ao kanban.');
            }

            return $this->redirectToRoute('app_core_projetos_show', ['id' => $id]);
        }

        $tarefaFlash = $request->getSession()->getFlashBag()->get('dev_tarefa_form');
        $tarefaFormRd = \is_array($tarefaFlash[0] ?? null) ? $tarefaFlash[0] : [];

        return $this->render(self::T . 'show.html.twig', [
            'projeto' => $projeto,
            'metas' => $this->metaRepo->findBy(['projeto' => $projeto], ['dataAlvo' => 'ASC']),
            'kanban' => $this->service->kanban($projeto->getEmpresa(), $projeto->getId()),
            'kanban_columns' => DevTarefa::KANBAN_COLUMNS,
            'move_url_template' => $this->generateUrl('app_core_tarefa_mover', ['id' => 0]),
            'csrf_move' => $this->container->get('security.csrf.token_manager')->getToken('dev_tarefa_move')->getValue(),
            'projeto_filter' => $projeto->getId(),
            'open_tarefa_offcanvas' => $request->query->getBoolean('open_nova_tarefa'),
            'tarefa_form_rd' => $tarefaFormRd,
            'tarefa_edit_url_template' => $this->generateUrl('app_core_tarefa_editar', ['id' => 0]),
            'tarefa_delete_url_template' => $this->generateUrl('app_core_tarefa_excluir', ['id' => 0]),
            'csrf_tarefa_edit' => $this->container->get('security.csrf.token_manager')->getToken('dev_tarefa_edit')->getValue(),
            'csrf_tarefa_delete' => $this->container->get('security.csrf.token_manager')->getToken('dev_tarefa_delete')->getValue(),
        ]);
    }

    private function redirectAfterTarefaEdit(?string $redirect, string $redirectView, DevTarefa $tarefa, bool $reopenEdit): Response
    {
        if ($redirect === 'show') {
            $params = ['id' => $tarefa->getProjeto()->getId()];
            if ($reopenEdit) {
                $params['open_editar_tarefa'] = $tarefa->getId();
            }

            return $this->redirectToRoute('app_core_projetos_show', $params);
        }

        $params = ['view' => $redirectView, 'projeto' => $tarefa->getProjeto()->getId()];
        if ($reopenEdit) {
            $params['open_editar_tarefa'] = $tarefa->getId();
        }

        return $this->redirectToRoute('app_core_projetos', $params);
    }

    private function denyUnlessAllowed(): void
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$this->navigation->showProjetosMetas($user)) {
            throw $this->createAccessDeniedException('Projetos e Metas não disponível para seu perfil.');
        }
    }

    private function requireEmpresa(): Empresa
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user);

        if (!$empresa) {
            throw $this->createAccessDeniedException('Selecione uma área de trabalho.');
        }

        return $empresa;
    }

    private function loadProjeto(int $id): DevProjeto
    {
        $empresa = $this->requireEmpresa();
        $projeto = $this->projetoRepo->find($id);

        if (!$projeto || $projeto->getEmpresa()->getId() !== $empresa->getId()) {
            throw $this->createNotFoundException();
        }

        return $projeto;
    }
}
