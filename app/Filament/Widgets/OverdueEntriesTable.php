<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Actions\WhatsAppReminderAction;
use App\Filament\Resources\Entries\EntryResource;
use App\Models\Entry;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Entries past their repayment date, oldest first.
 */
class OverdueEntriesTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Overdue entries'))
            ->description(__('Past the repayment due date and not fully paid.'))
            ->query(fn () => Entry::query()->overdue()->with(['customer', 'bank']))
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('id')
                    ->label(__('Entry #'))
                    ->formatStateUsing(fn (Entry $record): string => $record->number()),
                TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->description(fn (Entry $record): ?string => $record->customer?->mobile)
                    ->searchable(['name', 'mobile']),
                TextColumn::make('entry_date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label(__('Due date'))
                    ->date()
                    ->sortable()
                    ->color('danger')
                    ->description(fn (Entry $record): string => trans_choice(':count day overdue|:count days overdue', $record->daysOverdue())),
                TextColumn::make('total_due')
                    ->label(__('Total due'))
                    ->money()
                    ->alignEnd(),
                TextColumn::make('outstanding')
                    ->label(__('Outstanding'))
                    ->money()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('danger')
                    ->sortable(),
            ])
            ->recordActions([
                RecordPaymentAction::make()->iconButton(),
                WhatsAppReminderAction::make()->iconButton(),
                ViewAction::make()
                    ->iconButton()
                    ->url(fn (Entry $record): string => EntryResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading(__('Nothing overdue'))
            ->emptyStateDescription(__('Every entry is either paid or still within its due date.'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
