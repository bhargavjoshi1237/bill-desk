<?php

namespace App\Filament\Resources\Portals\Tables;

use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PortalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('entries')->withSum('entries as entries_volume', 'bill_amount'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Portal'))
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('charge_percent')
                    ->label(__('Default charge'))
                    ->formatStateUsing(fn ($state): string => Money::percent($state))
                    ->alignEnd(),
                TextColumn::make('entries_count')
                    ->label(__('Entries'))
                    ->alignCenter(),
                TextColumn::make('entries_volume')
                    ->label(__('Total bill volume'))
                    ->money()
                    ->alignEnd()
                    ->placeholder(Money::format(0)),
                ToggleColumn::make('is_active')
                    ->label(__('Active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
