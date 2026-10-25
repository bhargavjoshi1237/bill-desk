<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasConfigurableSpan;
use App\Filament\Widgets\Concerns\ResolvesDateRange;
use App\Reports\ReportService;
use Filament\Widgets\ChartWidget;

/**
 * Booked vs collected profit (admins only). Kept apart from the volume chart
 * because profit is a far smaller number and would flatten on a shared axis.
 */
class ProfitTrendChart extends ChartWidget
{
    use HasConfigurableSpan;
    use ResolvesDateRange;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 3];

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getHeading(): string
    {
        return __('Net profit');
    }

    public function getDescription(): string
    {
        return __('Booked on the entry date; collected when the entry is fully paid.');
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
                ChartPalette::line(__('Booked profit'), $trend['series']['net_profit'], 0),
                ChartPalette::line(__('Collected profit'), $trend['series']['collected_profit'], 1),
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ChartPalette::options();
    }
}
