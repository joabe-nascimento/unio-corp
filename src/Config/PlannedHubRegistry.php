<?php

namespace App\Config;

/**
 * Hubs planejados (somente landing + sidebar, sem produtos ainda).
 *
 * @phpstan-type PlannedHub array{
 *     id: string,
 *     scope: string,
 *     route: string,
 *     path: string,
 *     label: string,
 *     icon: string,
 *     subtitle: string,
 *     empty_icon: string,
 *     empty_title: string,
 *     empty_text: string,
 * }
 */
final class PlannedHubRegistry
{
    /** @var list<PlannedHub> */
    public const HUBS = [
        [
            'id' => 'comercial',
            'scope' => 'hub_comercial',
            'route' => 'app_comercial',
            'path' => '/comercial',
            'label' => 'Hub Comercial',
            'icon' => 'fa-handshake',
            'subtitle' => 'CRM e pipeline de vendas',
            'empty_icon' => 'fa-handshake',
            'empty_title' => 'Hub Comercial em desenvolvimento',
            'empty_text' => 'CRM, pipeline de vendas, propostas e contratos comerciais estarão disponíveis em breve.',
        ],
        [
            'id' => 'beneficios',
            'scope' => 'hub_beneficios',
            'route' => 'app_beneficios',
            'path' => '/beneficios',
            'label' => 'Hub Benefícios',
            'icon' => 'fa-gift',
            'subtitle' => 'Marketplace corporativo',
            'empty_icon' => 'fa-gift',
            'empty_title' => 'Hub Benefícios em desenvolvimento',
            'empty_text' => 'Catálogo de benefícios, adesões e marketplace corporativo estarão disponíveis em breve.',
        ],
        [
            'id' => 'academy',
            'scope' => 'hub_academy',
            'route' => 'app_academy',
            'path' => '/academy',
            'label' => 'Hub Academy',
            'icon' => 'fa-graduation-cap',
            'subtitle' => 'Cursos e trilhas',
            'empty_icon' => 'fa-graduation-cap',
            'empty_title' => 'Hub Academy em desenvolvimento',
            'empty_text' => 'Cursos, trilhas de aprendizado e certificações estarão disponíveis em breve.',
        ],
        [
            'id' => 'parceiros',
            'scope' => 'hub_parceiros',
            'route' => 'app_parceiros',
            'path' => '/parceiros',
            'label' => 'Hub Parceiros',
            'icon' => 'fa-people-group',
            'subtitle' => 'Rede e revenda',
            'empty_icon' => 'fa-people-group',
            'empty_title' => 'Hub Parceiros em desenvolvimento',
            'empty_text' => 'Rede de parceiros, revenda white-label e comissionamento estarão disponíveis em breve.',
        ],
        [
            'id' => 'financeiro',
            'scope' => 'hub_financeiro',
            'route' => 'app_financeiro',
            'path' => '/financeiro',
            'label' => 'Hub Financeiro',
            'icon' => 'fa-coins',
            'subtitle' => 'Tesouraria e orçamento',
            'empty_icon' => 'fa-coins',
            'empty_title' => 'Hub Financeiro em desenvolvimento',
            'empty_text' => 'Orçamento de pessoal, tesouraria e integrações contábeis estarão disponíveis em breve.',
        ],
        [
            'id' => 'compliance',
            'scope' => 'hub_compliance',
            'route' => 'app_compliance',
            'path' => '/compliance',
            'label' => 'Hub Compliance',
            'icon' => 'fa-scale-balanced',
            'subtitle' => 'Normas e auditorias',
            'empty_icon' => 'fa-scale-balanced',
            'empty_title' => 'Hub Compliance em desenvolvimento',
            'empty_text' => 'eSocial, LGPD, obrigações legais e trilhas de auditoria estarão disponíveis em breve.',
        ],
        [
            'id' => 'analytics',
            'scope' => 'hub_analytics',
            'route' => 'app_analytics',
            'path' => '/analytics',
            'label' => 'Hub Analytics',
            'icon' => 'fa-chart-line',
            'subtitle' => 'BI e indicadores',
            'empty_icon' => 'fa-chart-line',
            'empty_title' => 'Hub Analytics em desenvolvimento',
            'empty_text' => 'Dashboards executivos, KPIs de RH e inteligência de dados estarão disponíveis em breve.',
        ],
        [
            'id' => 'juridico',
            'scope' => 'hub_juridico',
            'route' => 'app_juridico',
            'path' => '/juridico',
            'label' => 'Hub Jurídico',
            'icon' => 'fa-gavel',
            'subtitle' => 'Trabalhista e contratos',
            'empty_icon' => 'fa-gavel',
            'empty_title' => 'Hub Jurídico em desenvolvimento',
            'empty_text' => 'Contratos, processos trabalhistas e pareceres jurídicos estarão disponíveis em breve.',
        ],
        [
            'id' => 'clima',
            'scope' => 'hub_clima',
            'route' => 'app_clima',
            'path' => '/clima',
            'label' => 'Hub Clima',
            'icon' => 'fa-heart',
            'subtitle' => 'Engajamento e eNPS',
            'empty_icon' => 'fa-heart',
            'empty_title' => 'Hub Clima em desenvolvimento',
            'empty_text' => 'Pesquisas de clima, eNPS e planos de engajamento estarão disponíveis em breve.',
        ],
        [
            'id' => 'sst',
            'scope' => 'hub_sst',
            'route' => 'app_sst',
            'path' => '/sst',
            'label' => 'Hub SST',
            'icon' => 'fa-hard-hat',
            'subtitle' => 'Saúde e segurança',
            'empty_icon' => 'fa-hard-hat',
            'empty_title' => 'Hub SST em desenvolvimento',
            'empty_text' => 'PCMSO, gestão de EPIs e segurança do trabalho estarão disponíveis em breve.',
        ],
        [
            'id' => 'comunicacao',
            'scope' => 'hub_comunicacao',
            'route' => 'app_comunicacao',
            'path' => '/comunicacao',
            'label' => 'Hub Comunicação',
            'icon' => 'fa-bullhorn',
            'subtitle' => 'Mural e cultura',
            'empty_icon' => 'fa-bullhorn',
            'empty_title' => 'Hub Comunicação em desenvolvimento',
            'empty_text' => 'Comunicados internos, mural da empresa e campanhas culturais estarão disponíveis em breve.',
        ],
    ];

    public static function findById(string $id): ?array
    {
        foreach (self::HUBS as $hub) {
            if ($hub['id'] === $id) {
                return $hub;
            }
        }

        return null;
    }

    public static function findByRoute(?string $route): ?array
    {
        if ($route === null || $route === '') {
            return null;
        }

        foreach (self::HUBS as $hub) {
            if (str_starts_with($route, $hub['route'])) {
                return $hub;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function routePrefixes(): array
    {
        return array_column(self::HUBS, 'route');
    }

    /** @return list<string> */
    public static function scopes(): array
    {
        return array_column(self::HUBS, 'scope');
    }
}
