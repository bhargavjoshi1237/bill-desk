<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesDateRange;
use App\Reports\ReportService;
use Filament\Widgets\ChartWidget;

/**
 * Horizontal bars of bill volume by portal or by bank for the period.
 * (Bars rather than a pie: with only a couple of portals a pie says little.)
 */
class VolumeByDimensionChart extends ChartWidget
{
    use ResolvesDateRange;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 3];

    protected ?string $maxHeight = '280px';

    /** 'portal' or 'bank'. */
    public string $dimension = 'portal';

    public function getHeading(): string
    {
        return $this->dimension === 'bank' ? __('Bill volume by bank') : __('Bill volume by portal');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $reports = app(ReportService::class);
        $rows = ($this->dimension === 'bank' ? $reports->byBank($this->dateRange()) : $reports->byPortal($this->dateRange()))
            ->take(10);

        return [
            'labels' => $rows->pluck('name')->values()->all(),
            'datasets' => [
                ChartPalette::bar(__('Bill volume'), $rows->pluck('bill_amount')->map(fn ($v): float => (float) $v)->values()->all()),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ChartPalette::options(legend: false, horizontal: true);
    }
}
