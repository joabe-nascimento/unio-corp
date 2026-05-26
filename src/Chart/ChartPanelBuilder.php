<?php

namespace App\Chart;

/**
 * Monta a estrutura JSON do painel de gráficos para os componentes Twig.
 */
final class ChartPanelBuilder
{
    /** @var list<ChartSectionConfig> */
    private array $sections = [];

    public function addSection(ChartSectionConfig $section): self
    {
        $this->sections[] = $section;

        return $this;
    }

    /**
     * @param list<ChartConfig> $charts
     */
    public function addSectionIfNotEmpty(
        string $id,
        string $title,
        string $subtitle,
        string $icon,
        array $charts,
    ): self {
        if ($charts === []) {
            return $this;
        }

        return $this->addSection(new ChartSectionConfig($id, $title, $subtitle, $icon, $charts));
    }

    /** @return list<array<string, mixed>> */
    public function build(): array
    {
        $out = [];
        foreach ($this->sections as $section) {
            $data = $section->toArray();
            if ($data !== null) {
                $out[] = $data;
            }
        }

        return $out;
    }
}
