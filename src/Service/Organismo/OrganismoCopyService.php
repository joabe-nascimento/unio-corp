<?php

namespace App\Service\Organismo;

/**
 * Vocabulário UI da transição Organismo (novo termo ↔ legado interno).
 */
final class OrganismoCopyService
{
    /** @return array<string, string> */
    public function getGlobals(): array
    {
        return [
            'colonia' => 'Colônia',
            'colonia_artigo' => 'da colônia',
            'colonia_plural' => 'Colônias',
            'orquestrador' => 'Orquestrador',
            'pulso' => 'Pulso',
            'memoria' => 'Memória',
            'rede' => 'Rede',
            'lumen' => 'Lumen',
            'lumen_subtitle' => 'Consciência da colônia',
            'eco' => 'Eco',
            'praticas' => 'Práticas',
            'cena' => 'Cena',
            'cenas' => 'Cenas',
            'membro' => 'Membro',
            'membros' => 'Membros',
            'circulo' => 'Círculo',
            'observatorio' => 'Observatório',
            'presenca' => 'Presença',
            'inicio' => 'Pulso',
            'workspace_select' => 'Selecionar colônia',
            'workspace_switch' => 'Trocar colônia',
            'search_placeholder' => 'O que você quer fazer?',
            'search_filter_pratica' => 'Práticas',
            'search_filter_all' => 'Tudo',
        ];
    }

    public function colonia(): string
    {
        return 'Colônia';
    }

    public function pulso(): string
    {
        return 'Pulso';
    }

    public function lumen(): string
    {
        return 'Lumen';
    }
}
