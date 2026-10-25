<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentMode;
use App\Filament\Actions\ExportTableAction;
use App\Filament\Actions\PrintReceiptAction;
use App\Filament\Resources\Entries\EntryResource;
use App\Filament\Resources\Entries\Tables\EntriesTable;
use App\Models\Payment;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['entry.customer', 'receiver']))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('paid_on')->orderByDesc('id'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('Receipt #'))
                    ->formatStateUsing(fn (Payment $record): string => $record->number())
                    ->sortable(),
                TextColumn::make('paid_on')
                    ->label(__('Payment date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('entry.customer.name')
                    ->label(__('Customer'))
                    ->description(fn (Payment $record): ?string => $record->entry?->customer?->mobile)
                    ->searchable(['name', 'mobile']),
                TextColumn::make('entry_id')
                    ->label(__('Entry #'))
                    ->formatStateUsing(fn (Payment $record): string => $record->entry->number())
                    ->url(fn (Payment $record): string => EntryResource::getUrl('view', ['record' => $record->entry_id]))
                    ->color('primary'),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('mode')
                    ->label(__('Mode'))
                    ->badge(),
                TextColumn::make('reference')
                    ->label(__('Reference'))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('receiver.name')
                    ->label(__('Received by'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('notes')
                    ->label(__('Notes'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                EntriesTable::dateRangeFilter('paid_on'),
                SelectFilter::make('mode')
                    ->label(__('Mode'))
                    ->options(PaymentMode::class)
                    ->multiple(),
                SelectFilter::make('received_by')
                    ->label(__('Received by'))
                    ->relationship('receiver', 'name'),
            ])
            ->filtersFormColumns(['md' => 2, 'xl' => 4])
            ->filtersFormWidth(Width::FourExtraLarge)
            ->recordActions([
                PrintReceiptAction::make()->iconButton(),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            // Export sits in the toolbar with search, filters and columns, so the table has one header row.
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
                ExportTableAction::make('payments', fn (): array => self::exportColumns()),
            ])
            ->striped()
            ->paginated([25, 50, 100, 'all']);
    }

    /**
     * @return array<string, \Closure(Payment): mixed>
     */
    public static function exportColumns(): array
    {
        return [
            __('Receipt #') => fn (Payment $p) => $p->number(),
            __('Payment date') => fn (Payment $p) => $p->paid_on,
            __('Entry #') => fn (Payment $p) => $p->entry?->number(),
            __('Customer') => fn (Payment $p) => $p->entry?->customer?->name,
            __('Mobile') => fn (Payment $p) => $p->entry?->customer?->mobile,
            __('Amount') => fn (Payment $p) => (float) $p->amount,
            __('Mode') => fn (Payment $p) => $p->mode,
            __('Reference') => fn (Payment $p) => $p->reference,
            __('Received by') => fn (Payment $p) => $p->receiver?->name,
            __('Notes') => fn (Payment $p) => $p->notes,
        ];
    }
}
