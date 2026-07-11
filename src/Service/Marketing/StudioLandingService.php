<?php

namespace App\Service\Marketing;

use App\Config\PlannedHubRegistry;

/**
 * Conteúdo da landing do site central Unio (uniowork.com.br).
 */
final class StudioLandingService
{
    /** @return list<array<string, mixed>> */
    public function verticals(): array
    {
        return [
            [
                'id' => 'saude',
                'label' => 'Saúde',
                'title' => 'Unio Saúde',
                'desc' => 'Pós-operatório, carteirinha digital e guia médico para clínicas.',
                'icon' => 'fa-heart-pulse',
                'theme' => 'saude',
                'url' => 'https://uniosaude.uniowork.com.br',
                'badge' => 'Produto ativo',
                'external' => true,
            ],
            [
                'id' => 'educacao',
                'label' => 'Educação',
                'title' => 'Instituições de ensino',
                'desc' => 'Portais, comunicação e operações para faculdades e redes educacionais.',
                'icon' => 'fa-graduation-cap',
                'theme' => 'educacao',
                'url' => '#cases-reais',
                'badge' => 'Case UNEF',
                'external' => false,
            ],
            [
                'id' => 'corporativo',
                'label' => 'Corporativo',
                'title' => 'Operações & RH',
                'desc' => 'Hubs de pessoas, engenharia, finanças e governança em um só organismo.',
                'icon' => 'fa-building',
                'theme' => 'corp',
                'url' => '#modulos-studio',
                'badge' => 'Plataforma',
                'external' => false,
            ],
        ];
    }

    /** @return list<array<string, string>> */
    public function hubs(): array
    {
        return [
            [
                'label' => 'Pessoas & RH',
                'desc' => 'Recrutamento, cargos, folha e portal do colaborador.',
                'icon' => 'fa-users',
                'route' => 'app_rh',
            ],
            [
                'label' => 'Engenharia',
                'desc' => 'Projetos, entregas e playbooks de implementação.',
                'icon' => 'fa-compass-drafting',
                'route' => 'app_engenharia',
            ],
            [
                'label' => 'Pós-operatório',
                'desc' => 'Acompanhamento clínico modular para clínicas e hospitais.',
                'icon' => 'fa-user-nurse',
                'route' => 'app_pos_operatorio',
            ],
            [
                'label' => 'TI & Inovação',
                'desc' => 'Chamados, integrações e experimentação controlada.',
                'icon' => 'fa-microchip',
                'route' => 'app_ti',
            ],
            [
                'label' => 'Financeiro',
                'desc' => 'Indicadores, compliance e trilhas de aprovação.',
                'icon' => 'fa-chart-pie',
                'route' => 'app_financeiro',
            ],
            [
                'label' => 'Lumen',
                'desc' => 'Assistente contextual para equipes e gestores.',
                'icon' => 'fa-wand-magic-sparkles',
                'route' => 'app_login',
            ],
        ];
    }

    /** @return list<array<string, string>> */
    public function cases(): array
    {
        return [
            [
                'name' => 'União Médica',
                'sector' => 'Saúde',
                'logo' => 'uniao-medica.jpg',
                'text' => 'Operação clínica e comunicação com pacientes em ambiente digital unificado.',
            ],
            [
                'name' => 'UNEF',
                'sector' => 'Educação',
                'logo' => 'unef.svg',
                'text' => 'Presença digital e sistemas para instituição de ensino superior.',
            ],
        ];
    }
}
