<?php

namespace App\Config;

/** Módulos do Núcleo TI — sidebar e navegação. */
final class TiModuleRegistry
{
    /** @var list<array{id: string, label: string, icon: string, route: string, subtitle: string, group: string}> */
    public const MODULES = [
        ['id' => 'overview', 'label' => 'NOC Center', 'icon' => 'fa-tower-broadcast', 'route' => 'app_ti', 'subtitle' => 'Command center', 'group' => 'core'],
        ['id' => 'chamados', 'label' => 'Chamados', 'icon' => 'fa-headset', 'route' => 'app_ti_chamados', 'subtitle' => 'Service desk', 'group' => 'ops'],
        ['id' => 'catalogo', 'label' => 'Catálogo', 'icon' => 'fa-grid-2', 'route' => 'app_ti_catalogo', 'subtitle' => 'Serviços de TI', 'group' => 'ops'],
        ['id' => 'kb', 'label' => 'Base de Conhecimento', 'icon' => 'fa-book', 'route' => 'app_ti_kb', 'subtitle' => 'Artigos e runbooks', 'group' => 'ops'],
        ['id' => 'problemas', 'label' => 'Problemas', 'icon' => 'fa-diagram-project', 'route' => 'app_ti_problemas', 'subtitle' => 'Gestão de problemas', 'group' => 'ops'],
        ['id' => 'meus_chamados', 'label' => 'Meus Chamados', 'icon' => 'fa-user', 'route' => 'app_ti_meus_chamados', 'subtitle' => 'Portal solicitante', 'group' => 'ops'],
        ['id' => 'sla', 'label' => 'SLA & Prioridades', 'icon' => 'fa-gauge-high', 'route' => 'app_ti_sla', 'subtitle' => 'Metas e heatmap', 'group' => 'ops'],
        ['id' => 'manutencoes', 'label' => 'Manutenções', 'icon' => 'fa-screwdriver-wrench', 'route' => 'app_ti_manutencoes', 'subtitle' => 'Janelas programadas', 'group' => 'ops'],
        ['id' => 'ativos', 'label' => 'Ativos', 'icon' => 'fa-laptop', 'route' => 'app_ti_ativos', 'subtitle' => 'Inventário', 'group' => 'infra'],
        ['id' => 'licencas', 'label' => 'Licenças', 'icon' => 'fa-key', 'route' => 'app_ti_licencas', 'subtitle' => 'Software & seats', 'group' => 'infra'],
        ['id' => 'integracoes', 'label' => 'Integrações', 'icon' => 'fa-plug', 'route' => 'app_ti_integracoes', 'subtitle' => 'APIs & webhooks', 'group' => 'infra'],
        ['id' => 'cortex', 'label' => 'Cortex Ops', 'icon' => 'fa-brain', 'route' => 'app_ti_cortex', 'subtitle' => 'Triagem IA', 'group' => 'intel'],
        ['id' => 'analytics', 'label' => 'Analytics', 'icon' => 'fa-chart-line', 'route' => 'app_ti_analytics', 'subtitle' => 'Métricas operacionais', 'group' => 'intel'],
        ['id' => 'novidades', 'label' => 'Novidades', 'icon' => 'fa-bullhorn', 'route' => 'app_ti_novidades', 'subtitle' => 'Comunicados TI', 'group' => 'intel'],
        ['id' => 'novo_chamado', 'label' => 'Abrir Chamado', 'icon' => 'fa-plus', 'route' => 'app_ti_chamado_novo', 'subtitle' => 'Novo ticket', 'group' => 'cta'],
    ];

    /** @return list<array{id: string, label: string, icon: string, route: string, subtitle: string, group: string}> */
    public static function all(): array
    {
        return self::MODULES;
    }
}
