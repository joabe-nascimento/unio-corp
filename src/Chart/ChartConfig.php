<?php

namespace App\Chart;

/**
 * Definição de um gráfico Chart.js (serializável para Twig/JSON).
 */
final class ChartConfig
{
    /** @param list<string> $labels */
    /** @param list<int|float> $data */
  /** @param list<array{label: string, data: list<int|float>}> $datasets */
    private function __construct(private array $payload) {}

    /** @param list<string> $labels @param list<int|float> $data */
    public static function bar(
        string $id,
        string $title,
        array $labels,
        array $data,
        string $description = '',
        bool $horizontal = false,
    ): self {
        $payload = [
            'id' => $id,
            'type' => 'bar',
            'title' => $title,
            'description' => $description,
            'labels' => $labels,
            'data' => $data,
        ];
        if ($horizontal) {
            $payload['indexAxis'] = 'y';
        }

        return new self($payload);
    }

    /** @param list<string> $labels @param list<int|float> $data */
    public static function doughnut(
        string $id,
        string $title,
        array $labels,
        array $data,
        string $description = '',
    ): self {
        return new self([
            'id' => $id,
            'type' => 'doughnut',
            'title' => $title,
            'description' => $description,
            'labels' => $labels,
            'data' => $data,
        ]);
    }

    /** @param list<string> $labels @param list<int|float> $data */
    public static function pie(
        string $id,
        string $title,
        array $labels,
        array $data,
        string $description = '',
    ): self {
        return new self([
            'id' => $id,
            'type' => 'pie',
            'title' => $title,
            'description' => $description,
            'labels' => $labels,
            'data' => $data,
        ]);
    }

    /**
     * @param list<string> $labels
     * @param list<array{label: string, data: list<int|float>}> $datasets
     */
    public static function line(
        string $id,
        string $title,
        array $labels,
        array $datasets,
        string $description = '',
    ): self {
        return new self([
            'id' => $id,
            'type' => 'line',
            'title' => $title,
            'description' => $description,
            'labels' => $labels,
            'datasets' => $datasets,
        ]);
    }

    /**
     * @param list<string> $labels
     * @param list<array{label: string, data: list<int|float>}> $datasets
     */
    public static function areaLine(
        string $id,
        string $title,
        array $labels,
        array $datasets,
        string $description = '',
    ): self {
        return new self([
            'id' => $id,
            'type' => 'area-line',
            'engine' => 'echarts',
            'title' => $title,
            'description' => $description,
            'labels' => $labels,
            'datasets' => $datasets,
        ]);
    }

    /** @param list<array{name: string}> $nodes @param list<array{source: string, target: string, value: int|float}> $links */
    public static function sankey(
        string $id,
        string $title,
        array $nodes,
        array $links,
        string $description = '',
    ): self {
        return new self([
            'id' => $id,
            'type' => 'sankey',
            'engine' => 'echarts',
            'title' => $title,
            'description' => $description,
            'nodes' => $nodes,
            'links' => $links,
        ]);
    }

    /**
     * @param list<string> $xLabels
     * @param list<string> $yLabels
     * @param list<array{0: int, 1: int, 2: int|float}> $matrix [x, y, value]
     */
    public static function heatmap(
        string $id,
        string $title,
        array $xLabels,
        array $yLabels,
        array $matrix,
        string $description = '',
    ): self {
        return new self([
            'id' => $id,
            'type' => 'heatmap',
            'engine' => 'echarts',
            'title' => $title,
            'description' => $description,
            'xLabels' => $xLabels,
            'yLabels' => $yLabels,
            'matrix' => $matrix,
        ]);
    }

    /** @param list<array{name: string, value: list<int|float>}> $series */
    public static function radar(
        string $id,
        string $title,
        array $indicators,
        array $series,
        string $description = '',
    ): self {
        return new self([
            'id' => $id,
            'type' => 'radar',
            'engine' => 'echarts',
            'title' => $title,
            'description' => $description,
            'indicators' => $indicators,
            'series' => $series,
        ]);
    }

    /** @param list<array{x: int|float, y: int|float, r: int|float, label?: string}> $points */
    public static function bubble(
        string $id,
        string $title,
        array $points,
        string $description = '',
        ?string $xName = null,
        ?string $yName = null,
    ): self {
        return new self([
            'id' => $id,
            'type' => 'bubble',
            'engine' => 'echarts',
            'title' => $title,
            'description' => $description,
            'points' => $points,
            'xName' => $xName,
            'yName' => $yName,
        ]);
    }

    /**
     * @param list<string> $labels
     * @param list<array{label: string, data: list<int|float>}> $datasets
     */
    public static function stackedBar(
        string $id,
        string $title,
        array $labels,
        array $datasets,
        string $description = '',
        bool $horizontal = false,
    ): self {
        return new self([
            'id' => $id,
            'type' => 'stacked-bar',
            'engine' => 'echarts',
            'title' => $title,
            'description' => $description,
            'labels' => $labels,
            'datasets' => $datasets,
            'horizontal' => $horizontal,
        ]);
    }

    /** @param list<array{name: string, value: int|float}> $steps */
    public static function funnel(
        string $id,
        string $title,
        array $steps,
        string $description = '',
    ): self {
        return new self([
            'id' => $id,
            'type' => 'funnel',
            'engine' => 'echarts',
            'title' => $title,
            'description' => $description,
            'steps' => $steps,
        ]);
    }

    /** @param list<array{name: string, value?: int|float, children?: list<array<string, mixed>>}> $tree */
    public static function treemap(
        string $id,
        string $title,
        array $tree,
        string $description = '',
    ): self {
        return new self([
            'id' => $id,
            'type' => 'treemap',
            'engine' => 'echarts',
            'title' => $title,
            'description' => $description,
            'tree' => $tree,
        ]);
    }

    public static function gauge(
        string $id,
        string $title,
        float|int $value,
        float|int $max,
        string $description = '',
        ?string $unit = null,
    ): self {
        return new self([
            'id' => $id,
            'type' => 'gauge',
            'engine' => 'echarts',
            'title' => $title,
            'description' => $description,
            'value' => $value,
            'max' => $max,
            'unit' => $unit,
        ]);
    }

    /** @param list<string> $labels @param list<int|float> $data */
    public static function ring(
        string $id,
        string $title,
        array $labels,
        array $data,
        string $description = '',
    ): self {
        return new self([
            'id' => $id,
            'type' => 'ring',
            'engine' => 'echarts',
            'title' => $title,
            'description' => $description,
            'labels' => $labels,
            'data' => $data,
        ]);
    }

    /** @param list<string> $labels @param list<int|float> $data */
    public static function barPro(
        string $id,
        string $title,
        array $labels,
        array $data,
        string $description = '',
        bool $horizontal = false,
    ): self {
        return new self([
            'id' => $id,
            'type' => 'bar-pro',
            'engine' => 'echarts',
            'title' => $title,
            'description' => $description,
            'labels' => $labels,
            'data' => $data,
            'horizontal' => $horizontal,
        ]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }
}
