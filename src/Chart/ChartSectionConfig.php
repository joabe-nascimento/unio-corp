<?php

namespace App\Chart;

/**
 * Grupo de gráficos (seção dentro de um painel).
 */
final class ChartSectionConfig
{
    /** @param list<ChartConfig> $charts */
    public function __construct(
        private string $id,
        private string $title,
        private string $subtitle,
        private string $icon,
        private array $charts,
    ) {}

    /** @return array<string, mixed>|null null se não houver gráficos com dados */
    public function toArray(): ?array
    {
        $charts = [];
        foreach ($this->charts as $chart) {
            $charts[] = $chart->toArray();
        }

        if ($charts === []) {
            return null;
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'icon' => $this->icon,
            'charts' => $charts,
        ];
    }
}
