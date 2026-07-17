<?php

namespace App\Service\Analytics;

trait ClinicChartAnalyticsTrait
{
    /**
     * @param list<array<string, mixed>> $charts
     *
     * @return array<string, mixed>
     */
    private function makeSection(
        string $id,
        string $title,
        string $subtitle,
        string $icon,
        string $tier,
        string $badge,
        array $charts,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'icon' => $icon,
            'tier' => $tier,
            'badge' => $badge,
            'charts' => $charts,
        ];
    }

    /** @param array<string, mixed> $chart @return array<string, mixed> */
    private function withKpi(array $chart, string $label, int|float $value): array
    {
        $chart['kpi'] = ['label' => $label, 'value' => $value];

        return $chart;
    }

    /** @param list<int|float> $values */
    private function hasValues(array $values): bool
    {
        return array_sum($values) > 0;
    }

    /** @return array<string, mixed> */
    private function executiveKpi(
        string $id,
        string $label,
        int|float $value,
        string $icon,
        string $hint,
        ?string $suffix = null,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'value' => $value,
            'icon' => $icon,
            'hint' => $hint,
            'suffix' => $suffix,
        ];
    }
}
