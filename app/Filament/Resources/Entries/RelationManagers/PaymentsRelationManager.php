<?php

namespace App\Filament\Resources\Entries\RelationManagers;

use App\Enums\PaymentMode;
use App\Filament\Actions\PrintReceiptAction;
use App\Filament\Resources\Payments\Schemas\PaymentForm;
use App\Models\Entry;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Payments received');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(PaymentForm::fields(fn (RelationManager $livewire): Entry => $livewire->getOwnerRecord()->refresh()))
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        // Tell the entry page to reload its totals after a payment changes.
        $refreshEntry = fn (RelationManager $livewire) => $livewire->dispatch('entry-payments-changed');

        return $table
            ->recordTitle(fn (Model $record): string => $record->number())
            ->defaultSort('paid_on', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label(__('Receipt #'))
                    ->formatStateUsing(fn (Model $record): string => $record->number()),
                TextColumn::make('paid_on')
                    ->label(__('Payment date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money()
                    ->alignEnd()
                    ->summarize(Sum::make()->money()->label(__('Total received'))),
                TextColumn::make('mode')
                    ->label(__('Mode'))
                    ->badge(),
                TextColumn::make('reference')
                    ->label(__('Reference'))
                    ->placeholder('—'),
                TextColumn::make('receiver.name')
                    ->label(__('Received by'))
                    ->placeholder('—'),
                TextColumn::make('notes')
                    ->label(__('Notes'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Record payment'))
                    ->visible(fn (RelationManager $livewire): bool => Money::toPaise($livewire->getOwnerRecord()->refresh()->outstanding) > 0)
                    ->fillForm(fn (RelationManager $livewire): array => [
                        'paid_on' => today()->toDateString(),
                        'amount' => $livewire->getOwnerRecord()->outstanding,
                        'mode' => PaymentMode::Cash->value,
                    ])
                    ->after($refreshEntry),
            ])
            ->recordActions([
                PrintReceiptAction::make()->iconButton(),
                EditAction::make()->after($refreshEntry),
                DeleteAction::make()->after($refreshEntry),
            ])
            ->emptyStateHeading(__('No payments yet'))
            ->emptyStateDescription(__('Payments received from the customer for this entry will appear here.'));
    }
}
