<?php

namespace App\Service\Analytics;

use App\Chart\ChartConfig;
use App\Entity\DevTarefa;
use App\Entity\Empresa;
use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Entity\User;
use App\Repository\DevTarefaRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Repository\UserRepository;
use App\Service\NavigationService;

final class GrowthAnalyticsService
{
    use ChartAnalyticsTrait;

    public function __construct(
        private UserRepository $userRepo,
        private FuncionarioRepository $funcionarioRepo,
        private DevTarefaRepository $tarefaRepo,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhOffboardingProcessRepository $offboardingRepo,
        private NavigationService $navigation,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buildSections(User $user, ?Empresa $empresa): array
    {
        $hasGlobalScope = $user->hasPlatformAccess();

        $growthCharts = array_values(array_filter([
            $this->buildEvolutionChart($user, $empresa, $hasGlobalScope),
            $empresa !== null ? $this->buildTaskVelocityChart($empresa) : null,
            $empresa !== null ? $this->buildRhActivityChart($empresa) : null,
        ]));

        if ($growthCharts === []) {
            return [];
        }

        return [
            $this->makeSection(
                'growth-trends',
                'Growth & Trends',
                'Séries temporais de cadastros, entregas e movimentações RH',
                'fa-chart-line',
                'executive',
                'Executive Trends',
                $growthCharts,
            ),
        ];
    }

    /** @return ?array<string, mixed> */
    private function buildEvolutionChart(User $user, ?Empresa $empresa, bool $isTenant): ?array
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

        $chart = ChartConfig::areaLine(
            'evolution-registrations',
            'Novos cadastros (6 meses)',
            $evolution['labels'],
            $datasets,
            'Série temporal executiva de crescimento de contas e colaboradores',
        )->toArray();
        $chart['size'] = 'hero';

        return $this->withKpi(
            $chart,
            'Total no período',
            array_sum($evolution['users']) + array_sum($evolution['funcionarios']),
        );
    }

    /** @return ?array<string, mixed> */
    private function buildTaskVelocityChart(Empresa $empresa): ?array
    {
        $velocity = $this->countEntityRegistrationsLastMonths($empresa, DevTarefa::class, 6);
        if (!$this->hasValues($velocity['values'])) {
            return null;
        }

        $chart = ChartConfig::areaLine(
            'task-velocity',
            'Velocidade de tarefas criadas',
            $velocity['labels'],
            [['label' => 'Novas tarefas', 'data' => $velocity['values']]],
            'Throughput mensal de criação de entregas no portfólio',
        )->toArray();

        return $this->withKpi($chart, 'Total', array_sum($velocity['values']));
    }

    /** @return ?array<string, mixed> */
    public function buildRhActivityChart(Empresa $empresa): ?array
    {
        $onboarding = $this->countEntityRegistrationsLastMonths($empresa, RhOnboardingProcess::class, 6);
        $offboarding = $this->countEntityRegistrationsLastMonths($empresa, RhOffboardingProcess::class, 6);

        if (!$this->hasValues($onboarding['values']) && !$this->hasValues($offboarding['values'])) {
            return null;
        }

        $datasets = array_values(array_filter([
            $this->hasValues($onboarding['values'])
                ? ['label' => 'Admissões', 'data' => $onboarding['values']]
                : null,
            $this->hasValues($offboarding['values'])
                ? ['label' => 'Desligamentos', 'data' => $offboarding['values']]
                : null,
        ]));

        $chart = ChartConfig::areaLine(
            'rh-activity-trend',
            'Movimentações RH (6 meses)',
            $onboarding['labels'],
            $datasets,
            'Pulsos mensais de admissões e desligamentos abertos',
        )->toArray();

        return $this->withKpi(
            $chart,
            'Processos',
            array_sum($onboarding['values']) + array_sum($offboarding['values']),
        );
    }

    /**
     * Seção compacta de tendências RH para o hub /rh.
     *
     * @return list<array<string, mixed>>
     */
    public function buildRhHubSections(User $user, Empresa $empresa): array
    {
        if (!$this->navigation->showModuloRh($user)) {
            return [];
        }

        $charts = array_values(array_filter([
            $this->buildRhActivityChart($empresa),
        ]));

        if ($charts === []) {
            return [];
        }

        return [
            $this->makeSection(
                'rh-trends',
                'Tendências RH',
                'Pulsos mensais de admissões e desligamentos',
                'fa-chart-line',
                'executive',
                'Time Series',
                $charts,
            ),
        ];
    }
}
