<?php

namespace App\Service\Analytics;

use App\Chart\ChartConfig;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\EmpresaRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\UserRepository;
use App\Service\NavigationService;

final class GovernanceAnalyticsService
{
    use ChartAnalyticsTrait;

    public function __construct(
        private UserRepository $userRepo,
        private EmpresaRepository $empresaRepo,
        private FuncionarioRepository $funcionarioRepo,
        private NavigationService $navigation,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buildSections(User $user, ?Empresa $empresa): array
    {
        $hasGlobalScope = $user->hasPlatformAccess();

        $governance = array_values(array_filter([
            !($empresa !== null && ($this->navigation->showModuloRh($user) || $this->navigation->showModuloPessoas($user)))
                ? $this->buildAccessSankey($user, $empresa, $hasGlobalScope)
                : null,
            $empresa !== null ? $this->buildProfileRing($empresa) : null,
            $empresa !== null ? $this->buildUserActivityGauge($empresa) : null,
            ($hasGlobalScope && $empresa === null) ? $this->buildTenantSankey() : null,
            ($hasGlobalScope && $empresa === null) ? $this->buildTenantEmpresaTreemap() : null,
        ]));

        if ($governance === []) {
            return [];
        }

        return [
            $this->makeSection(
                'platform-governance',
                'Platform Governance',
                'Acessos, perfis e governança multi-empresa',
                'fa-shield-halved',
                'governance',
                'Identity & Access',
                $governance,
            ),
        ];
    }

    /** @return ?array<string, mixed> */
    private function buildAccessSankey(User $user, ?Empresa $empresa, bool $isTenant): ?array
    {
        if ($isTenant && $empresa === null) {
            return $this->buildTenantSankey();
        }

        $qb = $this->userRepo->createQueryBuilder('u')
            ->select('u.ativo AS ativo, u.perfil AS perfil, COUNT(u.id) AS total')
            ->groupBy('u.ativo, u.perfil')
            ->orderBy('total', 'DESC');

        if ($empresa !== null) {
            $qb->andWhere('u.empresa = :empresa')->setParameter('empresa', $empresa);
        }

        $rows = $qb->getQuery()->getArrayResult();
        if ($rows === []) {
            return null;
        }

        $root = $empresa?->getNome() ?? 'Acesso';
        $nodes = [['name' => $root]];
        $links = [];

        foreach ($rows as $row) {
            $value = (int) ($row['total'] ?? 0);
            if ($value <= 0) {
                continue;
            }
            $bucket = ($row['ativo'] ?? false) ? 'Contas ativas' : 'Contas inativas';
            $perfil = self::PERFIL_LABELS[(string) ($row['perfil'] ?? '')] ?? (string) ($row['perfil'] ?? 'Perfil');
            $perfilNode = 'Perfil · ' . $perfil;

            $this->ensureSankeyNode($nodes, $bucket);
            $this->ensureSankeyNode($nodes, $perfilNode);
            $links[] = ['source' => $root, 'target' => $bucket, 'value' => $value];
            $links[] = ['source' => $bucket, 'target' => $perfilNode, 'value' => $value];
        }

        $links = $this->mergeSankeyLinks($links);
        $total = (int) $this->userRepo->count($empresa !== null ? ['empresa' => $empresa] : []);

        $chart = ChartConfig::sankey(
            'access-sankey',
            'Mapa de acessos',
            $nodes,
            $links,
            'Hierarquia empresa → status da conta → perfil de permissão',
        )->toArray();

        return $this->withKpi($chart, 'Contas', $total);
    }

    /** @return ?array<string, mixed> */
    private function buildTenantSankey(): ?array
    {
        $empresas = $this->empresaRepo->findBy(['ativo' => true], ['nome' => 'ASC'], 8);
        if ($empresas === []) {
            return null;
        }

        $root = 'Plataforma Unio';
        $nodes = [['name' => $root]];
        $links = [];

        foreach ($empresas as $emp) {
            $userCount = (int) $this->userRepo->count(['empresa' => $emp]);
            if ($userCount <= 0) {
                continue;
            }
            $node = (string) $emp->getNome();
            $this->ensureSankeyNode($nodes, $node);
            $links[] = ['source' => $root, 'target' => $node, 'value' => $userCount];

            $byPerfil = $this->countUsersByPerfil($emp, false);
            foreach ($byPerfil['labels'] as $idx => $label) {
                $value = (int) ($byPerfil['values'][$idx] ?? 0);
                if ($value <= 0) {
                    continue;
                }
                $perfilNode = $node . ' · ' . $label;
                $this->ensureSankeyNode($nodes, $perfilNode);
                $links[] = ['source' => $node, 'target' => $perfilNode, 'value' => $value];
            }
        }

        if ($links === []) {
            return null;
        }

        return ChartConfig::sankey(
            'tenant-sankey',
            'Fluxo multi-empresa',
            $nodes,
            $this->mergeSankeyLinks($links),
            'Empresas ativas e perfis de usuário na plataforma',
        )->toArray();
    }

    /** @return ?array<string, mixed> */
    private function buildProfileRing(Empresa $empresa): ?array
    {
        $byPerfil = $this->countUsersByPerfil($empresa, false);
        if (!$this->hasValues($byPerfil['values'])) {
            return null;
        }

        $chart = ChartConfig::ring(
            'profile-ring',
            'Mix de perfis de acesso',
            $byPerfil['labels'],
            $byPerfil['values'],
            'Distribuição de papéis e permissões na empresa',
        )->toArray();
        $chart['size'] = 'compact';

        return $this->withKpi($chart, 'Contas', array_sum($byPerfil['values']));
    }

    /** @return ?array<string, mixed> */
    private function buildUserActivityGauge(Empresa $empresa): ?array
    {
        $byAtivo = $this->countUsersByAtivo($empresa, false);
        $total = array_sum($byAtivo['values']);
        if ($total <= 0) {
            return null;
        }

        $active = (int) ($byAtivo['values'][0] ?? 0);
        $rate = (int) round(($active / $total) * 100);

        $chart = ChartConfig::gauge(
            'user-activity-gauge',
            'Contas ativas',
            $rate,
            100,
            'Percentual de usuários com acesso ativo',
            '%',
        )->toArray();
        $chart['size'] = 'compact';

        return $this->withKpi($chart, 'Ativas', $active);
    }

    /** @return ?array<string, mixed> */
    private function buildTenantEmpresaTreemap(): ?array
    {
        $empresas = $this->empresaRepo->findBy(['ativo' => true], ['nome' => 'ASC'], 12);
        if ($empresas === []) {
            return null;
        }

        $tree = [];
        foreach ($empresas as $emp) {
            $users = (int) $this->userRepo->count(['empresa' => $emp]);
            $people = (int) $this->funcionarioRepo->count(['empresa' => $emp]);
            if ($users + $people <= 0) {
                continue;
            }
            $tree[] = [
                'name' => (string) $emp->getNome(),
                'value' => max(1, $users + $people),
            ];
        }

        if ($tree === []) {
            return null;
        }

        $chart = ChartConfig::treemap(
            'tenant-empresa-treemap',
            'Mapa multi-empresa',
            $tree,
            'Peso relativo de usuários + colaboradores por empresa ativa',
        )->toArray();

        return $this->withKpi($chart, 'Empresas', \count($tree));
    }
}
