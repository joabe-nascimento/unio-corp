<?php

namespace App\Controller\Module\Ti;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\TiChamadoAnexoRepository;
use App\Security\TiGrantService;
use App\Service\Ti\TiHeliaService;
use App\Service\Ti\TiChamadoService;
use App\Service\Ti\TiChamadoAttachmentService;
use App\Service\Ti\TiPlaybookService;
use App\Service\Ti\TiReferenceData;
use App\Service\Ti\TiService;
use App\Service\WorkspaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ti/chamados')]
#[IsGranted('ROLE_USER')]
final class TiChamadoController extends AbstractController
{
    private const T = 'modules/ti/';

    public function __construct(
        private WorkspaceService $workspace,
        private TiChamadoService $chamados,
        private TiService $service,
        private TiHeliaService $helia,
        private TiGrantService $tiGrants,
        private EntityManagerInterface $em,
        private TiChamadoAnexoRepository $anexoRepository,
        private TiPlaybookService $playbooks,
    ) {}

    #[Route('/{id}', name: 'app_ti_chamado_show', requirements: ['id' => 'TK-\d+'], methods: ['GET'])]
    public function show(string $id): Response
    {
        $empresa = $this->requireEmpresa();
        $ticket = $this->chamados->find($empresa, $id);
        if ($ticket === null) {
            throw $this->createNotFoundException('Chamado não encontrado.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canViewChamado($user, $ticket), 'Sem permissão para ver este chamado.');

        $section = $this->tiGrants->canOperateChamados($user) ? 'chamados' : 'meus_chamados';
        $base = $this->service->getSection($section, $user);

        return $this->render(self::T . 'chamado_show.html.twig', array_merge($base, [
            'ticket' => $ticket,
            'ti_portal_view' => !$this->tiGrants->canOperateChamados($user),
        ], $this->service->ticketDetailContext($ticket, $user)));
    }

    #[Route('/helia/analyze', name: 'app_ti_helia_analyze', methods: ['POST'])]
    public function heliaAnalyze(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('ti_helia_analyze', (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token inválido.'], 403);
        }

        return new JsonResponse($this->helia->analyzeInput($request->request->all(), $this->requireEmpresa()));
    }

    #[Route('/novo/submit', name: 'app_ti_chamado_novo_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canCreateChamado($user));
        $empresa = $this->requireEmpresa();

        if (!$this->isCsrfTokenValid('ti_novo_chamado', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }

        try {
            $files = $this->normalizeUploadedFiles($request->files->get('attachments'));
            $ticket = $this->chamados->create($empresa, $user, $request->request->all(), $files);
            $this->addFlash('success', 'Chamado ' . $ticket['id'] . ' aberto com sucesso.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_ti_chamados', ['open_novo' => 1]);
        }

        if ($request->request->get('redirect') === 'list') {
            return $this->redirectToRoute('app_ti_chamados');
        }

        return $this->redirectToRoute('app_ti_chamado_show', ['id' => $ticket['id']]);
    }

    #[Route('/{id}/status', name: 'app_ti_chamado_status', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function updateStatus(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canOperateChamados($user));
        $empresa = $this->requireEmpresa();

        if (!$this->isCsrfTokenValid('ti_chamado_status_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }

        try {
            $ticket = $this->chamados->updateStatus($empresa, $id, (string) $request->request->get('status', ''), $user);
            if ($this->wantsJson($request)) {
                $labels = TiReferenceData::statusLabels();
                return new JsonResponse([
                    'ok' => true,
                    'ticket' => array_merge($ticket, [
                        'status_label' => $labels[$ticket['status'] ?? ''] ?? ($ticket['status'] ?? ''),
                    ]),
                ]);
            }
            $this->addFlash('success', 'Status atualizado.');
        } catch (\InvalidArgumentException $e) {
            if ($this->wantsJson($request)) {
                return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            $this->addFlash('error', $e->getMessage());
        }

        if ($this->wantsJson($request)) {
            return new JsonResponse(['ok' => true]);
        }

        return $this->redirectToRoute('app_ti_chamado_show', ['id' => $id]);
    }

    #[Route('/{id}/sla/pausa', name: 'app_ti_chamado_sla_pausa', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function slaPausa(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canPauseSla($user));
        $empresa = $this->requireEmpresa();
        if (!$this->isCsrfTokenValid('ti_chamado_sla_pausa_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }
        try {
            $this->chamados->toggleSlaPause($empresa, $id, $user, (string) $request->request->get('motivo', ''));
            $this->addFlash('success', 'SLA atualizado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('app_ti_chamado_show', ['id' => $id]);
    }

    #[Route('/{id}/helia/feedback', name: 'app_ti_chamado_helia_feedback', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function heliaFeedback(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa();
        $ticket = $this->chamados->find($empresa, $id);
        if ($ticket === null) {
            throw $this->createNotFoundException('Chamado não encontrado.');
        }
        $this->tiGrants->assert($user, $this->tiGrants->canViewChamado($user, $ticket));
        if (!$this->isCsrfTokenValid('ti_chamado_helia_feedback_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }
        try {
            $this->chamados->heliaFeedback($empresa, $id, $user, (string) $request->request->get('feedback', ''));
            $this->addFlash('success', 'Feedback registrado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('app_ti_chamado_show', ['id' => $id]);
    }

    #[Route('/{id}/excluir', name: 'app_ti_chamado_excluir', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function excluir(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canDeleteChamados($user));
        $empresa = $this->requireEmpresa();

        if (!$this->isCsrfTokenValid('ti_chamado_delete_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }

        try {
            $this->chamados->delete($empresa, $id);
            $this->addFlash('success', 'Chamado ' . $id . ' excluído.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_ti_chamados');
    }

    #[Route('/{id}/atribuir', name: 'app_ti_chamado_atribuir', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function atribuir(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canOperateChamados($user));
        $empresa = $this->requireEmpresa();

        if (!$this->isCsrfTokenValid('ti_chamado_atribuir_' . $id, (string) $request->request->get('_token'))) {
            if ($this->wantsJson($request)) {
                return new JsonResponse(['ok' => false, 'error' => 'Token inválido.'], 403);
            }
            throw $this->createAccessDeniedException('Token inválido.');
        }

        try {
            $ticket = $this->chamados->assignTechnician($empresa, $id, (int) $request->request->get('technician_id', 0), $user);
            if ($this->wantsJson($request)) {
                $labels = TiReferenceData::statusLabels();

                return new JsonResponse([
                    'ok' => true,
                    'ticket' => array_merge($ticket, [
                        'status_label' => $labels[$ticket['status'] ?? ''] ?? ($ticket['status'] ?? ''),
                    ]),
                ]);
            }
            $this->addFlash('success', 'Técnico atribuído.');
        } catch (\InvalidArgumentException $e) {
            if ($this->wantsJson($request)) {
                return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            $this->addFlash('error', $e->getMessage());
        }

        if ((string) $request->request->get('redirect') === 'list') {
            return $this->redirectToRoute('app_ti_chamados');
        }

        return $this->redirectToRoute('app_ti_chamado_show', ['id' => $id]);
    }

    #[Route('/{id}/problema', name: 'app_ti_chamado_problema', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function linkProblema(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canLinkProblema($user));
        $empresa = $this->requireEmpresa();
        if (!$this->isCsrfTokenValid('ti_chamado_problema_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }
        $problemaId = $request->request->get('problema_id');
        try {
            $this->chamados->linkProblema(
                $empresa,
                $id,
                $user,
                $problemaId !== null && $problemaId !== '' ? (int) $problemaId : null,
            );
            $this->addFlash('success', 'Problema vinculado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_ti_chamado_show', ['id' => $id]);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json')
            || $request->request->getBoolean('ajax');
    }

    #[Route('/{id}/anexo/{anexoId}', name: 'app_ti_chamado_anexo_download', requirements: ['id' => 'TK-\d+', 'anexoId' => '\d+'], methods: ['GET'])]
    public function downloadAnexo(string $id, int $anexoId, TiChamadoAttachmentService $attachments): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa();
        $chamado = $this->chamados->findEntity($empresa, $id);
        if ($chamado === null) {
            throw $this->createNotFoundException('Chamado não encontrado.');
        }

        $ticket = $this->chamados->find($empresa, $id);
        $this->tiGrants->assert($user, $this->tiGrants->canViewChamado($user, $ticket ?? []), 'Sem permissão.');

        $anexo = $this->anexoRepository->find($anexoId);
        if ($anexo === null || $anexo->getChamado()->getId() !== $chamado->getId() || $anexo->getEmpresa()->getId() !== $empresa->getId()) {
            throw $this->createNotFoundException('Anexo não encontrado.');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/' . ltrim($anexo->getCaminho(), '/');
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Arquivo não encontrado no servidor.');
        }

        $chamado->addTimelineEvent(
            'Anexo "' . $anexo->getNomeOriginal() . '" baixado',
            $user->getNome() ?: $user->getEmail() ?: 'Usuário',
        );
        $this->em->flush();

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $anexo->getNomeOriginal());
        $response->headers->set('Content-Type', $anexo->getMimeType());

        return $response;
    }

    #[Route('/{id}/csat', name: 'app_ti_chamado_csat', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function csat(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa();

        if (!$this->isCsrfTokenValid('ti_csat_' . $id, (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'Token inválido.'], 403);
        }

        $chamado = $this->chamados->findEntity($empresa, $id);
        if ($chamado === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Chamado não encontrado.'], 404);
        }

        if ($chamado->getSolicitante()->getId() !== $user->getId()) {
            return new JsonResponse(['ok' => false, 'error' => 'Apenas o solicitante pode avaliar.'], 403);
        }

        if ($chamado->getStatus() !== \App\Entity\TiChamado::STATUS_RESOLVIDO) {
            return new JsonResponse(['ok' => false, 'error' => 'CSAT disponível apenas para chamados resolvidos.'], 422);
        }

        if ($chamado->getCsatEm() !== null) {
            return new JsonResponse(['ok' => false, 'error' => 'CSAT já registrado.'], 422);
        }

        $score = (int) $request->request->get('score', 0);
        if ($score < 1 || $score > 5) {
            return new JsonResponse(['ok' => false, 'error' => 'Score deve ser entre 1 e 5.'], 422);
        }

        $comentario = trim((string) $request->request->get('comentario', ''));
        $chamado->setCsatScore($score)
            ->setCsatComentario($comentario !== '' ? $comentario : null)
            ->setCsatEm(new \DateTimeImmutable())
            ->addTimelineEvent('CSAT registrado: ' . $score . '/5', $user->getNome() ?: $user->getEmail() ?: 'Usuário')
            ->touch();
        $this->em->flush();

        return new JsonResponse(['ok' => true, 'score' => $score]);
    }

    #[Route('/{id}/playbook/step', name: 'app_ti_chamado_playbook_step', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function playbookStep(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa();

        if (!$this->isCsrfTokenValid('ti_playbook_' . $id, (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'Token inválido.'], 403);
        }

        $chamado = $this->chamados->findEntity($empresa, $id);
        if ($chamado === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Chamado não encontrado.'], 404);
        }

        $this->tiGrants->assert($user, $this->tiGrants->canOperateChamados($user), 'Sem permissão.');

        $stepNum = (int) $request->request->get('step', 0);
        $done = $request->request->getBoolean('done');
        $evidencia = trim((string) $request->request->get('evidencia', ''));

        $steps = $chamado->getPlaybookSteps();

        // Initialize from playbook if empty
        if ($steps === []) {
            $ticket = $this->chamados->find($empresa, $id);
            if ($ticket !== null) {
                $steps = $this->playbooks->initPlaybookSteps($ticket, $empresa);
            }
        }

        $found = false;
        $stepTitulo = 'Passo ' . $stepNum;
        foreach ($steps as &$step) {
            if ((int) ($step['step'] ?? 0) === $stepNum) {
                $step['feito'] = $done;
                $step['evidencia'] = $evidencia !== '' ? $evidencia : null;
                $step['feito_em'] = $done ? (new \DateTimeImmutable())->format('d/m/Y H:i') : null;
                $stepTitulo = $step['titulo'] ?? $stepTitulo;
                $found = true;
                break;
            }
        }
        unset($step);

        if (!$found) {
            $steps[] = [
                'step' => $stepNum,
                'titulo' => 'Passo ' . $stepNum,
                'feito' => $done,
                'evidencia' => $evidencia !== '' ? $evidencia : null,
                'feito_em' => $done ? (new \DateTimeImmutable())->format('d/m/Y H:i') : null,
            ];
        }

        $chamado->setPlaybookSteps($steps)
            ->addTimelineEvent(
                'Playbook — Passo ' . $stepNum . ' ' . ($done ? 'concluído' : 'desmarcado') . ': ' . $stepTitulo,
                $user->getNome() ?: $user->getEmail() ?: 'Usuário',
            )
            ->touch();
        $this->em->flush();

        return new JsonResponse(['ok' => true, 'steps' => $chamado->getPlaybookSteps()]);
    }

    #[Route('/{id}/nota', name: 'app_ti_chamado_nota', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function nota(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canOperateChamados($user));
        $empresa = $this->requireEmpresa();

        if (!$this->isCsrfTokenValid('ti_chamado_nota_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }

        try {
            $this->chamados->addNote($empresa, $id, (string) $request->request->get('note', ''), $user);
            $this->addFlash('success', 'Nota registrada.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_ti_chamado_show', ['id' => $id]);
    }

    #[Route('/{id}/prioridade', name: 'app_ti_chamado_prioridade', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function prioridade(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canManageChamados($user));
        $empresa = $this->requireEmpresa();

        if (!$this->isCsrfTokenValid('ti_chamado_prioridade_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }

        try {
            $this->chamados->escalatePriority($empresa, $id, $user);
            $this->addFlash('success', 'Prioridade escalada.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_ti_chamado_show', ['id' => $id]);
    }

    #[Route('/{id}/helia/aplicar', name: 'app_ti_chamado_helia_aplicar', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function heliaAplicar(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canApplyHelia($user));
        $empresa = $this->requireEmpresa();

        if (!$this->isCsrfTokenValid('ti_chamado_helia_aplicar_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }

        try {
            $this->chamados->applyHeliaSuggestion($empresa, $id, $user);
            $this->addFlash('success', 'Sugestão Helia aplicada.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $redirect = (string) $request->request->get('redirect', 'show');

        return $redirect === 'cortex'
            ? $this->redirectToRoute('app_ti_cortex')
            : $this->redirectToRoute('app_ti_chamado_show', ['id' => $id]);
    }

    #[Route('/{id}/helia/revisar', name: 'app_ti_chamado_helia_revisar', requirements: ['id' => 'TK-\d+'], methods: ['POST'])]
    public function heliaRevisar(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canApplyHelia($user));
        $empresa = $this->requireEmpresa();

        if (!$this->isCsrfTokenValid('ti_chamado_helia_revisar_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }

        try {
            $this->chamados->markHeliaReviewed($empresa, $id, $user);
            $this->addFlash('success', 'Triagem Helia marcada como revisada.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $redirect = (string) $request->request->get('redirect', 'show');

        return $redirect === 'cortex'
            ? $this->redirectToRoute('app_ti_cortex')
            : $this->redirectToRoute('app_ti_chamado_show', ['id' => $id]);
    }

    /** @return list<UploadedFile> */
    private function normalizeUploadedFiles(mixed $raw): array
    {
        if ($raw instanceof UploadedFile) {
            return $raw->isValid() ? [$raw] : [];
        }
        if (!\is_array($raw)) {
            return [];
        }

        return array_values(array_filter(
            $raw,
            static fn ($f) => $f instanceof UploadedFile && $f->isValid(),
        ));
    }

    private function requireEmpresa(): Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw new \RuntimeException('Selecione uma área de trabalho.');
        }

        return $empresa;
    }
}
