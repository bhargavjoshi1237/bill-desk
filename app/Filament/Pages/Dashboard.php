<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasDateRangeFilters;
use App\Filament\Resources\Entries\EntryResource;
use App\Filament\Widgets\CollectionsTrendChart;
use App\Filament\Widgets\OverdueEntriesTable;
use App\Filament\Widgets\PeriodStatsOverview;
use App\Filament\Widgets\ProfitTrendChart;
use App\Filament\Widgets\VolumeByDimensionChart;
use App\Filament\Widgets\WalletBalancesOverview;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    use HasDateRangeFilters;
    use HasFiltersForm;

    public function getTitle(): string
    {
        return __('Dashboard');
    }

    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newEntry')
                ->label(__('New entry'))
                ->icon(Heroicon::OutlinedPlus)
                ->url(EntryResource::getUrl('create'))
                ->keyBindings(['mod+e']),
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema($this->dateRangeFilterComponents())
                ->columns(3)
                ->columnSpanFull(),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            PeriodStatsOverview::class,
            // Operators don't get the profit chart, so the collections chart takes the whole row.
            CollectionsTrendChart::make(['span' => auth()->user()->isAdmin() ? null : 'full']),
            ProfitTrendChart::class,
            VolumeByDimensionChart::make(['dimension' => 'portal']),
            VolumeByDimensionChart::make(['dimension' => 'bank']),
            WalletBalancesOverview::class,
            OverdueEntriesTable::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 6];
    }
}
