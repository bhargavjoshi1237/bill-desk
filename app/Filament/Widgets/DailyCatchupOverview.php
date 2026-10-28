<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\DailyCatchup as DailyCatchupPage;
use App\Reports\DailyCatchup;
use App\Reports\ReportService;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Headline numbers for the daily catch-up: where the money sits at the end
 * of the day, what moved that day, and the fees still to collect.
 */
class DailyCatchupOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $date = DailyCatchupPage::dateFrom($this->pageFilters);
        $totals = (new DailyCatchup($date))->totals();
        $position = app(ReportService::class)->currentPosition();

        return [
            Stat::make(__('Total balance'), Money::format($totals['total']))
                ->description(__('All accounts and portals at the end of :date', ['date' => $date->format('d-m-Y')]))
                ->descriptionIcon(Heroicon::OutlinedScale)
                ->color(Money::toPaise($totals['total']) < 0 ? 'danger' : 'primary'),
            Stat::make(__('In bank accounts'), Money::format($totals['bank_accounts']))
                ->descriptionIcon(Heroicon::OutlinedBuildingLibrary)
                ->description(__('Closing balance'))
                ->color('info'),
            Stat::make(__('In portals'), Money::format($totals['portals']))
                ->descriptionIcon(Heroicon::OutlinedGlobeAlt)
                ->description(__('Closing balance'))
                ->color('info'),
            Stat::make(__('Entries on this day'), trans_choice(':count entry|:count entries', $totals['entries']))
                ->description(__('Received :received · Sent :sent', [
                    'received' => Money::format($totals['received']),
                    'sent' => Money::format($totals['sent']),
                ]))
                ->descriptionIcon(Heroicon::OutlinedArrowsRightLeft)
                ->color('gray'),
            Stat::make(__('Fees collected on this day'), Money::format($totals['fees_collected']))
                ->description(trans_choice(':count payment received|:count payments received', $totals['payments_count']))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make(__('Fees outstanding now'), Money::format($position['outstanding']))
                ->description(trans_choice(':count customer owes money|:count customers owe money', $position['customers_owing']))
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color(Money::toPaise($position['outstanding']) > 0 ? 'warning' : 'success'),
        ];
    }
}
