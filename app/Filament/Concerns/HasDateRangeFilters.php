<?php

namespace App\Filament\Concerns;

use App\Support\DateRange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Period preset + from/until fields shared by dashboard-style pages.
 *
 * The date fields are always shown so the filter row keeps its shape: for a
 * preset they show its range as a placeholder, and picking a date switches
 * the period to "Custom range".
 */
trait HasDateRangeFilters
{
    /**
     * @return array<Component>
     */
    protected function dateRangeFilterComponents(string $defaultPreset = 'this_month'): array
    {
        $range = fn (Get $get): DateRange => DateRange::fromPreset($get('preset') ?? $defaultPreset, $get('from'), $get('until'));

        return [
            Select::make('preset')
                ->label(__('Period'))
                ->options(DateRange::presets())
                ->default($defaultPreset)
                ->selectablePlaceholder(false)
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    // A preset brings its own dates; clear typed ones so the placeholders show them.
                    if ($state !== 'custom') {
                        $set('from', null);
                        $set('until', null);
                    }
                }),
            DatePicker::make('from')
                ->label(__('From'))
                ->placeholder(fn (Get $get): string => $range($get)->from->format('d-m-Y'))
                ->maxDate(fn (Get $get) => $get('until') ?: today())
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('preset', 'custom')),
            DatePicker::make('until')
                ->label(__('Until'))
                ->placeholder(fn (Get $get): string => $range($get)->until->format('d-m-Y'))
                ->minDate(fn (Get $get) => $get('from'))
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('preset', 'custom')),
        ];
    }

    public function getDateRange(): DateRange
    {
        return DateRange::fromFilters($this->filters ?? null, $this->defaultPreset ?? 'this_month');
    }
}
