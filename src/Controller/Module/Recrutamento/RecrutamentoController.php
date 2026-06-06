<?php

namespace App\Controller\Module\Recrutamento;

use App\Controller\Module\Rh\RhEmpresaScopeTrait;
use App\Entity\RhVaga;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\DepartamentoRepository;
use App\Repository\RhCandidatoAprovacaoRepository;
use App\Repository\RhCandidatoRepository;
use App\Repository\RhEmailEventRepository;
use App\Repository\RhTalentoPoolRepository;
use App\Repository\RhVagaRepository;
use App\Repository\UserRepository;
use App\Rh\RhCandidatoEtapa;
use App\Rh\RhCandidatoOrigem;
use App\Rh\RhVagaTipoContrato;
use App\Security\ProductGrantAccess;
use App\Service\Analytics\RecrutamentoAnalyticsService;
use App\Service\Rh\RhCarreirasService;
use App\Service\Rh\RhCandidatoAttachmentService;
use App\Service\Rh\RhRecruitmentExtendedService;
use App\Service\Rh\RhRecrutamentoEmailService;
use App\Service\Rh\RhRecrutamentoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/recrutamento')]
#[IsGranted('ROLE_USER')]
class RecrutamentoController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/recrutamento/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhRecrutamentoService $recrutamento,
        private RhVagaRepository $vagaRepo,
        private RhCandidatoRepository $candidatoRepo,
        private DepartamentoRepository $departamentoRepo,
        private ProductGrantAccess $grants,
        private RhRecruitmentExtendedService $extended,
        private RhRecrutamentoEmailService $emails,
        private RhCandidatoAttachmentService $attachments,
        private RhTalentoPoolRepository $talentoPoolRepo,
        private RhCandidatoAprovacaoRepository $aprovacaoRepo,
        private RhEmailEventRepository $emailEventRepo,
        private RhCarreirasService $carreiras,
        private UserRepository $userRepo,
        private RecrutamentoAnalyticsService $recrutamentoAnalytics,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_recrutamento')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();
        $timeToHire = $this->candidatoRepo->avgTimeToHireDays($empresa);
        $allVagas = $this->recrutamento->listVagas($empresa);

        $aprovacoesPendentes = $this->extended->listPendentes($empresa);
        $allCandidatos = $this->candidatoRepo->findForEmpresa($empresa);

        return $this->render(self::T . 'index.html.twig', [
            'empresa' => $empresa,
            'vagas_abertas' => $this->vagaRepo->countAbertasByEmpresa($empresa),
            'vagas_publicadas' => $this->vagaRepo->countPublicadasByEmpresa($empresa),
            'total_candidatos' => $this->candidatoRepo->countAtivosByEmpresa($empresa),
            'total_vagas' => \count($allVagas),
            'contratados' => $this->candidatoRepo->countContratadosByEmpresa($empresa),
            'reprovados' => $this->candidatoRepo->countByEtapaForEmpresa($empresa, RhCandidatoEtapa::REPROVADO),
            'time_to_hire_days' => $timeToHire,
            'hire_rate' => $this->candidatoRepo->hireRatePercent($empresa),
            'recent_vagas' => \array_slice($allVagas, 0, 5),
            'recent_vagas_total' => \count($allVagas),
            'recent_candidatos' => \array_slice($allCandidatos, 0, 5),
            'recent_candidatos_total' => \count($allCandidatos),
            'talentos_count' => $this->talentoPoolRepo->countByEmpresa($empresa),
            'aprovacoes_pendentes' => $aprovacoesPendentes,
            'aprovacoes_pendentes_count' => \count($aprovacoesPendentes),
            'emails_pendentes' => $this->emailEventRepo->countPendentesByEmpresa($empresa),
            'entrevistas_proximas' => $this->candidatoRepo->findProximasEntrevistas($empresa, 5),
            'entrevistas_proximas_count' => $this->candidatoRepo->countProximasEntrevistas($empresa),
            'carreiras_ativo' => $empresa->isCarreirasAtivo(),
            'carreiras_url' => $this->carreiras->publicUrl($empresa),
            'can_manage_vagas' => $this->canManageVagas(),
        ]);
    }

    #[Route('/analytics', name: 'app_recrutamento_analytics')]
    public function analytics(): Response
    {
        $empresa = $this->requireEmpresa();
        $chartPayload = $this->recrutamentoAnalytics->getHubChartPayload($empresa);

        return $this->render(self::T . 'analytics.html.twig', [
            'empresa' => $empresa,
            'chart_sections' => $chartPayload['sections'],
            'chart_executive' => $chartPayload['executive'],
        ]);
    }

    #[Route('/candidatos', name: 'app_recrutamento_candidatos', methods: ['GET'])]
    public function candidatos(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $vagaId = (int) $request->query->get('vaga', 0);
        $filterVagaId = $vagaId > 0 ? $vagaId : null;
        $etapa = trim((string) $request->query->get('etapa', ''));
        $origem = trim((string) $request->query->get('origem', ''));
        $q = trim((string) $request->query->get('q', ''));

        return $this->render(self::T . 'candidatos.html.twig', [
            'candidatos' => $this->recrutamento->listCandidatosForEmpresa(
                $empresa,
                $filterVagaId,
                $q !== '' ? $q : null,
                $etapa !== '' ? $etapa : null,
                $origem !== '' ? $origem : null,
            ),
            'vagas' => $this->recrutamento->listVagas($empresa),
            'filter_vaga_id' => $filterVagaId,
            'filter_etapa' => $etapa,
            'filter_origem' => $origem,
            'filter_q' => $q,
            'has_filters' => $filterVagaId !== null || $etapa !== '' || $origem !== '' || $q !== '',
            'etapa_options' => $this->etapaFilterOptions(),
            'origem_options' => $this->origemFilterOptions(),
        ]);
    }

    #[Route('/candidatos/exportar', name: 'app_recrutamento_candidatos_export', methods: ['GET'])]
    public function candidatosExport(Request $request): StreamedResponse
    {
        $empresa = $this->requireEmpresa();
        $vagaId = (int) $request->query->get('vaga', 0);
        $filterVagaId = $vagaId > 0 ? $vagaId : null;
        $etapa = trim((string) $request->query->get('etapa', ''));
        $origem = trim((string) $request->query->get('origem', ''));
        $q = trim((string) $request->query->get('q', ''));

        $candidatos = $this->recrutamento->listCandidatosForEmpresa(
            $empresa,
            $filterVagaId,
            $q !== '' ? $q : null,
            $etapa !== '' ? $etapa : null,
            $origem !== '' ? $origem : null,
        );

        $response = new StreamedResponse(function () use ($candidatos): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Nome', 'E-mail', 'Telefone', 'Vaga', 'Etapa', 'Origem', 'Avaliação', 'Cadastro'], ';');
            foreach ($candidatos as $c) {
                fputcsv($out, [
                    $c->getNome(),
                    $c->getEmail(),
                    $c->getTelefone() ?? '',
                    $c->getVaga()->getTitulo(),
                    $c->getEtapaLabel(),
                    $c->getOrigemLabel(),
                    $c->getAvaliacao() ?? '',
                    $c->getCriadoEm()->format('d/m/Y'),
                ], ';');
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="candidatos-' . date('Y-m-d') . '.csv"');

        return $response;
    }

    #[Route('/candidatos/{id}', name: 'app_recrutamento_candidatos_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function candidatoShow(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $candidato = $this->candidatoRepo->findOneForEmpresa($id, $empresa);
        if (!$candidato) {
            throw $this->createNotFoundException();
        }

        return $this->render(self::T . 'candidato_show.html.twig', [
            'candidato' => $candidato,
            'timeline' => $this->recrutamento->buildCandidatoTimeline($empresa, $candidato),
            'pipeline_stages' => RhCandidatoEtapa::boardStages(),
            'aprovacoes' => $this->extended->listForCandidato($candidato),
            'anexos' => $this->attachments->listForCandidato($candidato),
            'entrevistadores' => $this->userRepo->findActiveByEmpresa($empresa),
        ]);
    }

    #[Route('/candidatos/{id}/editar', name: 'app_recrutamento_candidato_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function candidatoEdit(int $id, Request $request): Response
    {
        if (!$this->canManageVagas()) {
            throw $this->createAccessDeniedException();
        }

        $empresa = $this->requireEmpresa();
        $candidato = $this->candidatoRepo->findOneForEmpresa($id, $empresa);
        if (!$candidato) {
            throw $this->createNotFoundException();
        }

        $redirectTo = (string) $request->request->get('redirect_to', 'show');

        try {
            $this->requireCsrf($request, 'recrutamento_candidato_edit_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $this->recrutamento->updateCandidato(
                $candidato,
                (string) $request->request->get('nome', ''),
                (string) $request->request->get('email', ''),
                (string) $request->request->get('telefone', ''),
                $user,
                (string) $request->request->get('origem', $candidato->getOrigem()),
                (string) $request->request->get('linkedin', ''),
            );
            $this->addFlash('success', 'Candidato atualizado.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        if ($redirectTo === 'list') {
            return $this->redirectToRoute('app_recrutamento_candidatos', $this->candidatosRedirectParams($request));
        }

        return $this->redirectToRoute('app_recrutamento_candidatos_show', ['id' => $id]);
    }

    #[Route('/candidatos/{id}/avaliacao', name: 'app_recrutamento_candidato_avaliacao', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function candidatoAvaliacao(int $id, Request $request): Response
    {
        if (!$this->canManageVagas() && !$this->canManagePipeline()) {
            throw $this->createAccessDeniedException();
        }

        $empresa = $this->requireEmpresa();
        $candidato = $this->candidatoRepo->findOneForEmpresa($id, $empresa);
        if (!$candidato) {
            throw $this->createNotFoundException();
        }

        try {
            $this->requireCsrf($request, 'recrutamento_candidato_avaliacao_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $avaliacaoRaw = $request->request->get('avaliacao', '');
            $avaliacao = $avaliacaoRaw === '' || $avaliacaoRaw === null ? null : (int) $avaliacaoRaw;
            $this->recrutamento->updateCandidatoAvaliacao(
                $candidato,
                $avaliacao,
                (string) $request->request->get('observacoes', ''),
                $user,
            );
            $this->addFlash('success', 'Avaliação e notas salvas.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_recrutamento_candidatos_show', ['id' => $id]);
    }

    #[Route('/vagas', name: 'app_recrutamento_vagas', methods: ['GET', 'POST'])]
    public function vagas(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $status = (string) $request->query->get('status', '');
        $q = trim((string) $request->query->get('q', ''));

        if ($request->isMethod('POST')) {
            if (!$this->canManageVagas()) {
                throw $this->createAccessDeniedException();
            }
            try {
                $this->requireCsrf($request, 'recrutamento_vaga');
                /** @var User $user */
                $user = $this->getUser();
                $vaga = $this->recrutamento->createVaga(
                    $empresa,
                    (string) $request->request->get('titulo', ''),
                    (string) $request->request->get('departamento', ''),
                    (string) $request->request->get('descricao', ''),
                    $user,
                    (string) $request->request->get('status', RhVaga::STATUS_ABERTA),
                    (string) $request->request->get('tipo_contrato', ''),
                    (string) $request->request->get('local_trabalho', ''),
                    (string) $request->request->get('requisitos', ''),
                    max(1, (int) $request->request->get('vagas_quantidade', 1)),
                );
                $this->addFlash('success', 'Vaga criada com sucesso.');

                $afterCreate = (string) $request->request->get('after_create', 'list');
                if ($afterCreate === 'show_candidato') {
                    return $this->redirectToRoute('app_recrutamento_vagas_show', [
                        'id' => $vaga->getId(),
                        'open_candidato' => '1',
                    ]);
                }
                if ($afterCreate === 'show') {
                    return $this->redirectToRoute('app_recrutamento_vagas_show', ['id' => $vaga->getId()]);
                }
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_recrutamento_vagas', $this->vagasRedirectParams($request, $status, $q));
        }

        $vagas = $this->recrutamento->listVagas(
            $empresa,
            $status !== '' ? $status : null,
            $q !== '' ? $q : null,
        );

        return $this->render(self::T . 'vagas.html.twig', [
            'vagas' => $vagas,
            'filter_status' => $status,
            'filter_q' => $q,
            'has_filters' => $status !== '' || $q !== '',
            'candidatos_por_vaga' => $this->candidatoRepo->countGroupedByVagaForEmpresa($empresa),
            'candidatos_lista' => $this->candidatoRepo->findGroupedByVagaForEmpresa($empresa),
            'status_options' => $this->statusOptions(),
            'departamentos' => $this->departamentoRepo->findByEmpresa($empresa),
        ]);
    }

    #[Route('/vagas/{id}', name: 'app_recrutamento_vagas_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function vagaShow(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $vaga = $this->vagaRepo->findOneForEmpresa($id, $empresa);
        if (!$vaga) {
            throw $this->createNotFoundException();
        }

        $candidatos = $this->recrutamento->listCandidatos($vaga);
        $etapasCount = [];
        foreach (RhCandidatoEtapa::BOARD_ORDER as $etapa) {
            $etapasCount[$etapa] = 0;
        }
        foreach ($candidatos as $candidato) {
            $etapa = RhCandidatoEtapa::isValid($candidato->getEtapa())
                ? $candidato->getEtapa()
                : RhCandidatoEtapa::TRIAGEM;
            ++$etapasCount[$etapa];
        }

        return $this->render(self::T . 'vaga_show.html.twig', [
            'vaga' => $vaga,
            'candidatos' => $candidatos,
            'etapas_count' => $etapasCount,
            'departamentos' => $this->departamentoRepo->findByEmpresa($empresa),
        ]);
    }

    #[Route('/vagas/{id}/editar', name: 'app_recrutamento_vaga_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function vagaEdit(int $id, Request $request): Response
    {
        if (!$this->canManageVagas()) {
            throw $this->createAccessDeniedException();
        }

        $empresa = $this->requireEmpresa();
        $vaga = $this->vagaRepo->findOneForEmpresa($id, $empresa);
        if (!$vaga) {
            throw $this->createNotFoundException();
        }

        $redirectTo = (string) $request->request->get('redirect_to', 'show');

        try {
            $this->requireCsrf($request, 'recrutamento_vaga_edit_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $this->recrutamento->updateVaga(
                $vaga,
                (string) $request->request->get('titulo', ''),
                (string) $request->request->get('departamento', ''),
                (string) $request->request->get('descricao', ''),
                (string) $request->request->get('status', RhVaga::STATUS_ABERTA),
                $user,
                (string) $request->request->get('tipo_contrato', ''),
                (string) $request->request->get('local_trabalho', ''),
                (string) $request->request->get('requisitos', ''),
                max(1, (int) $request->request->get('vagas_quantidade', 1)),
            );
            $this->addFlash('success', 'Vaga atualizada.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        if ($redirectTo === 'list') {
            return $this->redirectToRoute(
                'app_recrutamento_vagas',
                $this->vagasRedirectParams($request),
            );
        }

        return $this->redirectToRoute('app_recrutamento_vagas_show', ['id' => $id]);
    }

    #[Route('/vagas/{id}/status', name: 'app_recrutamento_vaga_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function vagaStatus(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $vaga = $this->vagaRepo->findOneForEmpresa($id, $empresa);
        if (!$vaga) {
            throw $this->createNotFoundException();
        }

        $redirectTo = (string) $request->request->get('redirect_to', 'list');

        try {
            $this->requireCsrf($request, 'recrutamento_vaga_status_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $this->recrutamento->updateVagaStatus(
                $vaga,
                (string) $request->request->get('status', ''),
                $user,
            );
            $this->addFlash('success', 'Status da vaga atualizado.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        if ($redirectTo === 'show') {
            return $this->redirectToRoute('app_recrutamento_vagas_show', ['id' => $id]);
        }

        return $this->redirectToRoute(
            'app_recrutamento_vagas',
            $this->vagasRedirectParams($request),
        );
    }

    #[Route('/pipeline', name: 'app_recrutamento_pipeline')]
    public function pipeline(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $vagaId = (int) $request->query->get('vaga', 0);
        $filterVagaId = $vagaId > 0 ? $vagaId : null;
        $q = trim((string) $request->query->get('q', ''));
        $pipelineBoard = $this->recrutamento->buildPipelineBoard(
            $empresa,
            $filterVagaId,
            $q !== '' ? $q : null,
        );

        return $this->render(self::T . 'pipeline.html.twig', [
            'pipeline_board' => $pipelineBoard,
            'pipeline_stages' => RhCandidatoEtapa::boardStages(),
            'vagas' => $this->recrutamento->listVagas($empresa),
            'filter_vaga_id' => $filterVagaId,
            'filter_q' => $q,
            'has_filters' => $filterVagaId !== null || $q !== '',
            'pipeline_total' => array_sum(array_map('count', $pipelineBoard)),
        ]);
    }

    #[Route('/candidatos/{id}/etapa', name: 'app_recrutamento_candidato_etapa', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function candidatoEtapa(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $candidato = $this->candidatoRepo->findOneForEmpresa($id, $empresa);
        if (!$candidato) {
            throw $this->createNotFoundException();
        }

        $fromEtapa = $candidato->getEtapa();
        $toEtapa = (string) $request->request->get('etapa', '');

        try {
            $this->requireCsrf($request, 'recrutamento_candidato_etapa_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $result = $this->extended->moveEtapaWithPolicy($candidato, $toEtapa, $user);
            if ($result instanceof \App\Entity\RhCandidatoAprovacao) {
                $message = 'Solicitação de aprovação enviada para ' . RhCandidatoEtapa::label($toEtapa) . '.';
            } elseif ($toEtapa === RhCandidatoEtapa::CONTRATADO && $candidato->getOnboardingProcess()) {
                $message = 'Candidato movido para Contratado. Processo de admissão #'
                    . $candidato->getOnboardingProcess()->getId() . ' iniciado.';
                $this->extended->notifyHrisWebhook($candidato, 'contratado');
            } else {
                $message = 'Candidato movido para ' . RhCandidatoEtapa::label($toEtapa) . '.';
            }

            if ($this->wantsJson($request)) {
                return $this->pipelineMoveJsonResponse($empresa, $id, $fromEtapa, $toEtapa, $message, $request);
            }

            $this->addFlash('success', $message);
        } catch (RhProcessException $e) {
            if ($this->wantsJson($request)) {
                return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectAfterCandidatoAction($request, $id);
    }

    #[Route('/candidatos/{id}/reprovar', name: 'app_recrutamento_candidato_reprovar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function candidatoReprovar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $candidato = $this->candidatoRepo->findOneForEmpresa($id, $empresa);
        if (!$candidato) {
            throw $this->createNotFoundException();
        }

        $fromEtapa = $candidato->getEtapa();
        $toEtapa = RhCandidatoEtapa::REPROVADO;

        try {
            $this->requireCsrf($request, 'recrutamento_candidato_reprovar_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $this->recrutamento->rejectCandidato(
                $candidato,
                $user,
                $this->motivoReprovacaoFromRequest($request),
            );
            $this->emails->queueEtapaMudanca($candidato, $fromEtapa, $toEtapa);

            if ($this->wantsJson($request)) {
                return $this->pipelineMoveJsonResponse(
                    $empresa,
                    $id,
                    $fromEtapa,
                    $toEtapa,
                    'Candidato reprovado.',
                    $request,
                );
            }

            $this->addFlash('success', 'Candidato reprovado.');
        } catch (RhProcessException $e) {
            if ($this->wantsJson($request)) {
                return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectAfterCandidatoAction($request, $id);
    }

    #[Route('/vagas/{id}/candidato', name: 'app_recrutamento_candidato', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function candidato(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $vaga = $this->vagaRepo->findOneForEmpresa($id, $empresa);
        if (!$vaga) {
            throw $this->createNotFoundException();
        }

        $redirectTo = (string) $request->request->get('redirect_to', 'list');

        try {
            $this->requireCsrf($request, 'recrutamento_candidato');
            /** @var User $user */
            $user = $this->getUser();
            $this->recrutamento->addCandidato(
                $vaga,
                (string) $request->request->get('nome', ''),
                (string) $request->request->get('email', ''),
                (string) $request->request->get('telefone', ''),
                $user,
                (string) $request->request->get('origem', RhCandidatoOrigem::MANUAL),
                (string) $request->request->get('linkedin', ''),
            );
            $this->addFlash('success', 'Candidato adicionado.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        if ($redirectTo === 'show') {
            return $this->redirectToRoute('app_recrutamento_vagas_show', ['id' => $id]);
        }

        return $this->redirectToRoute('app_recrutamento_vagas', $this->vagasRedirectParams($request));
    }

    private function redirectPipeline(Request $request): Response
    {
        $params = [];
        $vaga = (int) $request->request->get('vaga', 0);
        $q = trim((string) $request->request->get('q', ''));
        if ($vaga > 0) {
            $params['vaga'] = $vaga;
        }
        if ($q !== '') {
            $params['q'] = $q;
        }

        return $this->redirectToRoute('app_recrutamento_pipeline', $params);
    }

    private function redirectAfterCandidatoAction(Request $request, int $candidatoId): Response
    {
        $redirectTo = (string) $request->request->get('redirect_to', 'pipeline');
        if ($redirectTo === 'show') {
            return $this->redirectToRoute('app_recrutamento_candidatos_show', ['id' => $candidatoId]);
        }
        if ($redirectTo === 'list') {
            return $this->redirectToRoute('app_recrutamento_candidatos', $this->candidatosRedirectParams($request));
        }

        return $this->redirectPipeline($request);
    }

    /** @return array<string, int|string> */
    private function candidatosRedirectParams(Request $request): array
    {
        $params = [];
        $vaga = (int) $request->request->get('vaga', $request->query->get('vaga', 0));
        $etapa = trim((string) $request->request->get('etapa', $request->query->get('etapa', '')));
        $origem = trim((string) $request->request->get('origem', $request->query->get('origem', '')));
        $q = trim((string) $request->request->get('q', $request->query->get('q', '')));
        if ($vaga > 0) {
            $params['vaga'] = $vaga;
        }
        if ($etapa !== '') {
            $params['etapa'] = $etapa;
        }
        if ($origem !== '') {
            $params['origem'] = $origem;
        }
        if ($q !== '') {
            $params['q'] = $q;
        }

        return $params;
    }

    /** @return array<string, string> */
    private function origemFilterOptions(): array
    {
        $options = ['' => 'Todas as origens'];
        foreach (RhCandidatoOrigem::ALL as $origem) {
            $options[$origem] = RhCandidatoOrigem::label($origem);
        }

        return $options;
    }

    /** @return array<string, string> */
    private function etapaFilterOptions(): array
    {
        $options = ['' => 'Todas as etapas'];
        foreach (RhCandidatoEtapa::BOARD_ORDER as $etapa) {
            $options[$etapa] = RhCandidatoEtapa::label($etapa);
        }

        return $options;
    }

    private function canManageVagas(): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->grants->grantAtLeast($user, 'hub_recrutamento', 'vagas', 'GESTOR_EQUIPE')
            || $this->grants->grantAtLeast($user, 'product_rh', 'recrutamento', 'GESTOR_EQUIPE');
    }

    /** @return array<string, string> */
    private function vagasRedirectParams(Request $request, string $queryStatus = '', string $queryQ = ''): array
    {
        $params = [];
        $status = (string) $request->request->get('redirect_status', $request->query->get('status', $queryStatus));
        $q = trim((string) $request->request->get('redirect_q', $request->query->get('q', $queryQ)));
        if ($status !== '') {
            $params['status'] = $status;
        }
        if ($q !== '') {
            $params['q'] = $q;
        }

        return $params;
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return [
            '' => 'Todas',
            RhVaga::STATUS_ABERTA => 'Abertas',
            RhVaga::STATUS_PAUSADA => 'Pausadas',
            RhVaga::STATUS_FECHADA => 'Fechadas',
        ];
    }

    private function wantsJson(Request $request): bool
    {
        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return true;
        }

        $accept = (string) $request->headers->get('Accept', '');

        return str_contains($accept, 'application/json');
    }

    /** @return array{vaga: ?int, q: ?string} */
    private function pipelineFiltersFromRequest(Request $request): array
    {
        $vaga = (int) $request->request->get('vaga', 0);
        $q = trim((string) $request->request->get('q', ''));

        return [
            'vaga' => $vaga > 0 ? $vaga : null,
            'q' => $q !== '' ? $q : null,
        ];
    }

    private function pipelineMoveJsonResponse(
        \App\Entity\Empresa $empresa,
        int $candidatoId,
        string $fromEtapa,
        string $toEtapa,
        string $message,
        Request $request,
    ): JsonResponse {
        $filters = $this->pipelineFiltersFromRequest($request);
        $candidato = $this->candidatoRepo->findOneForEmpresa($candidatoId, $empresa);
        if (!$candidato) {
            return new JsonResponse(['ok' => false, 'error' => 'Candidato não encontrado.'], 404);
        }

        $board = $this->recrutamento->buildPipelineBoard($empresa, $filters['vaga'], $filters['q']);
        $counts = [];
        foreach (RhCandidatoEtapa::BOARD_ORDER as $stage) {
            $counts[$stage] = \count($board[$stage] ?? []);
        }

        $canManage = $this->canManagePipeline();
        $user = $this->getUser();
        $canViewAdmissoes = $user instanceof User
            && $this->grants->canViewProductForUi($user, 'product_rh', 'admissoes');

        $cardHtml = $this->renderView('components/recrutamento/pipeline_card.html.twig', [
            'candidato' => $candidato,
            'filter_vaga_id' => $filters['vaga'],
            'filter_q' => $filters['q'] ?? '',
            'can_manage' => $canManage,
            'can_view_admissoes' => $canViewAdmissoes,
        ]);

        return new JsonResponse([
            'ok' => true,
            'message' => $message,
            'candidato_id' => $candidatoId,
            'from_etapa' => $fromEtapa,
            'to_etapa' => $toEtapa,
            'card_html' => $cardHtml,
            'counts' => $counts,
        ]);
    }

    private function canManagePipeline(): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->grants->grantAtLeast($user, 'hub_recrutamento', 'pipeline', 'GESTOR_EQUIPE')
            || $this->grants->grantAtLeast($user, 'product_rh', 'recrutamento', 'GESTOR_EQUIPE');
    }

    private function motivoReprovacaoFromRequest(Request $request): string
    {
        $motivo = trim((string) $request->request->get('motivo_reprovacao', ''));
        $detalhe = trim((string) $request->request->get('motivo_detalhe', ''));
        if ($detalhe === '') {
            return $motivo;
        }

        return $motivo !== '' ? $motivo . ' — ' . $detalhe : $detalhe;
    }
}
