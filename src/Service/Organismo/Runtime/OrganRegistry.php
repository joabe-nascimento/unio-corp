<?php

namespace App\Service\Organismo\Runtime;

/** Catálogo dos órgãos do Organismo Runtime (Unio Saúde). */
final class OrganRegistry
{
    public const MONITORAMENTO = 'monitoramento';
    public const AGENDA = 'agenda';
    public const EXPERIENCIA = 'experiencia';
    public const FINANCEIRO = 'financeiro';
    public const MEMORIA = 'memoria';

    /**
     * @return list<array{id: string, label: string, icon: string, weight: float}>
     */
    public function all(): array
    {
        return [
            ['id' => self::MONITORAMENTO, 'label' => 'Monitoramento', 'icon' => 'fa-heart-pulse', 'weight' => 0.30],
            ['id' => self::AGENDA, 'label' => 'Agenda', 'icon' => 'fa-calendar-alt', 'weight' => 0.20],
            ['id' => self::EXPERIENCIA, 'label' => 'Experiência', 'icon' => 'fa-hand-holding-medical', 'weight' => 0.25],
            ['id' => self::FINANCEIRO, 'label' => 'Financeiro', 'icon' => 'fa-receipt', 'weight' => 0.15],
            ['id' => self::MEMORIA, 'label' => 'Memória', 'icon' => 'fa-brain', 'weight' => 0.10],
        ];
    }
}
