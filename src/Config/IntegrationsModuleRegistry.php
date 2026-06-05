<?php

namespace App\Config;

/**
 * Módulos do Núcleo Integrações (sidebar + rotas).
 */
final class IntegrationsModuleRegistry
{
    /** @var list<array{id: string, label: string, icon: string, route: string, subtitle: string, group: string}> */
    public const MODULES = [
        [
            'id' => 'overview',
            'label' => 'Visão Geral',
            'icon' => 'fa-gauge-high',
            'route' => 'app_integracoes',
            'subtitle' => 'Painel de conectividade',
            'group' => 'core',
        ],
        [
            'id' => 'catalogo',
            'label' => 'Catálogo',
            'icon' => 'fa-store',
            'route' => 'app_integracoes_catalogo',
            'subtitle' => 'Conectores disponíveis',
            'group' => 'conexoes',
        ],
        [
            'id' => 'conectores',
            'label' => 'Meus conectores',
            'icon' => 'fa-plug',
            'route' => 'app_integracoes_conectores',
            'subtitle' => 'Integrações ativas',
            'group' => 'conexoes',
        ],
        [
            'id' => 'webhooks',
            'label' => 'Webhooks',
            'icon' => 'fa-bolt',
            'route' => 'app_integracoes_webhooks',
            'subtitle' => 'Eventos entrada e saída',
            'group' => 'conexoes',
        ],
        [
            'id' => 'mapeamentos',
            'label' => 'Mapeamentos',
            'icon' => 'fa-right-left',
            'route' => 'app_integracoes_mapeamentos',
            'subtitle' => 'Campos e transformações',
            'group' => 'dados',
        ],
        [
            'id' => 'api_keys',
            'label' => 'API & chaves',
            'icon' => 'fa-key',
            'route' => 'app_integracoes_api',
            'subtitle' => 'Acesso programático',
            'group' => 'dados',
        ],
        [
            'id' => 'observatorio',
            'label' => 'Observatório Causal',
            'icon' => 'fa-diagram-project',
            'route' => 'app_integracoes_observatorio',
            'subtitle' => 'Malha causal cross-núcleo',
            'group' => 'observabilidade',
        ],
        [
            'id' => 'logs',
            'label' => 'Logs',
            'icon' => 'fa-list-ul',
            'route' => 'app_integracoes_logs',
            'subtitle' => 'Eventos e auditoria',
            'group' => 'observabilidade',
        ],
        [
            'id' => 'playbooks',
            'label' => 'Playbooks',
            'icon' => 'fa-book-open',
            'route' => 'app_integracoes_playbooks',
            'subtitle' => 'Guias de integração',
            'group' => 'observabilidade',
        ],
    ];

    /** @return list<array{id: string, label: string, icon: string, route: string, subtitle: string, group: string}> */
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

    public static function findById(string $id): ?array
    {
        foreach (self::MODULES as $module) {
            if ($module['id'] === $id) {
                return $module;
            }
        }

        return null;
    }
}
