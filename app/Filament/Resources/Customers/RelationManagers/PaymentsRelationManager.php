<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Actions\PrintReceiptAction;
use App\Filament\Resources\Entries\EntryResource;
use App\Models\Payment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Payments received');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['entry', 'receiver']))
            ->defaultSort('paid_on', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label(__('Receipt #'))
                    ->formatStateUsing(fn (Payment $record): string => $record->number()),
                TextColumn::make('paid_on')
                    ->label(__('Payment date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('entry_id')
                    ->label(__('Entry #'))
                    ->formatStateUsing(fn (Payment $record): string => $record->entry->number())
                    ->url(fn (Payment $record): string => EntryResource::getUrl('view', ['record' => $record->entry_id]))
                    ->color('primary'),
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
            ])
            ->recordActions([
                PrintReceiptAction::make()->iconButton(),
            ]);
    }
}
