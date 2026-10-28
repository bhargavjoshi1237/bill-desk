<?php

namespace App\Filament\Widgets;

use App\Models\Portal;
use App\Support\GridColumns;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Current wallet balance of each active portal (admins only).
 */
class WalletBalancesOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getHeading(): ?string
    {
        return __('Portal wallet balances');
    }

    protected function getColumns(): int
    {
        return GridColumns::balanced(count($this->getCachedStats()));
    }

    protected function getStats(): array
    {
        return Portal::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Portal $portal): Stat => Stat::make($portal->name, Money::format($portal->walletBalance()))
                ->description(match (true) {
                    $portal->isLowOnBalance() => __('Low balance: please top up'),
                    $portal->isBankAccount() => __('Bank account'),
                    default => __('Default charge :percent', ['percent' => Money::percent($portal->charge_percent)]),
                })
                ->descriptionIcon($portal->isLowOnBalance() ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedWallet)
                ->color($portal->isLowOnBalance() ? 'danger' : 'success'))
            ->all();
    }
}
