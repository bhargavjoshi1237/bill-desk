<?php

namespace App\Filament\Resources\Portals\RelationManagers;

use App\Enums\WalletTransactionType;
use App\Filament\Resources\WalletTransactions\Schemas\WalletTransactionForm;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Wallet top-ups & adjustments');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(WalletTransactionForm::fields())->columns(2);
    }

    public function table(Table $table): Table
    {
        $refresh = fn (RelationManager $livewire) => $livewire->dispatch('wallet-transactions-changed');

        return $table
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('transaction_date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money()
                    ->alignEnd(),
                TextColumn::make('reference')
                    ->label(__('Reference / UTR'))
                    ->placeholder('—'),
                TextColumn::make('creator.name')
                    ->label(__('Added by'))
                    ->placeholder('—'),
                TextColumn::make('notes')
                    ->label(__('Notes'))
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(WalletTransactionType::class),
            ])
            ->headerActions([
                CreateAction::make()->label(__('Add transaction'))->after($refresh),
            ])
            ->recordActions([
                EditAction::make()->after($refresh),
                DeleteAction::make()->after($refresh),
            ]);
    }
}
