<?php

namespace App\Filament\Concerns;

use App\Support\DateRange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Period preset + custom from/until fields shared by dashboard-style pages.
 */
trait HasDateRangeFilters
{
    /**
     * @return array<Component>
     */
    protected function dateRangeFilterComponents(string $defaultPreset = 'this_month'): array
    {
        return [
            Select::make('preset')
                ->label(__('Period'))
                ->options(DateRange::presets())
                ->default($defaultPreset)
                ->selectablePlaceholder(false)
                ->native(false),
            DatePicker::make('from')
                ->label(__('From'))
                ->maxDate(fn (Get $get) => $get('until') ?: today())
                ->visible(fn (Get $get): bool => $get('preset') === 'custom'),
            DatePicker::make('until')
                ->label(__('Until'))
                ->minDate(fn (Get $get) => $get('from'))
                ->visible(fn (Get $get): bool => $get('preset') === 'custom'),
        ];
    }

    public function getDateRange(): DateRange
    {
        return DateRange::fromFilters($this->filters ?? null, $this->defaultPreset ?? 'this_month');
    }
}
