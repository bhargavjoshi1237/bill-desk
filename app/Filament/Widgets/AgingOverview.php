<?php

namespace App\Filament\Widgets;

use App\Reports\ReportService;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Total outstanding split into 0–7, 8–30 and 30+ day aging buckets.
 */
class AgingOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('Outstanding by age');
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $reports = app(ReportService::class);
        $position = $reports->currentPosition();
        $colors = ['0_7' => 'success', '8_30' => 'warning', '31_plus' => 'danger'];

        return [
            Stat::make(__('Total outstanding'), Money::format($position['outstanding']))
                ->description(trans_choice(':count open entry|:count open entries', $position['open_entries']).' · '
                    .trans_choice(':count customer|:count customers', $position['customers_owing']))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
            ...collect($reports->aging())->map(fn (array $bucket, string $key): Stat => Stat::make($bucket['label'], Money::format($bucket['outstanding']))
                ->description(trans_choice(':count open entry|:count open entries', $bucket['entries']))
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($colors[$key]))->values()->all(),
        ];
    }
}
