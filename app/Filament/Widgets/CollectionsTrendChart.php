<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasConfigurableSpan;
use App\Filament\Widgets\Concerns\ResolvesDateRange;
use App\Reports\ReportService;
use Filament\Widgets\ChartWidget;

/**
 * Bill volume paid out vs money collected back, per day/week/month.
 */
class CollectionsTrendChart extends ChartWidget
{
    use HasConfigurableSpan;
    use ResolvesDateRange;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 3];

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return __('Bill volume vs collections');
    }

    public function getDescription(): string
    {
        return __('Card bills paid for customers, and money received back from them.');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $trend = app(ReportService::class)->trend($this->dateRange(), $this->grouping());

        return [
            'labels' => $trend['labels'],
            'datasets' => [
                ChartPalette::line(__('Bill volume'), $trend['series']['bill_amount'], 0),
                ChartPalette::line(__('Collected from customers'), $trend['series']['collected_amount'], 1),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ChartPalette::options();
    }
}
