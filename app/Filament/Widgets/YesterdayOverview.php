<?php

namespace App\Filament\Widgets;

use App\Reports\ReportService;
use App\Support\DateRange;
use App\Support\GridColumns;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Yesterday's bills paid, service charge and profit, whatever period the
 * dashboard filter is set to.
 */
class YesterdayOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function range(): DateRange
    {
        return DateRange::fromPreset('yesterday');
    }

    protected function getHeading(): ?string
    {
        return __('Yesterday (:date)', ['date' => $this->range()->label()]);
    }

    protected function getColumns(): int
    {
        return GridColumns::balanced(count($this->getCachedStats()));
    }

    protected function getStats(): array
    {
        $summary = app(ReportService::class)->summary($this->range());

        $stats = [
            Stat::make(__('Bills paid'), Money::format($summary['bill_amount']))
                ->description(trans_choice(':count entry|:count entries', $summary['entries']))
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentList)
                ->color('primary'),
            Stat::make(__('Service charge'), Money::format($summary['service_charge']))
                ->description(__('Charged to customers'))
                ->descriptionIcon(Heroicon::OutlinedReceiptPercent)
                ->color('info'),
        ];

        if (auth()->user()?->isAdmin()) {
            $stats[] = Stat::make(__('Profit'), Money::format($summary['net_profit']))
                ->description(__('Service charge − portal deduction'))
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color(Money::toPaise($summary['net_profit']) < 0 ? 'danger' : 'success');
        }

        return $stats;
    }
}
