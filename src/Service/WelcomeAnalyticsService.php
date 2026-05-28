<?php

namespace App\Service;

use App\Chart\ChartPanelFactory;
use App\Entity\DevProjeto;
use App\Entity\DevTarefa;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;
use App\Repository\EmpresaRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\UserRepository;
use App\Service\Analytics\DeliveryAnalyticsService;
use App\Service\Analytics\GovernanceAnalyticsService;
use App\Service\Analytics\GrowthAnalyticsService;
use App\Service\Analytics\RhAnalyticsService;
use App\Service\Analytics\WorkforceAnalyticsService;

/**
 * Orquestra módulos de analytics da tela de boas-vindas.
 */
final class WelcomeAnalyticsService
{
    public function __construct(
        private WorkforceAnalyticsService $workforce,
        private RhAnalyticsService $rh,
        private DeliveryAnalyticsService $delivery,
        private GovernanceAnalyticsService $governance,
        private GrowthAnalyticsService $growth,
        private ChartPanelFactory $chartPanelFactory,
        private NavigationService $navigation,
        private UserRepository $userRepo,
        private EmpresaRepository $empresaRepo,
        private FuncionarioRepository $funcionarioRepo,
        private DevTarefaRepository $tarefaRepo,
        private DevProjetoRepository $projetoRepo,
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
        return array_merge(
            $this->workforce->buildSections($user, $empresa),
            $this->rh->buildSections($user, $empresa),
            $this->delivery->buildSections($user, $empresa),
            $this->governance->buildSections($user, $empresa),
            $this->growth->buildSections($user, $empresa),
        );
    }

    /**
     * @return array{
     *     sections: list<array<string, mixed>>,
     *     executive: array{kpis: list<array<string, mixed>>},
     *     meta: array{chart_count: int, section_count: int, generated_at: string}
     * }
     */
    public function getChartPayload(User $user, ?Empresa $empresa): array
    {
        return $this->chartPanelFactory->wrap(
            $this->getChartSections($user, $empresa),
            $this->buildExecutiveSummary($user, $empresa),
        );
    }

    /**
     * @return array{kpis: list<array<string, mixed>>}
     */
    public function buildExecutiveSummary(User $user, ?Empresa $empresa): array
    {
        $isTenant = $this->navigation->isTenant($user);
        $kpis = [];

        if ($empresa !== null) {
            $headcount = (int) $this->funcionarioRepo->count(['empresa' => $empresa]);
            $ativos = (int) $this->funcionarioRepo->count(['empresa' => $empresa, 'status' => 'ATIVO']);
            $users = (int) $this->userRepo->count(['empresa' => $empresa]);
            $projects = (int) $this->projetoRepo->count(['empresa' => $empresa]);
            $projectsActive = (int) $this->projetoRepo->count([
                'empresa' => $empresa,
                'status' => DevProjeto::STATUS_EM_ANDAMENTO,
            ]);
            $tasksTotal = (int) $this->tarefaRepo->count(['empresa' => $empresa]);
            $tasksDone = (int) $this->tarefaRepo->count([
                'empresa' => $empresa,
                'status' => DevTarefa::STATUS_CONCLUIDO,
            ]);
            $rhOpen = $this->workforce->countOpenRhProcesses($empresa);
            $deliveryRate = $tasksTotal > 0 ? (int) round(($tasksDone / $tasksTotal) * 100) : 0;

            $kpis = array_values(array_filter([
                $headcount > 0 ? $this->executiveKpi('headcount', 'Colaboradores', $headcount, 'fa-users', 'Cadastro total') : null,
                $ativos > 0 ? $this->executiveKpi('active-people', 'Pessoas ativas', $ativos, 'fa-user-check', 'Status operacional') : null,
                $users > 0 ? $this->executiveKpi('users', 'Usuários', $users, 'fa-id-badge', 'Contas na plataforma') : null,
                $projects > 0 ? $this->executiveKpi('projects', 'Projetos', $projects, 'fa-folder-tree', $projectsActive . ' em andamento') : null,
                $tasksTotal > 0 ? $this->executiveKpi('delivery', 'Entrega', $deliveryRate, 'fa-gauge-high', $tasksDone . ' de ' . $tasksTotal . ' tarefas', '%') : null,
                $rhOpen > 0 ? $this->executiveKpi('rh-open', 'RH em aberto', $rhOpen, 'fa-user-clock', 'Admissões e desligamentos') : null,
            ]));
        } elseif ($isTenant) {
            $empresas = $this->countEmpresasAtivas();
            $users = (int) $this->userRepo->count([]);
            $kpis = [
                $this->executiveKpi('companies', 'Empresas', $empresas['values'][0], 'fa-building', 'Ativas na plataforma'),
                $this->executiveKpi('users-global', 'Usuários', $users, 'fa-globe', 'Contas globais'),
            ];
        }

        return ['kpis' => $kpis];
    }

    /** @return array<string, mixed> */
    private function executiveKpi(
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
}
