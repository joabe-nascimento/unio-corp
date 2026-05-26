<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\DepartamentoRepository;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;
use App\Repository\EmpresaRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Repository\UserRepository;

/**
 * KPIs do dashboard a partir dos registros no banco.
 */
final class DashboardStatsService
{
    public function __construct(
        private FuncionarioRepository $funcionarioRepo,
        private DepartamentoRepository $departamentoRepo,
        private UserRepository $userRepo,
        private EmpresaRepository $empresaRepo,
        private DevProjetoRepository $projetoRepo,
        private DevTarefaRepository $tarefaRepo,
        private RhOnboardingProcessRepository $onboardingRepo,
        private NavigationService $navigation,
    ) {}

    /**
     * @return list<array{value: int, label: string, icon: string, route?: string}>
     */
    public function getKpis(User $user, ?Empresa $empresa, string $layout, int $empresasCount): array
    {
        $isTenant = $this->navigation->isTenant($user);
        $kpis = [];

        if ($empresa !== null) {
            $empresaId = $empresa->getId();
            $kpis[] = $this->kpi(
                (int) $this->funcionarioRepo->count(['empresa' => $empresaId]),
                'Funcionários',
                'fa-users',
            );
            $kpis[] = $this->kpi(
                (int) $this->departamentoRepo->count(['empresa' => $empresaId]),
                'Departamentos',
                'fa-sitemap',
            );

            if ($this->navigation->showModuloRh($user)) {
                $kpis[] = $this->kpi(
                    $this->onboardingRepo->countOpenByEmpresa($empresa),
                    'Admissões abertas',
                    'fa-user-plus',
                    'app_rh_admissoes',
                );
            }

            if ($this->navigation->showProjetosMetas($user)) {
                $kpis[] = $this->kpi(
                    $this->projetoRepo->countEmAndamento($empresa),
                    'Projetos ativos',
                    'fa-diagram-project',
                    'app_core_projetos',
                );
                $kpis[] = $this->kpi(
                    (int) $this->tarefaRepo->count(['empresa' => $empresaId]),
                    'Tarefas',
                    'fa-list-check',
                    'app_core_projetos',
                );
            }
        }

        if ($isTenant) {
            $kpis[] = $this->kpi(
                (int) $this->userRepo->count([]),
                'Usuários',
                'fa-user-shield',
                'app_admin_usuarios',
            );
            $kpis[] = $this->kpi(
                (int) $this->empresaRepo->count([]),
                'Empresas',
                'fa-building',
                'app_admin_empresas',
            );
        } elseif ($empresa !== null) {
            $kpis[] = $this->kpi(
                (int) $this->userRepo->count(['empresa' => $empresa->getId()]),
                'Usuários',
                'fa-user-shield',
            );
        }

        if ($layout === 'membro') {
            $kpis = array_values(array_filter($kpis, static fn (array $k): bool => \in_array($k['icon'], ['fa-list-check', 'fa-diagram-project'], true)));
            if ($kpis === [] && $empresa !== null) {
                $kpis[] = $this->kpi(
                    (int) $this->tarefaRepo->count(['empresa' => $empresa->getId()]),
                    'Minhas entregas',
                    'fa-list-check',
                    'app_core_projetos',
                );
            }
        }

        if ($layout === 'supervisor' || $layout === 'gestor') {
            $kpis = \array_slice($kpis, 0, 6);
        }

        if ($layout === 'tenant') {
            $kpis = \array_slice($kpis, 0, 8);
        }

        return $kpis;
    }

    public function getLayoutHeadline(string $layout, ?Empresa $empresa): array
    {
        return match ($layout) {
            'tenant' => [
                'icon' => 'fa-shield-halved',
                'title' => 'Gestão da plataforma',
                'subtitle' => 'Multi-empresa · hubs operacionais e configuração'
                    . ($empresa ? ' · ' . $empresa->getNome() : ''),
            ],
            'gestor' => [
                'icon' => 'fa-chart-pie',
                'title' => 'Visão do gestor',
                'subtitle' => 'Dados consolidados'
                    . ($empresa ? ' · ' . $empresa->getNome() : ''),
            ],
            'supervisor' => [
                'icon' => 'fa-user-group',
                'title' => 'Sua equipe',
                'subtitle' => 'Colaboradores sob sua supervisão'
                    . ($empresa ? ' · ' . $empresa->getNome() : ''),
            ],
            default => [
                'icon' => 'fa-gauge-high',
                'title' => 'Seu painel',
                'subtitle' => $empresa ? $empresa->getNome() : 'Área de trabalho',
            ],
        };
    }

    /** @return array{value: int, label: string, icon: string, route?: string} */
    private function kpi(int $value, string $label, string $icon, ?string $route = null): array
    {
        $item = ['value' => $value, 'label' => $label, 'icon' => $icon];
        if ($route !== null) {
            $item['route'] = $route;
        }

        return $item;
    }
}
