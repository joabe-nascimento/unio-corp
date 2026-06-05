<?php

namespace App\Config;

/**
 * Módulos do Núcleo Inovação (sidebar + rotas).
 */
final class InnovationModuleRegistry
{
  /** @var list<array{id: string, label: string, icon: string, route: string, subtitle: string}> */
    public const MODULES = [
        [
            'id' => 'overview',
            'label' => 'Visão Geral',
            'icon' => 'fa-gauge-high',
            'route' => 'app_inovacao',
            'subtitle' => 'Command center',
        ],
        [
            'id' => 'pipeline',
            'label' => 'Pipeline',
            'icon' => 'fa-layer-group',
            'route' => 'app_inovacao_pipeline',
            'subtitle' => 'Funil de experimentos',
        ],
        [
            'id' => 'laboratorio',
            'label' => 'Laboratório',
            'icon' => 'fa-flask',
            'route' => 'app_inovacao_laboratorio',
            'subtitle' => 'POCs e hipóteses',
        ],
        [
            'id' => 'experimentos',
            'label' => 'Experimentos',
            'icon' => 'fa-vial',
            'route' => 'app_inovacao_experimentos',
            'subtitle' => 'Kill · Pivot · Scale',
        ],
        [
            'id' => 'backlog',
            'label' => 'Backlog de Ideias',
            'icon' => 'fa-lightbulb',
            'route' => 'app_inovacao_backlog',
            'subtitle' => 'Captura rápida',
        ],
        [
            'id' => 'analytics',
            'label' => 'Radar & Analytics',
            'icon' => 'fa-chart-pie',
            'route' => 'app_inovacao_analytics',
            'subtitle' => 'Maturidade e funil',
        ],
        [
            'id' => 'conexoes',
            'label' => 'Conexões',
            'icon' => 'fa-link',
            'route' => 'app_inovacao_conexoes',
            'subtitle' => 'Cross-hub',
        ],
        [
            'id' => 'tendencias',
            'label' => 'Tendências',
            'icon' => 'fa-satellite-dish',
            'route' => 'app_inovacao_tendencias',
            'subtitle' => 'Radar de tecnologias',
        ],
        [
            'id' => 'portfolio',
            'label' => 'Portfólio',
            'icon' => 'fa-trophy',
            'route' => 'app_inovacao_portfolio',
            'subtitle' => 'Inovações escaladas',
        ],
        [
            'id' => 'impact',
            'label' => 'Impacto',
            'icon' => 'fa-coins',
            'route' => 'app_inovacao_impact',
            'subtitle' => 'ROI e ledger',
        ],
        [
            'id' => 'novidades',
            'label' => 'Novidades',
            'icon' => 'fa-newspaper',
            'route' => 'app_inovacao_novidades',
            'subtitle' => 'Feed de inovação',
        ],
        [
            'id' => 'nova_ideia',
            'label' => 'Nova Ideia',
            'icon' => 'fa-plus-circle',
            'route' => 'app_inovacao_nova_ideia',
            'subtitle' => 'Registrar ideia',
        ],
    ];

    /**
     * @return list<array{id: string, label: string, icon: string, route: string, subtitle: string}>
     */
    public static function all(): array
    {
        return self::MODULES;
    }

    public static function findByRoute(string $route): ?array
    {
        foreach (self::MODULES as $module) {
            if ($module['route'] === $route) {
                return $module;
            }
        }

        return null;
    }
}
