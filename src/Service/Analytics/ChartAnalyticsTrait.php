<?php

namespace App\Service\Analytics;

use App\Entity\DevProjeto;
use App\Entity\DevTarefa;
use App\Entity\Empresa;
use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use DateTimeImmutable;
use DateTimeZone;

trait ChartAnalyticsTrait
{
    private const TZ = 'America/Sao_Paulo';

    private const PERFIL_LABELS = [
        'TENANT' => 'Tenant',
        'ADMIN' => 'Admin',
        'GESTOR' => 'Gestor',
        'GESTOR_EQUIPE' => 'Gestor de equipe',
        'SUPERVISOR' => 'Supervisor',
        'SUPERVISOR_EQUIPE' => 'Supervisor de equipe',
        'MEMBRO' => 'Membro',
    ];

    private const FUNC_STATUS_LABELS = [
        'ATIVO' => 'Ativos',
        'INATIVO' => 'Inativos',
        'FERIAS' => 'Férias',
        'AFASTADO' => 'Afastados',
    ];

    private const RH_STATUS_LABELS = [
        RhOnboardingProcess::STATUS_RASCUNHO => 'Rascunho',
        RhOnboardingProcess::STATUS_EM_ANDAMENTO => 'Em andamento',
        RhOnboardingProcess::STATUS_CONCLUIDO => 'Concluído',
        RhOnboardingProcess::STATUS_CANCELADO => 'Cancelado',
    ];

    /**
     * @param list<array<string, mixed>> $charts
     *
     * @return array<string, mixed>
     */
    protected function makeSection(
        string $id,
        string $title,
        string $subtitle,
        string $icon,
        string $tier,
        string $badge,
        array $charts,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'icon' => $icon,
            'tier' => $tier,
            'badge' => $badge,
            'charts' => $charts,
        ];
    }

    /** @param array<string, mixed> $chart @return array<string, mixed> */
    protected function withKpi(array $chart, string $label, int|float $value): array
    {
        $chart['kpi'] = ['label' => $label, 'value' => $value];

        return $chart;
    }

    /** @param list<int> $values */
    protected function hasValues(array $values): bool
    {
        return array_sum($values) > 0;
    }

    /** @param list<array{name: string}> $nodes */
    protected function ensureSankeyNode(array &$nodes, string $name): void
    {
        foreach ($nodes as $node) {
            if (($node['name'] ?? '') === $name) {
                return;
            }
        }
        $nodes[] = ['name' => $name];
    }

    /**
     * @param list<array{source: string, target: string, value: int|float}> $links
     *
     * @return list<array{source: string, target: string, value: int|float}>
     */
    protected function mergeSankeyLinks(array $links): array
    {
        $merged = [];
        foreach ($links as $link) {
            $key = $link['source'] . '→' . $link['target'];
            if (!isset($merged[$key])) {
                $merged[$key] = $link;
                continue;
            }
            $merged[$key]['value'] += $link['value'];
        }

        return array_values($merged);
    }

    /** @return array<string, mixed> */
    protected function executiveKpi(
        string $id,
        string $label,
        int|float $value,
        string $icon,
        string $hint,
        ?string $suffix = null,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'value' => $value,
            'icon' => $icon,
            'hint' => $hint,
            'suffix' => $suffix,
        ];
    }

    /**
     * @return ?array{
     *     depts: list<string>,
     *     statuses: list<string>,
     *     map: array<string, int>,
     *     total: int
     * }
     */
    protected function fetchPeopleMatrix(Empresa $empresa): ?array
    {
        $rows = $this->funcionarioRepo->createQueryBuilder('f')
            ->select('COALESCE(d.nome, :sem) AS dept, f.status AS status, COUNT(f.id) AS total')
            ->leftJoin('f.departamento', 'd')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->setParameter('sem', 'Sem departamento')
            ->groupBy('dept, status')
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return null;
        }

        $depts = [];
        $statuses = [];
        $map = [];
        $total = 0;

        foreach ($rows as $row) {
            $dept = (string) $row['dept'];
            $status = self::FUNC_STATUS_LABELS[(string) $row['status']] ?? (string) $row['status'];
            $count = (int) $row['total'];
            if (!\in_array($dept, $depts, true)) {
                $depts[] = $dept;
            }
            if (!\in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }
            $map[$dept . '|' . $status] = $count;
            $total += $count;
        }

        return ['depts' => $depts, 'statuses' => $statuses, 'map' => $map, 'total' => $total];
    }

    /**
     * @param class-string $entityClass
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    protected function countEntityRegistrationsLastMonths(?Empresa $empresa, string $entityClass, int $months): array
    {
        $tz = new DateTimeZone(self::TZ);
        $now = new DateTimeImmutable('now', $tz);
        $labels = [];
        $values = [];

        $repo = match ($entityClass) {
            DevTarefa::class => $this->tarefaRepo,
            RhOnboardingProcess::class => $this->onboardingRepo,
            RhOffboardingProcess::class => $this->offboardingRepo,
            default => throw new \InvalidArgumentException('Unsupported entity'),
        };

        for ($i = $months - 1; $i >= 0; --$i) {
            $monthStart = $now->modify('first day of this month')->modify("-{$i} months")->setTime(0, 0);
            $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);
            $labels[] = $this->formatMonthLabel($monthStart);

            $qb = $repo->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->andWhere('e.criadoEm BETWEEN :start AND :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd);

            if ($empresa !== null) {
                $qb->andWhere('e.empresa = :empresa')->setParameter('empresa', $empresa);
            }

            $values[] = (int) $qb->getQuery()->getSingleScalarResult();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     users: list<int>,
     *     funcionarios: list<int>
     * }
     */
    protected function countRegistrationsLastMonths(?Empresa $empresa, bool $isTenant, int $months): array
    {
        $tz = new DateTimeZone(self::TZ);
        $now = new DateTimeImmutable('now', $tz);
        $labels = [];
        $users = [];
        $funcionarios = [];

        for ($i = $months - 1; $i >= 0; --$i) {
            $monthStart = $now->modify('first day of this month')->modify("-{$i} months")->setTime(0, 0);
            $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);
            $labels[] = $this->formatMonthLabel($monthStart);

            $userQb = $this->userRepo->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->andWhere('u.criadoEm BETWEEN :start AND :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd);

            if (!$isTenant && $empresa !== null) {
                $userQb->andWhere('u.empresa = :empresa')->setParameter('empresa', $empresa);
            }

            $users[] = (int) $userQb->getQuery()->getSingleScalarResult();

            $funcCount = 0;
            if ($empresa !== null) {
                $funcCount = (int) $this->funcionarioRepo->createQueryBuilder('f')
                    ->select('COUNT(f.id)')
                    ->andWhere('f.empresa = :empresa')
                    ->andWhere('f.criadoEm BETWEEN :start AND :end')
                    ->setParameter('empresa', $empresa)
                    ->setParameter('start', $monthStart)
                    ->setParameter('end', $monthEnd)
                    ->getQuery()
                    ->getSingleScalarResult();
            }
            $funcionarios[] = $funcCount;
        }

        return ['labels' => $labels, 'users' => $users, 'funcionarios' => $funcionarios];
    }

    /** @return array{labels: list<string>, values: list<int>} */
    protected function countUsersByPerfil(?Empresa $empresa, bool $isTenant): array
    {
        $qb = $this->userRepo->createQueryBuilder('u')
            ->select('u.perfil AS perfil, COUNT(u.id) AS total')
            ->groupBy('u.perfil')
            ->orderBy('total', 'DESC');

        if (!$isTenant && $empresa !== null) {
            $qb->andWhere('u.empresa = :empresa')->setParameter('empresa', $empresa);
        }

        return $this->mapGroupedRows($qb->getQuery()->getArrayResult(), 'perfil', self::PERFIL_LABELS);
    }

    /** @return array{labels: list<string>, values: list<int>} */
    protected function countUsersByAtivo(?Empresa $empresa, bool $isTenant): array
    {
        $labels = ['Ativos', 'Inativos'];
        $values = [0, 0];

        $qb = $this->userRepo->createQueryBuilder('u')
            ->select('u.ativo AS ativo, COUNT(u.id) AS total')
            ->groupBy('u.ativo');

        if (!$isTenant && $empresa !== null) {
            $qb->andWhere('u.empresa = :empresa')->setParameter('empresa', $empresa);
        }

        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $idx = ($row['ativo'] ?? false) ? 0 : 1;
            $values[$idx] = (int) $row['total'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels: list<string>, values: list<int>, total: int} */
    protected function countEmpresasAtivas(): array
    {
        $total = (int) $this->empresaRepo->count([]);
        $ativas = (int) $this->empresaRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.ativo = true')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'labels' => ['Ativas', 'Inativas'],
            'values' => [$ativas, max(0, $total - $ativas)],
            'total' => $total,
        ];
    }

    /** @return array{labels: list<string>, values: list<int>} */
    protected function countFuncionariosByStatus(Empresa $empresa): array
    {
        $qb = $this->funcionarioRepo->createQueryBuilder('f')
            ->select('f.status AS status, COUNT(f.id) AS total')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('f.status')
            ->orderBy('total', 'DESC');

        return $this->mapGroupedRows($qb->getQuery()->getArrayResult(), 'status', self::FUNC_STATUS_LABELS);
    }

    /** @return array{labels: list<string>, values: list<int>} */
    protected function countFuncionariosByDepartamento(Empresa $empresa): array
    {
        $rows = $this->funcionarioRepo->createQueryBuilder('f')
            ->select('COALESCE(d.nome, :sem) AS nome, COUNT(f.id) AS total')
            ->leftJoin('f.departamento', 'd')
            ->andWhere('f.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->setParameter('sem', 'Sem departamento')
            ->groupBy('nome')
            ->orderBy('total', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getArrayResult();

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = (string) $row['nome'];
            $values[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param class-string<RhOnboardingProcess|RhOffboardingProcess> $entityClass
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    protected function countRhByStatus(string $entityClass, Empresa $empresa): array
    {
        $repo = $entityClass === RhOnboardingProcess::class
            ? $this->onboardingRepo
            : $this->offboardingRepo;

        $qb = $repo->createQueryBuilder('p')
            ->select('p.status AS status, COUNT(p.id) AS total')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('p.status')
            ->orderBy('total', 'DESC');

        return $this->mapGroupedRows($qb->getQuery()->getArrayResult(), 'status', self::RH_STATUS_LABELS);
    }

    /** @return array{labels: list<string>, values: list<int>} */
    protected function countTarefasByStatus(Empresa $empresa): array
    {
        $qb = $this->tarefaRepo->createQueryBuilder('t')
            ->select('t.status AS status, COUNT(t.id) AS total')
            ->andWhere('t.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('t.status')
            ->orderBy('total', 'DESC');

        return $this->mapGroupedRows($qb->getQuery()->getArrayResult(), 'status', DevTarefa::KANBAN_COLUMNS);
    }

    /** @return array{labels: list<string>, values: list<int>} */
    protected function countProjetosByStatus(Empresa $empresa): array
    {
        $labelsMap = [
            DevProjeto::STATUS_IDEIA => 'Ideia',
            DevProjeto::STATUS_EM_ANDAMENTO => 'Em andamento',
            DevProjeto::STATUS_PAUSADO => 'Pausado',
            DevProjeto::STATUS_FEITO => 'Concluído',
        ];

        $qb = $this->projetoRepo->createQueryBuilder('p')
            ->select('p.status AS status, COUNT(p.id) AS total')
            ->andWhere('p.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->groupBy('p.status')
            ->orderBy('total', 'DESC');

        return $this->mapGroupedRows($qb->getQuery()->getArrayResult(), 'status', $labelsMap);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $labelMap
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    protected function mapGroupedRows(array $rows, string $field, array $labelMap): array
    {
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $key = (string) ($row[$field] ?? '');
            $labels[] = $labelMap[$key] ?? $key;
            $values[] = (int) ($row['total'] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function formatMonthLabel(DateTimeImmutable $date): string
    {
        $months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        return $months[(int) $date->format('n') - 1] . '/' . $date->format('y');
    }
}
