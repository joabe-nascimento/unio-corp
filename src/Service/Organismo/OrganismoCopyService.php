<?php

namespace App\Service\Organismo;

/**
 * Vocabulário e marca da shell Organismo (configurável por ambiente).
 */
final class OrganismoCopyService
{
    public function __construct(
        private string $brandName,
        private string $brandSlogan,
        private string $heroTitle,
        private string $heroTitleAccent,
        private string $heroDesc,
        private string $lumenSubtitle,
        private string $unitLabel,
        private string $unitLabelArtigo,
        private string $unitLabelPlural,
        private string $navMaturidade,
        private string $navSectionClients,
        private string $navPacientes,
        private string $navSalaCritica,
        private string $navAlertas,
        private string $navSectionDeliverables,
        private string $navProtocolos,
        private string $navQuestionarios,
        private string $navPortal,
        private string $pulsoProjectsActive,
        private string $pulsoInProgress,
        private string $pulsoCasesHeading,
        private string $pulsoKpisHeading,
        private string $pulsoEmpty,
        private string $hubHeroTitle,
        private string $marketingEyebrow,
        private string $marketingTagline,
    ) {
    }

    public function brandName(): string
    {
        return $this->brandName;
    }

    public function brandSlogan(): string
    {
        return $this->brandSlogan;
    }

    public function colonia(): string
    {
        return $this->unitLabel;
    }

    public function pulso(): string
    {
        return 'Pulso';
    }

    public function lumen(): string
    {
        return 'Lumen';
    }

    /** @return array<string, string> */
    public function getGlobals(): array
    {
        return [
            'brand_name' => $this->brandName,
            'brand_slogan' => $this->brandSlogan,
            'hero_title' => $this->heroTitle,
            'hero_title_accent' => $this->heroTitleAccent,
            'hero_desc' => $this->heroDesc,
            'colonia' => $this->unitLabel,
            'colonia_artigo' => $this->unitLabelArtigo,
            'colonia_plural' => $this->unitLabelPlural,
            'orquestrador' => 'Equipe',
            'pulso' => 'Pulso',
            'memoria' => 'Memória',
            'rede' => 'Rede',
            'lumen' => 'Lumen',
            'lumen_subtitle' => $this->lumenSubtitle,
            'eco' => 'Eco',
            'praticas' => 'Práticas',
            'cena' => 'Entrega',
            'cenas' => 'Entregas',
            'membro' => 'Membro',
            'membros' => 'Membros',
            'circulo' => 'Círculo',
            'observatorio' => 'Observatório',
            'presenca' => 'Presença',
            'inicio' => 'Pulso',
            'search_placeholder' => 'O que você quer fazer?',
            'search_filter_pratica' => 'Práticas',
            'search_filter_all' => 'Tudo',
            'nav_maturidade' => $this->navMaturidade,
            'nav_section_clients' => $this->navSectionClients,
            'nav_pacientes' => $this->navPacientes,
            'nav_sala_critica' => $this->navSalaCritica,
            'nav_alertas' => $this->navAlertas,
            'nav_section_deliverables' => $this->navSectionDeliverables,
            'nav_protocolos' => $this->navProtocolos,
            'nav_questionarios' => $this->navQuestionarios,
            'nav_portal' => $this->navPortal,
            'pulso_projects_active' => $this->pulsoProjectsActive,
            'pulso_in_progress' => $this->pulsoInProgress,
            'pulso_cases_heading' => $this->pulsoCasesHeading,
            'pulso_kpis_heading' => $this->pulsoKpisHeading,
            'pulso_empty' => $this->pulsoEmpty,
            'hub_hero_title' => $this->hubHeroTitle,
            'marketing_eyebrow' => $this->marketingEyebrow,
            'marketing_tagline' => $this->marketingTagline,
        ];
    }
}
