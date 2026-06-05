<?php

namespace App\Controller\Module\Integracoes;

use App\Entity\IntegMapeamento;
use App\Entity\IntegSchemaDrift;
use App\Entity\User;
use App\Repository\IntegApiKeyRepository;
use App\Repository\IntegConectorRepository;
use App\Repository\IntegSchemaDriftRepository;
use App\Service\Integracoes\IntegracaoApiKeyService;
use App\Service\Integracoes\IntegracaoConectorService;
use App\Service\Integracoes\IntegracaoCortexService;
use App\Service\Integracoes\IntegracaoDeadLetterService;
use App\Service\Integracoes\IntegracaoEventBusService;
use App\Service\Integracoes\IntegracaoHealthService;
use App\Service\Integracoes\IntegracaoLogService;
use App\Service\Integracoes\IntegracaoMapeamentoService;
use App\Service\Integracoes\IntegracaoPlaybookRunService;
use App\Service\Integracoes\IntegracaoShadowReplayService;
use App\Service\Integracoes\IntegracaoWebhookService;
use App\Service\WorkspaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class IntegracoesManageController extends AbstractController
{
    use IntegracoesEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private IntegracaoConectorService $conectores,
        private IntegracaoWebhookService $webhooks,
        private IntegracaoApiKeyService $apiKeys,
        private IntegracaoMapeamentoService $mapeamentos,
        private IntegracaoShadowReplayService $shadowReplay,
        private IntegracaoHealthService $health,
        private IntegracaoCortexService $cortex,
        private IntegracaoDeadLetterService $deadLetter,
        private IntegracaoLogService $logService,
        private IntegracaoPlaybookRunService $playbookRuns,
        private IntegracaoEventBusService $eventBus,
        private IntegSchemaDriftRepository $driftRepo,
        private EntityManagerInterface $em,
        private IntegApiKeyRepository $apiKeyRepo,
        private IntegConectorRepository $conectorRepo,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[IsGranted('PUBLIC_ACCESS')]
    #[Route('/api/integracoes/webhook/{conectorId}', name: 'app_integracoes_webhook_receiver', methods: ['POST'])]
    public function webhookReceiver(Request $request, int $conectorId): JsonResponse
    {
        // Auth: check Authorization: Bearer {api_key} header
        $authHeader = $request->headers->get('Authorization', '');
        $webhookToken = $request->query->get('X-Webhook-Token', '');
        $conector = null;
        $empresa = null;

        if (str_starts_with($authHeader, 'Bearer ')) {
            $plainKey = substr($authHeader, 7);
            $keyHash = hash('sha256', $plainKey);
            $apiKey = $this->apiKeyRepo->findOneBy(['hash' => $keyHash, 'revogadaEm' => null]);
            if ($apiKey === null) {
                return new JsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
            }
            $empresa = $apiKey->getEmpresa();
            $conector = $this->conectorRepo->findOneForEmpresa($empresa, $conectorId);
        } elseif ($webhookToken !== '') {
            // Token lookup via IntegConector::configNotas
            $conector = $this->conectorRepo->find($conectorId);
            if ($conector === null) {
                return new JsonResponse(['ok' => false, 'error' => 'Not found'], 404);
            }
            // configNotas stores the webhook token for validation
            $storedToken = $conector->getConfigNotas();
            if ($storedToken === null || !hash_equals($storedToken, $webhookToken)) {
                return new JsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
            }
            $empresa = $conector->getEmpresa();
        } else {
            return new JsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        if ($conector === null || $conector->getEmpresa()->getId() !== $empresa->getId()) {
            return new JsonResponse(['ok' => false, 'error' => 'Conector não encontrado.'], 404);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $payloadSummary = 'Payload: ' . substr(json_encode($body) ?: '{}', 0, 100);

        $this->health->runChecksForConector($empresa, $conector);
        $this->logService->info($empresa, 'Webhook recebido: ' . $payloadSummary, $conector->getNome(), $conector);
        $conector->setEventos24h($conector->getEventos24h() + 1);
        $this->em->flush();

        return new JsonResponse(['ok' => true, 'received' => true, 'events' => $conector->getEventos24h()]);
    }

    #[Route('/integracoes/catalogo/{catalogoId}/ativar', name: 'app_integracoes_catalogo_ativar', methods: ['POST'])]
    public function ativarCatalogo(string $catalogoId, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_catalogo_ativar_' . $catalogoId);

        try {
            $this->conectores->activateFromCatalog($empresa, $catalogoId, $request->request->all());
            $this->addFlash('success', 'Conector ativado com sucesso!');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_integracoes_conectores');
    }

    #[Route('/integracoes/conectores/novo', name: 'app_integracoes_conector_novo_submit', methods: ['POST'])]
    public function conectorNovo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_conector_form');

        try {
            $this->conectores->createManual($empresa, $request->request->all());
            $this->addFlash('success', 'Conector cadastrado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_integracoes_conectores', ['open_novo' => '1']);
        }

        return $this->redirectToRoute('app_integracoes_conectores');
    }

    #[Route('/integracoes/conectores/{id}/editar', name: 'app_integracoes_conector_editar_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function conectorEditar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_conector_form');

        try {
            $conector = $this->conectores->loadForEmpresa($empresa, $id);
            $this->conectores->update($conector, $request->request->all());
            $this->addFlash('success', 'Conector atualizado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_integracoes_conectores', ['open_edit' => $id]);
        }

        return $this->redirectToRoute('app_integracoes_conectores');
    }

    #[Route('/integracoes/conectores/{id}/excluir', name: 'app_integracoes_conector_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function conectorExcluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_conector_delete_' . $id);
        $this->conectores->delete($this->conectores->loadForEmpresa($empresa, $id));
        $this->addFlash('success', 'Conector removido.');

        return $this->redirectToRoute('app_integracoes_conectores');
    }

    #[Route('/integracoes/conectores/{id}/testar', name: 'app_integracoes_conector_testar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function conectorTestar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_conector_test_' . $id);

        $conector = $this->conectores->loadForEmpresa($empresa, $id);
        $ok = $this->health->testConector($empresa, $conector);
        $this->addFlash($ok ? 'success' : 'warning', $ok ? 'Teste de conexão OK.' : 'Teste falhou — verifique logs.');

        return $this->redirectToRoute('app_integracoes_conectores');
    }

    #[Route('/integracoes/conectores/{id}/pausar', name: 'app_integracoes_conector_pausar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function conectorPausar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_conector_pause_' . $id);
        $this->conectores->togglePause($this->conectores->loadForEmpresa($empresa, $id));
        $this->addFlash('success', 'Status operacional atualizado.');

        return $this->redirectToRoute('app_integracoes_conectores');
    }

    #[Route('/integracoes/webhooks/novo', name: 'app_integracoes_webhook_novo_submit', methods: ['POST'])]
    public function webhookNovo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_webhook_form');

        try {
            $this->webhooks->create($empresa, $request->request->all());
            $this->addFlash('success', 'Webhook criado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_integracoes_webhooks', ['open_novo' => '1']);
        }

        return $this->redirectToRoute('app_integracoes_webhooks');
    }

    #[Route('/integracoes/webhooks/{id}/editar', name: 'app_integracoes_webhook_editar_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function webhookEditar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_webhook_form');

        try {
            $webhook = $this->webhooks->loadForEmpresa($empresa, $id);
            $this->webhooks->update($empresa, $webhook, $request->request->all());
            $this->addFlash('success', 'Webhook atualizado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_integracoes_webhooks', ['open_edit' => $id]);
        }

        return $this->redirectToRoute('app_integracoes_webhooks');
    }

    #[Route('/integracoes/webhooks/{id}/excluir', name: 'app_integracoes_webhook_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function webhookExcluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_webhook_delete_' . $id);
        $this->webhooks->delete($this->webhooks->loadForEmpresa($empresa, $id));
        $this->addFlash('success', 'Webhook excluído.');

        return $this->redirectToRoute('app_integracoes_webhooks');
    }

    #[Route('/integracoes/webhooks/{id}/toggle', name: 'app_integracoes_webhook_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function webhookToggle(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_webhook_toggle_' . $id);
        $this->webhooks->toggle($this->webhooks->loadForEmpresa($empresa, $id));
        $this->addFlash('success', 'Webhook atualizado.');

        return $this->redirectToRoute('app_integracoes_webhooks');
    }

    #[Route('/integracoes/api/nova', name: 'app_integracoes_api_nova_submit', methods: ['POST'])]
    public function apiNova(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_api_key_form');

        try {
            $result = $this->apiKeys->create($empresa, $request->request->all());
            $this->addFlash('success', 'Chave criada. Copie agora — não será exibida novamente: ' . $result['plain']);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_integracoes_api', ['open_novo' => '1']);
        }

        return $this->redirectToRoute('app_integracoes_api');
    }

    #[Route('/integracoes/api/{id}/revogar', name: 'app_integracoes_api_revogar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function apiRevogar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_api_revoke_' . $id);
        $this->apiKeys->revoke($this->apiKeys->loadForEmpresa($empresa, $id));
        $this->addFlash('success', 'Chave revogada.');

        return $this->redirectToRoute('app_integracoes_api');
    }

    #[Route('/integracoes/mapeamentos/novo', name: 'app_integracoes_mapeamento_novo_submit', methods: ['POST'])]
    public function mapeamentoNovo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_mapeamento_form');

        try {
            $this->mapeamentos->create($empresa, $request->request->all());
            $this->addFlash('success', 'Mapeamento criado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_integracoes_mapeamentos', ['open_novo' => '1']);
        }

        return $this->redirectToRoute('app_integracoes_mapeamentos');
    }

    #[Route('/integracoes/mapeamentos/{id}/editar', name: 'app_integracoes_mapeamento_editar_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function mapeamentoEditar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_mapeamento_form');

        try {
            $map = $this->mapeamentos->loadForEmpresa($empresa, $id);
            $this->mapeamentos->update($map, $request->request->all());
            $this->addFlash('success', 'Mapeamento atualizado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_integracoes_mapeamentos', ['open_edit' => $id]);
        }

        return $this->redirectToRoute('app_integracoes_mapeamentos');
    }

    #[Route('/integracoes/mapeamentos/{id}/excluir', name: 'app_integracoes_mapeamento_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function mapeamentoExcluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_mapeamento_delete_' . $id);
        $this->mapeamentos->delete($this->mapeamentos->loadForEmpresa($empresa, $id));
        $this->addFlash('success', 'Mapeamento excluído.');

        return $this->redirectToRoute('app_integracoes_mapeamentos');
    }

    #[Route('/integracoes/observatorio/shadow-replay', name: 'app_integracoes_observatorio_shadow', methods: ['POST'])]
    public function observatorioShadowReplay(Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_cortex_shadow');

        try {
            $result = $this->shadowReplay->runSimulation(
                $empresa,
                (int) $request->request->get('mapeamento_id'),
                (string) $request->request->get('campo_destino_proposto'),
                (int) $request->request->get('periodo_dias', 7),
            );

            return $this->json(['ok' => true, 'run' => $result]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    #[Route('/integracoes/drift/{id}/resolver', name: 'app_integracoes_drift_resolver', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resolverDrift(int $id, Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integracoes_drift_' . $id);

        $drift = $this->driftRepo->findOneBy(['id' => $id, 'empresa' => $empresa]);
        if (!$drift) {
            return $this->json(['ok' => false, 'error' => 'Drift não encontrado.'], 404);
        }

        $acao = (string) $request->request->get('acao');
        $resolucao = match ($acao) {
            'aceitar' => 'aceito',
            'ignorar' => 'ignorado',
            'sugerir_mapeamento' => 'mapeado',
            default => null,
        };

        if (!$resolucao) {
            return $this->json(['ok' => false, 'error' => 'Ação inválida.'], 422);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $drift->setResolucao($resolucao)
              ->setResolvidoEm(new \DateTimeImmutable())
              ->setResolvidoPor($user->getName() ?? $user->getEmail() ?? 'sistema')
              ->setResolvido(true);

        $mapeamentoId = null;
        if ($acao === 'sugerir_mapeamento' && $drift->getConector()) {
            $map = new IntegMapeamento();
            $map->setEmpresa($empresa)
                ->setConector($drift->getConector())
                ->setNome('Auto: ' . $drift->getCampoOrigem())
                ->setCampoOrigem($drift->getCampoOrigem())
                ->setCampoDestino($drift->getCampoDetectado())
                ->setAtivo(false);
            $this->em->persist($map);
            $mapeamentoId = null; // will be set after flush
        }

        $this->em->flush();

        return $this->json([
            'ok' => true,
            'resolucao' => $resolucao,
            'mapeamento_id' => $mapeamentoId,
        ]);
    }

    #[Route('/integracoes/logs/export', name: 'app_integracoes_logs_export', methods: ['GET'])]
    public function logsExport(): Response
    {
        $empresa = $this->requireEmpresa();
        $logs = $this->logService->exportCsv($empresa);

        $csv = "id,hora,nivel,origem,mensagem,trace_id\n";
        foreach ($logs as $log) {
            $row = $log->toArray();
            $csv .= implode(',', [
                $row['db_id'],
                '"' . $row['time'] . '"',
                $row['level'],
                '"' . str_replace('"', '""', $row['origem']) . '"',
                '"' . str_replace('"', '""', $row['mensagem']) . '"',
                '"' . ($row['trace_id'] ?? '') . '"',
            ]) . "\n";
        }

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="logs-integracoes-' . date('Y-m-d') . '.csv"',
        ]);
    }

    #[Route('/integracoes/dead-letter/{id}/retry', name: 'app_integracoes_dead_letter_retry', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deadLetterRetry(int $id, Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_dl_retry_' . $id);

        $dl = $this->deadLetter->findForEmpresaById($empresa, $id);
        if (!$dl) {
            return $this->json(['ok' => false, 'error' => 'Registro não encontrado.'], 404);
        }

        $this->deadLetter->retry($dl);

        return $this->json([
            'ok' => true,
            'status' => $dl->getStatus(),
            'tentativas' => $dl->getTentativas(),
            'proxima_retry' => $dl->getProximaRetryEm()?->format('d/m H:i'),
        ]);
    }

    #[Route('/integracoes/dead-letter/{id}/descartar', name: 'app_integracoes_dead_letter_descartar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deadLetterDescartar(int $id, Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integ_dl_descartar_' . $id);

        $dl = $this->deadLetter->findForEmpresaById($empresa, $id);
        if (!$dl) {
            return $this->json(['ok' => false, 'error' => 'Registro não encontrado.'], 404);
        }

        $this->deadLetter->discard($dl);

        return $this->json(['ok' => true]);
    }

    #[Route('/integracoes/conectores/{id}/simular-impacto', name: 'app_integracoes_simular_impacto', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function simularImpacto(int $id, Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'integracoes_simular_' . $id);

        $result = $this->cortex->simularImpacto($empresa, $id);

        return $this->json($result);
    }

    // ── Playbook Runs ─────────────────────────────────────────────────────────

    #[Route('/integracoes/playbooks/{playbookId}/iniciar', name: 'app_integracoes_playbook_iniciar', methods: ['POST'])]
    public function iniciarPlaybook(string $playbookId, Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();

        /** @var User $user */
        $user = $this->getUser();

        try {
            $run = $this->playbookRuns->iniciar($empresa, $playbookId, $user);

            return $this->json(['ok' => true, 'run' => $run->toArray()]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    #[Route('/integracoes/playbooks/run/{runId}/step', name: 'app_integracoes_playbook_step', methods: ['POST'])]
    public function playbookStep(int $runId, Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();

        try {
            $run = $this->playbookRuns->load($empresa, $runId);
            $stepIndex = (int) $request->request->get('step_index');
            $done = filter_var($request->request->get('done', true), FILTER_VALIDATE_BOOLEAN);
            $evidencia = $request->request->get('evidencia') ?: null;
            $this->playbookRuns->markStep($run, $stepIndex, $done, $evidencia);

            return $this->json(['ok' => true, 'run' => $run->toArray()]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    // ── Event Bus ─────────────────────────────────────────────────────────────

    #[Route('/integracoes/eventos/publicar', name: 'app_integracoes_evento_publicar', methods: ['POST'])]
    public function publicarEvento(Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();

        try {
            $tipo = (string) $request->request->get('tipo', '');
            $payloadRaw = (string) $request->request->get('payload', '{}');
            $origem = $request->request->get('origem') ?: null;

            if ($tipo === '') {
                return $this->json(['ok' => false, 'error' => 'Campo "tipo" é obrigatório.'], 422);
            }

            $payload = json_decode($payloadRaw, true) ?? [];
            $event = $this->eventBus->publish($empresa, $tipo, $payload, $origem);

            return $this->json(['ok' => true, 'event' => $event->toArray()]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
