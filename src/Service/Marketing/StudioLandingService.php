<?php

namespace App\Service\Marketing;

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
                'desc' => 'Módulos de pessoas, engenharia, finanças e governança em um só organismo.',
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
                'image' => 'https://images.unsplash.com/photo-1521737711866-ece3fd7a9fca?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'label' => 'Engenharia',
                'desc' => 'Projetos, entregas e playbooks de implementação.',
                'icon' => 'fa-compass-drafting',
                'route' => 'app_engenharia',
                'image' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'label' => 'Pós-operatório',
                'desc' => 'Acompanhamento clínico modular para clínicas e hospitais.',
                'icon' => 'fa-user-nurse',
                'route' => 'app_pos_operatorio',
                'image' => 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'label' => 'TI & Inovação',
                'desc' => 'Chamados, integrações e experimentação controlada.',
                'icon' => 'fa-microchip',
                'route' => 'app_ti',
                'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'label' => 'Financeiro',
                'desc' => 'Indicadores, compliance e trilhas de aprovação.',
                'icon' => 'fa-chart-pie',
                'route' => 'app_financeiro',
                'image' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'label' => 'Operações',
                'desc' => 'Governança, indicadores e fluxo entre áreas.',
                'icon' => 'fa-sitemap',
                'route' => 'app_hub_operacoes',
                'image' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=800&q=80',
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
