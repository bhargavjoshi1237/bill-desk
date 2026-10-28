<?php

namespace App\Filament\Resources\Portals\Schemas;

use App\Models\Portal;
use App\Support\Money;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class PortalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Balance'))
                    ->icon(Heroicon::OutlinedWallet)
                    ->description(__('Balance = top-ups − withdrawals + bill amounts received − (bill + portal deduction) of entries sent.'))
                    ->schema([
                        TextEntry::make('wallet_top_ups')
                            ->label(__('Total top-ups'))
                            ->state(fn (Portal $record): string => Money::format($record->totalTopUps()))
                            ->color('success')
                            ->size(TextSize::Large),
                        TextEntry::make('wallet_withdrawals')
                            ->label(__('Withdrawals / adjustments'))
                            ->state(fn (Portal $record): string => Money::format($record->totalWithdrawals()))
                            ->size(TextSize::Large),
                        TextEntry::make('wallet_received')
                            ->label(__('Received for entries'))
                            ->state(fn (Portal $record): string => Money::format($record->totalReceived()))
                            ->color('success')
                            ->size(TextSize::Large),
                        TextEntry::make('wallet_used')
                            ->label(__('Sent for entries'))
                            ->state(fn (Portal $record): string => Money::format($record->totalUsed()))
                            ->color('danger')
                            ->size(TextSize::Large),
                        TextEntry::make('wallet_balance')
                            ->label(__('Current balance'))
                            ->state(fn (Portal $record): string => Money::format($record->walletBalance()))
                            ->color(fn (Portal $record): string => $record->isLowOnBalance() ? 'danger' : 'success')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            // Odd one out on two-column screens, so it takes the whole last row.
                            ->columnSpan(['default' => 2, 'xl' => 1]),
                    ])
                    ->columns(['default' => 2, 'xl' => 5])
                    ->columnSpanFull(),
                Section::make(__('Portal details'))
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Name')),
                        TextEntry::make('type')
                            ->label(__('Type'))
                            ->badge(),
                        TextEntry::make('charge_percent')
                            ->label(__('Default portal charge'))
                            ->formatStateUsing(fn ($state): string => Money::percent($state)),
                        TextEntry::make('low_balance_alert')
                            ->label(__('Low balance alert'))
                            ->money()
                            ->placeholder('—'),
                        IconEntry::make('is_active')
                            ->label(__('Active'))
                            ->boolean(),
                        TextEntry::make('entries_count')
                            ->label(__('Entries sent'))
                            ->state(fn (Portal $record): int => $record->entries()->count()),
                        TextEntry::make('received_entries_count')
                            ->label(__('Entries received'))
                            ->state(fn (Portal $record): int => $record->receivedEntries()->count()),
                        TextEntry::make('entries_volume')
                            ->label(__('Total bill volume'))
                            ->state(fn (Portal $record): string => Money::format($record->entries()->sum('bill_amount'))),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 2, 'lg' => 4])
                    ->columnSpanFull(),
            ]);
    }
}
