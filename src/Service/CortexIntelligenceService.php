<?php

namespace App\Service;

use App\Entity\DevProjeto;
use App\Entity\DevTarefa;
use App\Entity\Empresa;
use App\Entity\RhOnboardingProcess;
use App\Entity\User;
use App\Repository\DepartamentoRepository;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;
use App\Repository\EmpresaRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\RhFeriasRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Repository\UserRepository;

/**
 * Unio Cortex — grafo neural, pulso do sistema e insights acionáveis.
 */
final class CortexIntelligenceService
{
    private const DOMAIN_META = [
        'people' => ['label' => 'People', 'icon' => 'fa-users', 'color' => '#4F7FFF'],
        'rh' => ['label' => 'RH', 'icon' => 'fa-user-tie', 'color' => '#22c55e'],
        'delivery' => ['label' => 'Delivery', 'icon' => 'fa-rocket', 'color' => '#f59e0b'],
        'access' => ['label' => 'Access', 'icon' => 'fa-shield-halved', 'color' => '#a78bfa'],
        'signals' => ['label' => 'Signals', 'icon' => 'fa-bolt', 'color' => '#f472b6'],
    ];

    public function __construct(
        private NavigationService $navigation,
        private WelcomeNewsIntelligenceService $newsIntelligence,
        private FuncionarioRepository $funcionarioRepo,
        private DepartamentoRepository $departamentoRepo,
        private DevProjetoRepository $projetoRepo,
        private DevTarefaRepository $tarefaRepo,
        private UserRepository $userRepo,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhOffboardingProcessRepository $offboardingRepo,
        private RhFeriasRepository $feriasRepo,
        private EmpresaRepository $empresaRepo,
    ) {}

    /**
     * @return array{
     *     pulse: array<string, mixed>,
     *     graph: array<string, mixed>,
     *     insights: list<array<string, mixed>>,
     *     domains: list<array<string, mixed>>,
     *     meta: array{generated_at: string, empresa: ?string}
     * }
     */
    public function buildPayload(User $user, ?Empresa $empresa): array
    {
        $graph = $this->buildGraph($user, $empresa);
        $domains = $this->buildDomainSummaries($user, $empresa, $graph);
        $pulse = $this->buildPulse($domains);
        $insights = $this->buildInsights($user, $empresa);

        return [
            'pulse' => $pulse,
            'graph' => $graph,
            'insights' => $insights,
            'domains' => $domains,
            'meta' => [
                'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'empresa' => $empresa?->getNome(),
            ],
        ];
    }

    /**
     * @return array{
     *     categories: list<array{name: string, color: string}>,
     *     nodes: list<array<string, mixed>>,
     *     links: list<array{source: string, target: string, value?: int|float}>
     * }
     */
    private function buildGraph(User $user, ?Empresa $empresa): array
    {
        $categories = [];
        $nodes = [];
        $links = [];
        $catIndex = 0;

        foreach (self::DOMAIN_META as $domainId => $meta) {
            if (!$this->isDomainVisible($user, $empresa, $domainId)) {
                continue;
            }
            $categories[] = ['name' => $meta['label'], 'color' => $meta['color']];
            $domainNodes = $this->buildDomainNodes($user, $empresa, $domainId, $catIndex);
            if ($domainNodes['hub'] === null) {
                array_pop($categories);
                continue;
            }
            $nodes[] = $domainNodes['hub'];
            foreach ($domainNodes['children'] as $child) {
                $nodes[] = $child;
                $links[] = [
                    'source' => $domainNodes['hub']['id'],
                    'target' => $child['id'],
                    'value' => max(1, (int) ($child['value'] ?? 1)),
                ];
            }
            foreach ($domainNodes['inner'] as $link) {
                $links[] = $link;
            }
            ++$catIndex;
        }

        if ($empresa !== null && $nodes !== []) {
            $coreId = 'core-' . $empresa->getId();
            $nodes[] = [
                'id' => $coreId,
                'name' => $empresa->getNome(),
                'category' => 0,
                'symbolSize' => 56,
                'value' => \count($nodes),
                'domain' => 'core',
                'level' => 'core',
                'x' => 0,
                'y' => 0,
            ];
            foreach ($nodes as $node) {
                if (($node['level'] ?? '') === 'domain') {
                    $links[] = ['source' => $coreId, 'target' => $node['id'], 'value' => (int) ($node['value'] ?? 1)];
                }
            }
        }

        return [
            'categories' => $categories,
            'nodes' => $nodes,
            'links' => array_values($this->uniqueLinks($links)),
        ];
    }

    /**
     * @return array{hub: ?array<string, mixed>, children: list<array<string, mixed>>, inner: list<array{source: string, target: string, value: int}>}
     */
    private function buildDomainNodes(User $user, ?Empresa $empresa, string $domainId, int $category): array
    {
        return match ($domainId) {
            'people' => $this->peopleNodes($empresa, $category),
            'rh' => $this->rhNodes($empresa, $category),
            'delivery' => $this->deliveryNodes($empresa, $category),
            'access' => $this->accessNodes($empresa, $category),
            'signals' => $this->signalsNodes($user, $empresa, $category),
            default => ['hub' => null, 'children' => [], 'inner' => []],
        };
    }

    /** @return array{hub: ?array<string, mixed>, children: list<array<string, mixed>>, inner: list<array{source: string, target: string, value: int}>} */
    private function peopleNodes(?Empresa $empresa, int $category): array
    {
        if ($empresa === null) {
            return ['hub' => null, 'children' => [], 'inner' => []];
        }

        $total = (int) $this->funcionarioRepo->count(['empresa' => $empresa]);
        if ($total <= 0) {
            return ['hub' => null, 'children' => [], 'inner' => []];
        }

        $hub = $this->hubNode('domain-people', 'People', $category, 'people', $total, 42);

        $rows = $this->funcionarioRepo->createQueryBuilder('f')
            ->select('COALESCE(d.nome, :sem) AS dept, COUNT(f.id) AS total')
            ->leftJoin('f.departamento', 'd')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->setParameter('sem', 'Sem departamento')
            ->groupBy('dept')
            ->orderBy('total', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getArrayResult();

        $children = [];
        foreach ($rows as $idx => $row) {
            $count = (int) $row['total'];
            $dept = (string) $row['dept'];
            $children[] = [
                'id' => 'people-dept-' . $idx,
                'name' => $dept,
                'category' => $category,
                'symbolSize' => max(14, min(36, 12 + $count * 3)),
                'value' => $count,
                'domain' => 'people',
                'level' => 'entity',
            ];
        }

        return ['hub' => $hub, 'children' => $children, 'inner' => []];
    }

    /** @return array{hub: ?array<string, mixed>, children: list<array<string, mixed>>, inner: list<array{source: string, target: string, value: int}>} */
    private function rhNodes(?Empresa $empresa, int $category): array
    {
        if ($empresa === null) {
            return ['hub' => null, 'children' => [], 'inner' => []];
        }

        $adm = (int) $this->onboardingRepo->count(['empresa' => $empresa]);
        $dem = (int) $this->offboardingRepo->count(['empresa' => $empresa]);
        $ferias = (int) $this->feriasRepo->count(['empresa' => $empresa]);
        $total = $adm + $dem + $ferias;

        if ($total <= 0) {
            return ['hub' => null, 'children' => [], 'inner' => []];
        }

        $hub = $this->hubNode('domain-rh', 'RH Operations', $category, 'rh', $total, 40);
        $children = array_values(array_filter([
            $adm > 0 ? $this->entityNode('rh-adm', 'Admissões', $category, 'rh', $adm, 'app_rh_admissoes') : null,
            $dem > 0 ? $this->entityNode('rh-dem', 'Desligamentos', $category, 'rh', $dem, 'app_rh_demissoes') : null,
            $ferias > 0 ? $this->entityNode('rh-ferias', 'Férias', $category, 'rh', $ferias, 'app_rh_ferias') : null,
        ]));

        return ['hub' => $hub, 'children' => $children, 'inner' => []];
    }

    /** @return array{hub: ?array<string, mixed>, children: list<array<string, mixed>>, inner: list<array{source: string, target: string, value: int}>} */
    private function deliveryNodes(?Empresa $empresa, int $category): array
    {
        if ($empresa === null) {
            return ['hub' => null, 'children' => [], 'inner' => []];
        }

        $projects = $this->projetoRepo->createQueryBuilder('p')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('p.nome', 'ASC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        if ($projects === []) {
            return ['hub' => null, 'children' => [], 'inner' => []];
        }

        $taskTotal = (int) $this->tarefaRepo->count(['empresa' => $empresa]);
        $hub = $this->hubNode('domain-delivery', 'Delivery', $category, 'delivery', $taskTotal, 40);

        $children = [];
        foreach ($projects as $idx => $project) {
            $tasks = (int) $this->tarefaRepo->count(['projeto' => $project]);
            $children[] = [
                'id' => 'delivery-proj-' . $project->getId(),
                'name' => $project->getNome(),
                'category' => $category,
                'symbolSize' => max(14, min(34, 10 + $tasks * 2)),
                'value' => $tasks,
                'domain' => 'delivery',
                'level' => 'entity',
                'route' => 'app_core_projetos_show',
                'routeParams' => ['id' => $project->getId()],
            ];
        }

        return ['hub' => $hub, 'children' => $children, 'inner' => []];
    }

    /** @return array{hub: ?array<string, mixed>, children: list<array<string, mixed>>, inner: list<array{source: string, target: string, value: int}>} */
    private function accessNodes(?Empresa $empresa, int $category): array
    {
        if ($empresa === null) {
            return $this->tenantAccessNodes($category);
        }

        $total = (int) $this->userRepo->count(['empresa' => $empresa]);
        if ($total <= 0) {
            return ['hub' => null, 'children' => [], 'inner' => []];
        }

        $hub = $this->hubNode('domain-access', 'Access', $category, 'access', $total, 38);

        $rows = $this->userRepo->createQueryBuilder('u')
            ->select('u.perfil AS perfil, COUNT(u.id) AS total')
            ->andWhere('u.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('u.perfil')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $labels = [
            'TENANT' => 'Tenant', 'ADMIN' => 'Admin', 'GESTOR' => 'Gestor',
            'GESTOR_EQUIPE' => 'Gestor equipe', 'SUPERVISOR' => 'Supervisor',
            'SUPERVISOR_EQUIPE' => 'Sup. equipe', 'MEMBRO' => 'Membro',
        ];

        $children = [];
        foreach ($rows as $idx => $row) {
            $count = (int) $row['total'];
            $key = (string) $row['perfil'];
            $children[] = [
                'id' => 'access-perfil-' . $idx,
                'name' => $labels[$key] ?? $key,
                'category' => $category,
                'symbolSize' => max(14, min(32, 10 + $count * 2)),
                'value' => $count,
                'domain' => 'access',
                'level' => 'entity',
            ];
        }

        return ['hub' => $hub, 'children' => $children, 'inner' => []];
    }

    /** @return array{hub: ?array<string, mixed>, children: list<array<string, mixed>>, inner: list<array{source: string, target: string, value: int}>} */
    private function tenantAccessNodes(int $category): array
    {
        $empresas = (int) $this->empresaRepo->count(['ativo' => true]);
        $users = (int) $this->userRepo->count([]);
        if ($empresas + $users <= 0) {
            return ['hub' => null, 'children' => [], 'inner' => []];
        }

        $hub = $this->hubNode('domain-access', 'Plataforma', $category, 'access', $users, 40);
        $children = array_values(array_filter([
            $empresas > 0 ? $this->entityNode('access-emp', 'Empresas ativas', $category, 'access', $empresas, 'app_admin_empresas') : null,
            $users > 0 ? $this->entityNode('access-users', 'Usuários', $category, 'access', $users, 'app_admin_usuarios') : null,
        ]));

        return ['hub' => $hub, 'children' => $children, 'inner' => []];
    }

    /** @return array{hub: ?array<string, mixed>, children: list<array<string, mixed>>, inner: list<array{source: string, target: string, value: int}>} */
    private function signalsNodes(User $user, ?Empresa $empresa, int $category): array
    {
        $insights = $this->newsIntelligence->buildInsights($user, $empresa, 'gestor');
        $count = \count($insights);
        if ($count <= 0) {
            return ['hub' => null, 'children' => [], 'inner' => []];
        }

        $hub = $this->hubNode('domain-signals', 'Signals', $category, 'signals', $count, 36);
        $children = [];
        foreach (\array_slice($insights, 0, 5) as $idx => $insight) {
            $children[] = [
                'id' => 'signal-' . $idx,
                'name' => mb_strlen($insight['title']) > 28 ? mb_substr($insight['title'], 0, 26) . '…' : $insight['title'],
                'category' => $category,
                'symbolSize' => 18,
                'value' => 1,
                'domain' => 'signals',
                'level' => 'entity',
                'route' => $insight['relatedRoute'] ?? null,
            ];
        }

        return ['hub' => $hub, 'children' => $children, 'inner' => []];
    }

    /** @param list<array<string, mixed>> $graphNodes */
    /** @return list<array<string, mixed>> */
    private function buildDomainSummaries(User $user, ?Empresa $empresa, array $graph): array
    {
        $summaries = [];
        foreach (self::DOMAIN_META as $domainId => $meta) {
            if (!$this->isDomainVisible($user, $empresa, $domainId)) {
                continue;
            }
            $domainNodes = array_values(array_filter(
                $graph['nodes'],
                static fn (array $n): bool => ($n['domain'] ?? '') === $domainId && ($n['level'] ?? '') === 'domain',
            ));
            if ($domainNodes === []) {
                continue;
            }
            $hub = $domainNodes[0];
            $entityCount = \count(array_filter(
                $graph['nodes'],
                static fn (array $n): bool => ($n['domain'] ?? '') === $domainId && ($n['level'] ?? '') === 'entity',
            ));
            $score = $this->domainScore($domainId, (int) ($hub['value'] ?? 0), $empresa);
            $summaries[] = [
                'id' => $domainId,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'color' => $meta['color'],
                'value' => (int) ($hub['value'] ?? 0),
                'entities' => $entityCount,
                'score' => $score,
                'status' => $this->scoreStatus($score),
            ];
        }

        return $summaries;
    }

    /** @param list<array<string, mixed>> $domains */
    /** @return array<string, mixed> */
    private function buildPulse(array $domains): array
    {
        if ($domains === []) {
            return [
                'score' => 0,
                'status' => 'idle',
                'label' => 'Sem dados',
                'hint' => 'Cadastre colaboradores ou projetos para ativar o Cortex',
            ];
        }

        $score = (int) round(array_sum(array_column($domains, 'score')) / \count($domains));

        return [
            'score' => $score,
            'status' => $this->scoreStatus($score),
            'label' => match ($this->scoreStatus($score)) {
                'healthy' => 'Sistema saudável',
                'attention' => 'Atenção recomendada',
                default => 'Intervenção sugerida',
            },
            'hint' => \count($domains) . ' domínios cognitivos ativos',
            'domains' => $domains,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function buildInsights(User $user, ?Empresa $empresa): array
    {
        $raw = $this->newsIntelligence->buildInsights($user, $empresa, 'gestor');
        $insights = [];
        foreach (\array_slice($raw, 0, 8) as $item) {
            $insights[] = [
                'id' => $item['id'] ?? uniqid('cortex-', true),
                'title' => $item['title'] ?? '',
                'summary' => $item['summary'] ?? '',
                'category' => $item['category'] ?? 'Insight',
                'icon' => $item['icon'] ?? 'fa-lightbulb',
                'route' => $item['relatedRoute'] ?? null,
                'priority' => str_contains(strtolower($item['category'] ?? ''), 'alerta') ? 'high' : 'normal',
            ];
        }

        if ($empresa !== null && $this->navigation->showProjetosMetas($user)) {
            $total = (int) $this->tarefaRepo->count(['empresa' => $empresa]);
            $done = (int) $this->tarefaRepo->count(['empresa' => $empresa, 'status' => DevTarefa::STATUS_CONCLUIDO]);
            if ($total > 0) {
                $rate = (int) round(($done / $total) * 100);
                if ($rate < 40) {
                    $insights[] = [
                        'id' => 'cortex-delivery-rate',
                        'title' => sprintf('Taxa de entrega em %d%%', $rate),
                        'summary' => 'Volume de tarefas concluídas abaixo do ideal — revise gargalos no portfólio.',
                        'category' => 'Delivery',
                        'icon' => 'fa-chart-line',
                        'route' => 'app_core_projetos',
                        'priority' => 'high',
                    ];
                }
            }
        }

        return $insights;
    }

    private function isDomainVisible(User $user, ?Empresa $empresa, string $domainId): bool
    {
        return match ($domainId) {
            'people' => $empresa !== null && ($this->navigation->showModuloRh($user) || $this->navigation->showModuloPessoas($user)),
            'rh' => $empresa !== null && $this->navigation->showModuloRh($user),
            'delivery' => $empresa !== null && $this->navigation->showProjetosMetas($user),
            'access' => true,
            'signals' => true,
            default => false,
        };
    }

    private function domainScore(string $domainId, int $value, ?Empresa $empresa): int
    {
        if ($value <= 0) {
            return 40;
        }

        return match ($domainId) {
            'people' => min(100, 50 + $value * 5),
            'rh' => $empresa === null ? 70 : min(100, 45 + $value * 8),
            'delivery' => min(100, (int) $this->deliveryScore($empresa)),
            'access' => min(100, 55 + $value * 3),
            'signals' => max(35, 100 - $value * 12),
            default => 70,
        };
    }

    private function deliveryScore(?Empresa $empresa): int
    {
        if ($empresa === null) {
            return 60;
        }
        $total = (int) $this->tarefaRepo->count(['empresa' => $empresa]);
        if ($total <= 0) {
            return 50;
        }
        $done = (int) $this->tarefaRepo->count(['empresa' => $empresa, 'status' => DevTarefa::STATUS_CONCLUIDO]);
        $active = (int) $this->projetoRepo->count(['empresa' => $empresa, 'status' => DevProjeto::STATUS_EM_ANDAMENTO]);

        return (int) round(($done / $total) * 60 + min(40, $active * 10));
    }

    private function scoreStatus(int $score): string
    {
        return match (true) {
            $score >= 75 => 'healthy',
            $score >= 50 => 'attention',
            default => 'critical',
        };
    }

    /** @return array<string, mixed> */
    private function hubNode(string $id, string $name, int $category, string $domain, int $value, int $size): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'category' => $category,
            'symbolSize' => $size,
            'value' => $value,
            'domain' => $domain,
            'level' => 'domain',
        ];
    }

    /** @return array<string, mixed> */
    private function entityNode(string $id, string $name, int $category, string $domain, int $value, ?string $route = null): array
    {
        $node = [
            'id' => $id,
            'name' => $name,
            'category' => $category,
            'symbolSize' => max(14, min(32, 10 + $value * 2)),
            'value' => $value,
            'domain' => $domain,
            'level' => 'entity',
        ];
        if ($route !== null) {
            $node['route'] = $route;
        }

        return $node;
    }

    /**
     * @param list<array{source: string, target: string, value?: int|float}> $links
     *
     * @return list<array{source: string, target: string, value?: int|float}>
     */
    private function uniqueLinks(array $links): array
    {
        $merged = [];
        foreach ($links as $link) {
            $key = $link['source'] . '→' . $link['target'];
            if (!isset($merged[$key])) {
                $merged[$key] = $link;
                continue;
            }
            $merged[$key]['value'] = ($merged[$key]['value'] ?? 0) + ($link['value'] ?? 1);
        }

        return array_values($merged);
    }
}
