<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesDateRange;
use App\Reports\ReportService;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Headline numbers for the selected period, plus what is owed right now.
 */
class PeriodStatsOverview extends StatsOverviewWidget
{
    use ResolvesDateRange;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('Summary for :period', ['period' => $this->dateRange()->label()]);
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $reports = app(ReportService::class);
        $range = $this->dateRange();
        $summary = $reports->summary($range);
        $position = $reports->currentPosition();
        $trend = $reports->trend($range, $this->grouping())['series'];

        $stats = [
            Stat::make(__('Bill volume'), Money::format($summary['bill_amount']))
                ->description(trans_choice(':count entry|:count entries', $summary['entries']))
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentList)
                ->chart($trend['bill_amount'])
                ->color('primary'),
            Stat::make(__('Service charge earned'), Money::format($summary['service_charge']))
                ->description(__('Total due from customers: :amount', ['amount' => Money::format($summary['total_due'])]))
                ->descriptionIcon(Heroicon::OutlinedReceiptPercent)
                ->chart($trend['service_charge'])
                ->color('info'),
            Stat::make(__('Collected from customers'), Money::format($summary['collected_amount']))
                ->description(trans_choice(':count payment received|:count payments received', $summary['payments_count']))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->chart($trend['collected_amount'])
                ->color('success'),
            Stat::make(__('Outstanding now'), Money::format($position['outstanding']))
                ->description(trans_choice(':count customer owes money|:count customers owe money', $position['customers_owing']))
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color(Money::toPaise($position['outstanding']) > 0 ? 'warning' : 'success'),
        ];

        if ($this->isAdmin()) {
            $stats[] = Stat::make(__('Net profit (booked)'), Money::format($summary['net_profit']))
                ->description(__('Service charge − portal deduction (:amount), by entry date', ['amount' => Money::format($summary['portal_deduction'])]))
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->chart($trend['net_profit'])
                ->color(Money::toPaise($summary['net_profit']) < 0 ? 'danger' : 'success');
            $stats[] = Stat::make(__('Net profit (collected)'), Money::format($summary['collected_profit']))
                ->description(trans_choice('From :count entry fully paid in this period|From :count entries fully paid in this period', $summary['settled_entries']))
                ->descriptionIcon(Heroicon::OutlinedCheckBadge)
                ->chart($trend['collected_profit'])
                ->color('success');
        }

        $stats[] = Stat::make(__('Overdue now'), Money::format($position['overdue']))
            ->description(trans_choice(':count entry past due date|:count entries past due date', $position['overdue_entries']))
            ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
            ->color($position['overdue_entries'] > 0 ? 'danger' : 'success');

        $stats[] = Stat::make(__('Outstanding from this period'), Money::format($summary['outstanding']))
            ->description(__('Still unpaid from entries made in this period'))
            ->descriptionIcon(Heroicon::OutlinedClock)
            ->color('gray');

        return $stats;
    }
}
