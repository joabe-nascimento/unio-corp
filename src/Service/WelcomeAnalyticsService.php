<?php

namespace App\Service;

use App\Chart\ChartConfig;
use App\Entity\DevProjeto;
use App\Entity\DevTarefa;
use App\Entity\Empresa;
use App\Entity\User;
use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;
use App\Repository\EmpresaRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Agrega dados registrados no banco para gráficos da tela de boas-vindas.
 */
final class WelcomeAnalyticsService
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

    public function __construct(
        private UserRepository $userRepo,
        private EmpresaRepository $empresaRepo,
        private FuncionarioRepository $funcionarioRepo,
        private DevTarefaRepository $tarefaRepo,
        private DevProjetoRepository $projetoRepo,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhOffboardingProcessRepository $offboardingRepo,
        private NavigationService $navigation,
    ) {}

    /**
     * @return list<array{
     *     id: string,
     *     title: string,
     *     subtitle: string,
     *     icon: string,
     *     charts: list<array<string, mixed>>
     * }>
     */
    public function getChartSections(User $user, ?Empresa $empresa): array
    {
        $sections = [];
        $isTenant = $this->navigation->isTenant($user);

        $access = $this->buildAccessSection($user, $empresa, $isTenant);
        if ($access !== null) {
            $sections[] = $access;
        }

        if ($empresa !== null && ($this->navigation->showModuloRh($user) || $this->navigation->showModuloPessoas($user))) {
            $people = $this->buildPeopleSection($empresa);
            if ($people !== null) {
                $sections[] = $people;
            }
        }

        if ($empresa !== null && $this->navigation->showModuloRh($user)) {
            $rh = $this->buildRhSection($empresa);
            if ($rh !== null) {
                $sections[] = $rh;
            }
        }

        if ($empresa !== null && $this->navigation->showProjetosMetas($user)) {
            $dev = $this->buildProjectsSection($empresa);
            if ($dev !== null) {
                $sections[] = $dev;
            }
        }

        $evolution = $this->buildEvolutionSection($user, $empresa, $isTenant);
        if ($evolution !== null) {
            $sections[] = $evolution;
        }

        return $sections;
    }

  /**
     * @return ?array{id: string, title: string, subtitle: string, icon: string, charts: list<array<string, mixed>>}
     */
    private function buildAccessSection(User $user, ?Empresa $empresa, bool $isTenant): ?array
    {
        $charts = [];

        $byPerfil = $this->countUsersByPerfil($empresa, $isTenant);
        if ($this->hasValues($byPerfil['values'])) {
            $charts[] = ChartConfig::doughnut(
                'users-perfil',
                'Usuários por perfil',
                $byPerfil['labels'],
                $byPerfil['values'],
                'Distribuição dos perfis de acesso registrados',
            )->toArray();
        }

        $byAtivo = $this->countUsersByAtivo($empresa, $isTenant);
        if ($this->hasValues($byAtivo['values'])) {
            $charts[] = ChartConfig::bar(
                'users-ativo',
                'Situação dos usuários',
                $byAtivo['labels'],
                $byAtivo['values'],
                'Contas ativas e inativas no cadastro',
            )->toArray();
        }

        if ($isTenant) {
            $empresas = $this->countEmpresasAtivas();
            if ($empresas['total'] > 0) {
                $charts[] = ChartConfig::doughnut(
                    'empresas-ativas',
                    'Empresas na plataforma',
                    $empresas['labels'],
                    $empresas['values'],
                    'Tenants e áreas de trabalho cadastradas',
                )->toArray();
            }
        }

        if ($charts === []) {
            return null;
        }

        return [
            'id' => 'access',
            'title' => 'Cadastros e acessos',
            'subtitle' => $empresa
                ? 'Usuários e registros em ' . $empresa->getNome()
                : 'Visão consolidada da plataforma',
            'icon' => 'fa-user-shield',
            'charts' => $charts,
        ];
    }

    /** @return ?array{id: string, title: string, subtitle: string, icon: string, charts: list<array<string, mixed>>} */
    private function buildPeopleSection(Empresa $empresa): ?array
    {
        $charts = [];

        $byStatus = $this->countFuncionariosByStatus($empresa);
        if ($this->hasValues($byStatus['values'])) {
            $charts[] = ChartConfig::doughnut(
                'func-status',
                'Colaboradores por situação',
                $byStatus['labels'],
                $byStatus['values'],
                'Funcionários registrados no hub de pessoas',
            )->toArray();
        }

        $byDept = $this->countFuncionariosByDepartamento($empresa);
        if ($this->hasValues($byDept['values'])) {
            $charts[] = ChartConfig::bar(
                'func-depto',
                'Colaboradores por departamento',
                $byDept['labels'],
                $byDept['values'],
                'Distribuição da equipe nas áreas cadastradas',
                true,
            )->toArray();
        }

        if ($charts === []) {
            return null;
        }

        return [
            'id' => 'people',
            'title' => 'Pessoas e equipe',
            'subtitle' => 'Dados de colaboradores e departamentos',
            'icon' => 'fa-users',
            'charts' => $charts,
        ];
    }

    /** @return ?array{id: string, title: string, subtitle: string, icon: string, charts: list<array<string, mixed>>} */
    private function buildRhSection(Empresa $empresa): ?array
    {
        $charts = [];

        $adm = $this->countRhByStatus(RhOnboardingProcess::class, $empresa);
        if ($this->hasValues($adm['values'])) {
            $charts[] = ChartConfig::bar(
                'rh-admissoes',
                'Processos de admissão',
                $adm['labels'],
                $adm['values'],
                'Onboarding registrados no RH',
            )->toArray();
        }

        $dem = $this->countRhByStatus(RhOffboardingProcess::class, $empresa);
        if ($this->hasValues($dem['values'])) {
            $charts[] = ChartConfig::bar(
                'rh-demissao',
                'Processos de demissão',
                $dem['labels'],
                $dem['values'],
                'Offboarding registrados no RH',
            )->toArray();
        }

        if ($charts === []) {
            return null;
        }

        return [
            'id' => 'rh',
            'title' => 'Recursos humanos',
            'subtitle' => 'Admissões e desligamentos em andamento',
            'icon' => 'fa-id-card',
            'charts' => $charts,
        ];
    }

    /** @return ?array{id: string, title: string, subtitle: string, icon: string, charts: list<array<string, mixed>>} */
    private function buildProjectsSection(Empresa $empresa): ?array
    {
        $charts = [];

        $tarefas = $this->countTarefasByStatus($empresa);
        if ($this->hasValues($tarefas['values'])) {
            $charts[] = ChartConfig::bar(
                'dev-tarefas',
                'Tarefas por status',
                $tarefas['labels'],
                $tarefas['values'],
                'Quadro de projetos e metas (Kanban)',
            )->toArray();
        }

        $projetos = $this->countProjetosByStatus($empresa);
        if ($this->hasValues($projetos['values'])) {
            $charts[] = ChartConfig::doughnut(
                'dev-projetos',
                'Projetos por status',
                $projetos['labels'],
                $projetos['values'],
                'Portfólio de projetos registrados',
            )->toArray();
        }

        if ($charts === []) {
            return null;
        }

        return [
            'id' => 'projects',
            'title' => 'Projetos e metas',
            'subtitle' => 'Entregas e portfólio de desenvolvimento',
            'icon' => 'fa-diagram-project',
            'charts' => $charts,
        ];
    }

    /** @return ?array{id: string, title: string, subtitle: string, icon: string, charts: list<array<string, mixed>>} */
    private function buildEvolutionSection(User $user, ?Empresa $empresa, bool $isTenant): ?array
    {
        $evolution = $this->countRegistrationsLastMonths($empresa, $isTenant, 6);
        if (!$this->hasValues($evolution['users']) && !$this->hasValues($evolution['funcionarios'])) {
            return null;
        }

        $datasets = array_values(array_filter([
            $this->hasValues($evolution['users'])
                ? ['label' => 'Usuários', 'data' => $evolution['users']]
                : null,
            $empresa !== null && $this->hasValues($evolution['funcionarios'])
                ? ['label' => 'Colaboradores', 'data' => $evolution['funcionarios']]
                : null,
        ]));

        $charts = [
            ChartConfig::line(
                'evolution-registrations',
                'Novos registros (últimos 6 meses)',
                $evolution['labels'],
                $datasets,
                'Usuários e colaboradores cadastrados por mês',
            )->toArray(),
        ];

        return [
            'id' => 'evolution',
            'title' => 'Evolução',
            'subtitle' => 'Crescimento dos cadastros ao longo do tempo',
            'icon' => 'fa-chart-line',
            'charts' => $charts,
        ];
    }

    /** @param list<int> $values */
    private function hasValues(array $values): bool
    {
        return array_sum($values) > 0;
    }

    /** @return array{labels: list<string>, values: list<int>} */
    private function countUsersByPerfil(?Empresa $empresa, bool $isTenant): array
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
    private function countUsersByAtivo(?Empresa $empresa, bool $isTenant): array
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
    private function countEmpresasAtivas(): array
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
    private function countFuncionariosByStatus(Empresa $empresa): array
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
    private function countFuncionariosByDepartamento(Empresa $empresa): array
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
    private function countRhByStatus(string $entityClass, Empresa $empresa): array
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
    private function countTarefasByStatus(Empresa $empresa): array
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
    private function countProjetosByStatus(Empresa $empresa): array
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
     * @return array{
     *     labels: list<string>,
     *     users: list<int>,
     *     funcionarios: list<int>
     * }
     */
    private function countRegistrationsLastMonths(?Empresa $empresa, bool $isTenant, int $months): array
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

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $labelMap
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    private function mapGroupedRows(array $rows, string $field, array $labelMap): array
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

    private function formatMonthLabel(DateTimeImmutable $date): string
    {
        $months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        return $months[(int) $date->format('n') - 1] . '/' . $date->format('y');
    }
}
