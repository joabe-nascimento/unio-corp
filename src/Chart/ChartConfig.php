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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }
}
