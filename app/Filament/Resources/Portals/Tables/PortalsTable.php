<?php

namespace App\Filament\Resources\Portals\Tables;

use App\Enums\PortalType;
use App\Filament\Resources\WalletTransactions\Schemas\WalletTransactionForm;
use App\Models\Portal;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->label(__('Name'))
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('charge_percent')
                    ->label(__('Default charge'))
                    ->formatStateUsing(fn ($state): string => Money::percent($state))
                    ->alignEnd(),
                TextColumn::make('wallet_balance')
                    ->label(__('Balance'))
                    ->state(fn (Portal $record): string => $record->walletBalance())
                    ->money()
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (Portal $record): string => $record->isLowOnBalance() ? 'danger' : 'success')
                    ->icon(fn (Portal $record): ?Heroicon => $record->isLowOnBalance() ? Heroicon::OutlinedExclamationTriangle : null),
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
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(PortalType::class),
            ])
            ->recordActions([
                self::topUpAction(),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function topUpAction(): Action
    {
        return Action::make('topUp')
            ->label(__('Wallet top-up'))
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('success')
            ->modalHeading(fn (Portal $record): string => __('Wallet transaction: :portal', ['portal' => $record->name]))
            ->modalDescription(fn (Portal $record): string => __('Current balance: :amount', ['amount' => Money::format($record->walletBalance())]))
            ->schema(WalletTransactionForm::fields())
            ->action(function (Portal $record, array $data): void {
                $record->walletTransactions()->create($data);

                Notification::make()
                    ->success()
                    ->title(__('Wallet updated'))
                    ->body(__('New balance: :amount', ['amount' => Money::format($record->walletBalance())]))
                    ->send();
            });
    }
}
