<?php

namespace App\Filament\Resources\Portals\Schemas;

use App\Models\Portal;
use App\Support\Money;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PortalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Portal details'))
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Portal name')),
                        TextEntry::make('charge_percent')
                            ->label(__('Default portal charge'))
                            ->formatStateUsing(fn ($state): string => Money::percent($state)),
                        IconEntry::make('is_active')
                            ->label(__('Active'))
                            ->boolean(),
                        TextEntry::make('entries_count')
                            ->label(__('Entries'))
                            ->state(fn (Portal $record): int => $record->entries()->count()),
                        TextEntry::make('entries_volume')
                            ->label(__('Total bill volume'))
                            ->state(fn (Portal $record): string => Money::format($record->entries()->sum('bill_amount'))),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    // Five details in one row on desktop, notes underneath.
                    ->columns(['default' => 2, 'lg' => 5])
                    ->columnSpanFull(),
            ]);
    }
}
