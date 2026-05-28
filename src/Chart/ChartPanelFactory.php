<?php

namespace App\Chart;

final class ChartPanelFactory
{
    /**
     * @param list<array<string, mixed>> $sections
     * @param array{kpis?: list<array<string, mixed>>} $executive
     *
     * @return array{
     *     sections: list<array<string, mixed>>,
     *     executive: array{kpis: list<array<string, mixed>>},
     *     meta: array{chart_count: int, section_count: int, generated_at: string}
     * }
     */
    public function wrap(array $sections, array $executive = ['kpis' => []]): array
    {
        $chartCount = 0;
        foreach ($sections as $section) {
            $chartCount += \count($section['charts'] ?? []);
        }

        return [
            'sections' => $sections,
            'executive' => [
                'kpis' => $executive['kpis'] ?? [],
            ],
            'meta' => [
                'chart_count' => $chartCount,
                'section_count' => \count($sections),
                'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
        ];
    }
}
