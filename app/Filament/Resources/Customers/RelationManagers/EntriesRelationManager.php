<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\EntryStatus;
use App\Filament\Actions\PrintReceiptAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Resources\Entries\EntryResource;
use App\Models\Entry;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Entries');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->entries()->count();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['bank', 'portal', 'card', 'customer']))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('entry_date')->orderByDesc('id'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('Entry #'))
                    ->formatStateUsing(fn (Entry $record): string => $record->number()),
                TextColumn::make('entry_date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('bank.name')
                    ->label(__('Bank'))
                    ->description(fn (Entry $record): ?string => $record->card?->last_four ? '•••• '.$record->card->last_four : null),
                TextColumn::make('portal.name')
                    ->label(__('Portal'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('bill_amount')
                    ->label(__('Bill amount'))
                    ->money()
                    ->alignEnd()
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('total_due')
                    ->label(__('Total due'))
                    ->money()
                    ->alignEnd()
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('amount_received')
                    ->label(__('Received'))
                    ->money()
                    ->alignEnd()
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('outstanding')
                    ->label(__('Outstanding'))
                    ->money()
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (Entry $record): ?string => Money::toPaise($record->outstanding) > 0 ? 'danger' : null)
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('net_profit')
                    ->label(__('Net profit'))
                    ->money()
                    ->alignEnd()
                    ->visible(fn (): bool => auth()->user()->isAdmin())
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->state(fn (Entry $record): EntryStatus => $record->displayStatus())
                    ->badge(),
                TextColumn::make('due_date')
                    ->label(__('Due date'))
                    ->date(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(EntryStatus::class)
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        EntryStatus::Overdue->value => $query->overdue(),
                        null, '' => $query,
                        default => $query->where('status', $data['value']),
                    }),
            ])
            ->headerActions([
                Action::make('newEntry')
                    ->label(__('New entry'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn (RelationManager $livewire): string => EntryResource::getUrl('create', ['customer' => $livewire->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                RecordPaymentAction::make()->iconButton(),
                PrintReceiptAction::make()->iconButton(),
                ViewAction::make()
                    ->url(fn (Entry $record): string => EntryResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Entry $record): string => EntryResource::getUrl('view', ['record' => $record]));
    }
}
