<?php

namespace App\Filament\Resources\WalletTransactions;

use App\Enums\NavigationGroup;
use App\Enums\WalletTransactionType;
use App\Filament\Actions\ExportTableAction;
use App\Filament\Resources\Entries\Tables\EntriesTable;
use App\Filament\Resources\WalletTransactions\Pages\ManageWalletTransactions;
use App\Filament\Resources\WalletTransactions\Schemas\WalletTransactionForm;
use App\Models\WalletTransaction;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Portals;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Wallet transaction');
    }

    public static function getNavigationLabel(): string
    {
        // Filament title-cases plural labels in the sidebar; keep sentence case like the rest of the menu.
        return __('Wallet top-ups');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Wallet top-ups');
    }

    public static function form(Schema $schema): Schema
    {
        return WalletTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['portal', 'creator']))
            ->defaultSort(fn ($query) => $query->orderByDesc('transaction_date')->orderByDesc('id'))
            ->columns([
                TextColumn::make('transaction_date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('portal.name')
                    ->label(__('Portal'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),
                TextColumn::make('reference')
                    ->label(__('Reference / UTR'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('creator.name')
                    ->label(__('Added by'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('notes')
                    ->label(__('Notes'))
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                EntriesTable::dateRangeFilter('transaction_date'),
                SelectFilter::make('portal_id')
                    ->label(__('Portal'))
                    ->relationship('portal', 'name'),
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(WalletTransactionType::class),
            ])
            // In the toolbar rather than the header, so the table has one header row.
            ->toolbarActions([
                ExportTableAction::make('wallet-transactions', fn (): array => [
                    __('Date') => fn (WalletTransaction $t) => $t->transaction_date,
                    __('Portal') => fn (WalletTransaction $t) => $t->portal?->name,
                    __('Type') => fn (WalletTransaction $t) => $t->type,
                    __('Amount') => fn (WalletTransaction $t) => (float) $t->amount,
                    __('Reference / UTR') => fn (WalletTransaction $t) => $t->reference,
                    __('Added by') => fn (WalletTransaction $t) => $t->creator?->name,
                    __('Notes') => fn (WalletTransaction $t) => $t->notes,
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWalletTransactions::route('/'),
        ];
    }
}
