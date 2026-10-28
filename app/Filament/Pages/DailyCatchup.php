<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Widgets\DailyCatchupOverview;
use App\Filament\Widgets\DailyCatchupTable;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

/**
 * Daily catch-up: every bank account and portal with its opening balance,
 * the day's money in and out, and the latest (closing) balance.
 */
class DailyCatchup extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'daily-catchup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Portals;

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Daily catch-up');
    }

    public function getTitle(): string
    {
        return __('Daily catch-up');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    DatePicker::make('date')
                        ->label(__('Date'))
                        ->default(today()->toDateString())
                        ->maxDate(today())
                        ->required()
                        ->helperText(__('Balances are as at the end of this day. Today shows the latest balance.')),
                ])
                ->columns(['md' => 2, 'xl' => 4])
                ->columnSpanFull(),
        ]);
    }

    /**
     * The chosen day from the page filters; today when blank, invalid or in the future.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function dateFrom(?array $filters): CarbonImmutable
    {
        try {
            $date = filled($filters['date'] ?? null) ? CarbonImmutable::parse($filters['date'])->startOfDay() : null;
        } catch (Throwable) {
            $date = null;
        }

        return $date === null || $date->isFuture() ? CarbonImmutable::today() : $date;
    }

    public function getWidgets(): array
    {
        return [
            DailyCatchupOverview::class,
            DailyCatchupTable::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
