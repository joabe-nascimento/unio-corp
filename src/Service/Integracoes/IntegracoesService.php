<?php

namespace App\Service\Integracoes;

use App\Config\IntegracaoCatalogRegistry;
use App\Config\IntegrationsModuleRegistry;
use App\Entity\Empresa;
use App\Entity\IntegConector;
use App\Entity\User;
use App\Repository\IntegConectorRepository;
use App\Repository\IntegLogRepository;
use App\Repository\IntegWebhookRepository;
use App\Service\WorkspaceService;

final class IntegracoesService
{
    public function __construct(
        private WorkspaceService $workspace,
        private IntegracaoSeedService $seed,
        private IntegracaoHealthService $health,
        private IntegracaoConectorService $conectores,
        private IntegracaoWebhookService $webhooks,
        private IntegracaoApiKeyService $apiKeys,
        private IntegracaoMapeamentoService $mapeamentos,
        private IntegracaoCortexService $cortex,
        private IntegracaoPlaybookRunService $playbookRuns,
        private IntegracaoEventBusService $eventBus,
        private IntegConectorRepository $conectorRepo,
        private IntegWebhookRepository $webhookRepo,
        private IntegLogRepository $logRepo,
    ) {}

    /** @return array<string, mixed> */
    public function getDashboard(User $user): array
    {
        $empresa = $this->requireEmpresa($user);
        $this->health->runChecks($empresa);

        $conectores = $this->conectores->listForEmpresa($empresa);
        $activeIds = array_column($conectores, 'catalogo_id');
        $catalog = $this->buildCatalog($activeIds);

        return [
            'integ_section' => 'overview',
            'integ_active_module' => null,
            'kpis' => $this->buildKpis($empresa, $conectores),
            'integration_alerts' => $this->health->alerts($empresa),
            'integrations' => $conectores,
            'integration_logs' => $this->getRecentLogs($empresa),
            'module_cards' => $this->getModuleCards($empresa, $conectores),
            'catalog_preview' => \array_slice($catalog, 0, 6),
            'catalog_total' => \count(IntegracaoCatalogRegistry::all()),
            'playbooks_preview' => \array_slice(IntegracaoCatalogRegistry::playbooks(), 0, 3),
            'event_timeline' => $this->getRecentLogs($empresa, 8),
            'health_score' => $this->health->computeOverallHealth($empresa),
            'cortex_preview' => $this->cortexPreview($empresa),
        ];
    }

    /** @return array<string, mixed> */
    public function getSection(string $section, User $user): array
    {
        $base = $this->getDashboard($user);
        $base['integ_section'] = $section;
        $base['integ_active_module'] = IntegrationsModuleRegistry::findById($section);

        $empresa = $this->requireEmpresa($user);

        if ($section === 'catalogo') {
            $activeIds = array_column($base['integrations'], 'catalogo_id');
            $base['catalog_items'] = $this->buildCatalog($activeIds);
            $base['catalog_categorias'] = IntegracaoCatalogRegistry::CATEGORIAS;
        }

        if ($section === 'conectores') {
            $base['conectores'] = $base['integrations'];
        }

        if ($section === 'webhooks') {
            $base['webhooks'] = $this->webhooks->listForEmpresa($empresa);
            $base['conectores_options'] = $base['integrations'];
        }

        if ($section === 'mapeamentos') {
            $base['mapeamentos'] = $this->mapeamentos->listForEmpresa($empresa);
            $base['conectores_options'] = $base['integrations'];
        }

        if ($section === 'api_keys') {
            $base['api_keys'] = $this->apiKeys->listForEmpresa($empresa);
            $base['api_scopes'] = $this->getApiScopes();
        }

        if ($section === 'logs') {
            $base['logs'] = $this->getRecentLogs($empresa, 100);
            $base['log_stats'] = [
                'today' => $this->logRepo->countTodayForEmpresa($empresa),
                'errors' => $this->logRepo->countErrorsOpen($empresa),
            ];
        }

        if ($section === 'playbooks') {
            $base['playbooks'] = IntegracaoCatalogRegistry::playbooks();
            $base['playbook_runs'] = $this->playbookRuns->list($empresa);
        }

        if ($section === 'observatorio') {
            $this->eventBus->seedDemoEvents($empresa);
            $base = array_merge($base, $this->cortex->getObservatorio($empresa));
            $base['domain_events'] = $this->eventBus->recentEvents($empresa, 10);
        }

        return $base;
    }

    private function requireEmpresa(User $user): Empresa
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw new \RuntimeException('Selecione uma área de trabalho para acessar o Núcleo Integrações.');
        }
        $this->seed->seedDemoData($empresa, $user);

        return $empresa;
    }

    /** @param list<string> $activeIds
     * @return list<array<string, mixed>>
     */
    private function buildCatalog(array $activeIds): array
    {
        $items = [];
        foreach (IntegracaoCatalogRegistry::all() as $item) {
            $items[] = array_merge($item, [
                'ativo' => \in_array($item['id'], $activeIds, true),
            ]);
        }

        return $items;
    }

    /** @param list<array<string, mixed>> $conectores
     * @return list<array<string, mixed>>
     */
    private function buildKpis(Empresa $empresa, array $conectores): array
    {
        $total = \count($conectores);
        $healthy = \count(array_filter($conectores, static fn ($c) => ($c['health'] ?? '') === 'healthy'));
        $eventsToday = $this->logRepo->countTodayForEmpresa($empresa);
        $webhooks = $this->webhookRepo->countActiveForEmpresa($empresa);
        $errors = $this->logRepo->countErrorsOpen($empresa);

        return [
            ['value' => $healthy . '/' . $total, 'label' => 'Conectores saudáveis', 'icon' => 'fa-plug', 'route' => 'app_integracoes_conectores'],
            ['value' => $this->health->computeOverallHealth($empresa) . '%', 'label' => 'Saúde geral', 'icon' => 'fa-heart-pulse', 'route' => 'app_integracoes_conectores'],
            ['value' => number_format($eventsToday, 0, ',', '.'), 'label' => 'Eventos hoje', 'icon' => 'fa-bolt', 'route' => 'app_integracoes_logs'],
            ['value' => $webhooks, 'label' => 'Webhooks ativos', 'icon' => 'fa-link', 'route' => 'app_integracoes_webhooks'],
            ['value' => $errors, 'label' => 'Falhas (24h)', 'icon' => 'fa-triangle-exclamation', 'route' => 'app_integracoes_logs'],
        ];
    }

    /** @param list<array<string, mixed>> $conectores
     * @return list<array<string, mixed>>
     */
    private function getModuleCards(Empresa $empresa, array $conectores): array
    {
        return [
            ['id' => 'observatorio', 'title' => 'Observatório Causal', 'subtitle' => 'Malha de fluxos cross-núcleo', 'icon' => 'fa-diagram-project', 'metric' => 'Cortex', 'route' => 'app_integracoes_observatorio', 'tone' => '#6366F1'],
            ['id' => 'catalogo', 'title' => 'Catálogo', 'subtitle' => 'Conectores disponíveis', 'icon' => 'fa-store', 'metric' => \count(IntegracaoCatalogRegistry::all()) . ' opções', 'route' => 'app_integracoes_catalogo', 'tone' => '#6366F1'],
            ['id' => 'conectores', 'title' => 'Meus conectores', 'subtitle' => 'Integrações ativas', 'icon' => 'fa-plug', 'metric' => \count($conectores) . ' ativos', 'route' => 'app_integracoes_conectores', 'tone' => '#10B981'],
            ['id' => 'webhooks', 'title' => 'Webhooks', 'subtitle' => 'Eventos e automações', 'icon' => 'fa-bolt', 'metric' => $this->webhookRepo->countActiveForEmpresa($empresa) . ' endpoints', 'route' => 'app_integracoes_webhooks', 'tone' => '#F59E0B'],
            ['id' => 'mapeamentos', 'title' => 'Mapeamentos', 'subtitle' => 'Campos e transformações', 'icon' => 'fa-right-left', 'metric' => \count($this->mapeamentos->listForEmpresa($empresa)) . ' regras', 'route' => 'app_integracoes_mapeamentos', 'tone' => '#8B5CF6'],
            ['id' => 'api_keys', 'title' => 'API & chaves', 'subtitle' => 'Acesso programático', 'icon' => 'fa-key', 'metric' => \count($this->apiKeys->listForEmpresa($empresa)) . ' chaves', 'route' => 'app_integracoes_api', 'tone' => '#EC4899'],
            ['id' => 'logs', 'title' => 'Logs', 'subtitle' => 'Auditoria e eventos', 'icon' => 'fa-list-ul', 'metric' => $this->logRepo->countTodayForEmpresa($empresa) . ' hoje', 'route' => 'app_integracoes_logs', 'tone' => '#64748B'],
            ['id' => 'playbooks', 'title' => 'Playbooks', 'subtitle' => 'Guias prontos', 'icon' => 'fa-book-open', 'metric' => \count(IntegracaoCatalogRegistry::playbooks()) . ' guias', 'route' => 'app_integracoes_playbooks', 'tone' => '#0EA5E9'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function getRecentLogs(Empresa $empresa, int $limit = 50): array
    {
        return array_map(
            static fn ($log) => $log->toArray(),
            $this->logRepo->findRecentForEmpresa($empresa, $limit),
        );
    }

    /** @return array<string, mixed>|null */
    private function cortexPreview(Empresa $empresa): ?array
    {
        return $this->cortex->getPreview($empresa);
    }

    /** @return array{total: int, page: int, limit: int, items: list<array<string, mixed>>} */
    public function getLogsFiltered(Empresa $empresa, array $filters = [], int $page = 1, int $limit = 50): array
    {
        $result = $this->logRepo->findForEmpresaFiltered($empresa, $filters, $page, $limit);

        return [
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
            'items' => array_map(static fn ($log) => $log->toArray(), $result['items']),
        ];
    }

    public function getFirstConector(Empresa $empresa): ?IntegConector
    {
        $list = $this->conectorRepo->findForEmpresa($empresa);

        return $list[0] ?? null;
    }

    /** @return list<array{id: string, label: string}> */
    private function getApiScopes(): array
    {
        return [
            ['id' => 'read:hub', 'label' => 'Leitura geral'],
            ['id' => 'read:rh', 'label' => 'RH — leitura'],
            ['id' => 'write:rh', 'label' => 'RH — escrita'],
            ['id' => 'read:pessoas', 'label' => 'Pessoas — leitura'],
            ['id' => 'read:ti', 'label' => 'TI — leitura'],
            ['id' => 'webhook:manage', 'label' => 'Gerenciar webhooks'],
        ];
    }
}
