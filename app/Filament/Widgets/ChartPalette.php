<?php

namespace App\Filament\Widgets;

/**
 * Categorical chart colours, always assigned in this order so a series keeps
 * its colour across charts. Validated for colour-blind separation.
 */
final class ChartPalette
{
    public const SERIES = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];

    /**
     * Shared Chart.js options: thin lines, recessive grid, index tooltips.
     *
     * @return array<string, mixed>
     */
    public static function options(bool $legend = true, bool $horizontal = false): array
    {
        $valueAxis = [
            'beginAtZero' => true,
            'grid' => ['color' => 'rgba(127, 127, 127, 0.15)'],
            'border' => ['display' => false],
            'ticks' => ['maxTicksLimit' => 6],
        ];

        $categoryAxis = [
            'grid' => ['display' => false],
            'ticks' => ['autoSkip' => true, 'maxRotation' => 0],
        ];

        return [
            'indexAxis' => $horizontal ? 'y' : 'x',
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => ['display' => $legend, 'position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8]],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
            ],
            'scales' => $horizontal
                ? ['x' => $valueAxis, 'y' => $categoryAxis]
                : ['x' => $categoryAxis, 'y' => $valueAxis],
        ];
    }

    /**
     * @param  array<float>  $data
     * @return array<string, mixed>
     */
    public static function line(string $label, array $data, int $slot): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'borderColor' => self::SERIES[$slot],
            'backgroundColor' => self::SERIES[$slot],
            'borderWidth' => 2,
            'pointRadius' => count($data) > 31 ? 0 : 3,
            'pointHoverRadius' => 5,
            'tension' => 0.25,
            'fill' => false,
        ];
    }

    /**
     * @param  array<float>  $data
     * @return array<string, mixed>
     */
    public static function bar(string $label, array $data, int $slot = 0): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'backgroundColor' => self::SERIES[$slot],
            'borderRadius' => 4,
            'borderSkipped' => 'start',
            'maxBarThickness' => 28,
        ];
    }
}
