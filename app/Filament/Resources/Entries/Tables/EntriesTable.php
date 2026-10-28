<?php

namespace App\Filament\Resources\Entries\Tables;

use App\Enums\EntryStatus;
use App\Filament\Actions\ExportTableAction;
use App\Filament\Actions\PrintReceiptAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Actions\WhatsAppReminderAction;
use App\Models\Entry;
use App\Models\Portal;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class EntriesTable
{
    public static function configure(Table $table): Table
    {
        $isAdmin = fn (): bool => auth()->user()->isAdmin();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'bank', 'portal', 'receivedPortal', 'card']))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('entry_date')->orderByDesc('id'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('Entry #'))
                    ->formatStateUsing(fn (Entry $record): string => $record->number())
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereKey((int) preg_replace('/\D/', '', $search)))
                    ->sortable(),
                TextColumn::make('entry_date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->description(fn (Entry $record): ?string => $record->customer?->mobile)
                    ->searchable(['name', 'mobile'])
                    ->sortable(),
                TextColumn::make('bank.name')
                    ->label(__('Bank / portal'))
                    ->description(fn (Entry $record): string => collect([
                        $record->card?->last_four ? '•••• '.$record->card->last_four : null,
                        // Sent by → received in.
                        collect([$record->portal?->name, $record->receivedPortal?->name])->filter()->implode(' → '),
                    ])->filter()->implode(' · '))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('bill_amount')
                    ->label(__('Bill amount'))
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('portal_charge_percent')
                    ->label(__('Portal %'))
                    ->formatStateUsing(fn ($state): string => Money::percent($state))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('portal_deduction')
                    ->label(__('Portal deduction'))
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('service_charge')
                    ->label(__('Service charge'))
                    ->money()
                    ->description(fn (Entry $record): string => Money::percent($record->service_charge_percent))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable()
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('total_due')
                    ->label(__('Total due'))
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('amount_received')
                    ->label(__('Received'))
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->color('success')
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('outstanding')
                    ->label(__('Outstanding'))
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (Entry $record): ?string => Money::toPaise($record->outstanding) > 0 ? 'danger' : null)
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('net_profit')
                    ->label(__('Net profit'))
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->color(fn (Entry $record): string => Money::toPaise($record->net_profit) < 0 ? 'danger' : 'success')
                    ->visible($isAdmin)
                    ->summarize(Sum::make()->money()->label(__('Total'))),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->state(fn (Entry $record): EntryStatus => $record->displayStatus())
                    ->badge()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label(__('Due date'))
                    ->date()
                    ->sortable()
                    ->description(fn (Entry $record): ?string => $record->isOverdue()
                        ? trans_choice(':count day overdue|:count days overdue', $record->daysOverdue())
                        : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label(__('Created by'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                self::dateRangeFilter(),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(EntryStatus::class)
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        EntryStatus::Overdue->value => $query->overdue(),
                        null, '' => $query,
                        default => $query->where('status', $data['value']),
                    }),
                // Matches entries that were sent by or received in the chosen portals.
                SelectFilter::make('portal_id')
                    ->label(__('Portal / account'))
                    ->options(fn (): array => Portal::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->multiple()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['values'] ?? null,
                        fn (Builder $query, array $ids) => $query->where(fn (Builder $query) => $query
                            ->whereIn('portal_id', $ids)
                            ->orWhereIn('received_portal_id', $ids)),
                    )),
                SelectFilter::make('bank_id')
                    ->label(__('Bank'))
                    ->relationship('bank', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                SelectFilter::make('customer_id')
                    ->label(__('Customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->columnSpan(['xl' => 2]),
                SelectFilter::make('created_by')
                    ->label(__('Created by'))
                    ->relationship('creator', 'name')
                    ->columnSpan(['md' => 2, 'xl' => 1]),
            ])
            // Two even rows on desktop: dates, status, portal | bank, customer, created by.
            ->filtersFormColumns(['md' => 2, 'xl' => 4])
            ->filtersFormWidth(Width::FourExtraLarge)
            ->recordActions([
                RecordPaymentAction::make()->iconButton(),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    WhatsAppReminderAction::make(),
                    PrintReceiptAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            // Export sits in the toolbar with search, filters and columns, so the table has one header row.
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
                ExportTableAction::make('entries', fn (): array => self::exportColumns()),
            ])
            ->striped()
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(25);
    }

    public static function dateRangeFilter(string $column = 'entry_date'): Filter
    {
        return Filter::make('date_range')
            ->schema([
                DatePicker::make('from')->label(__('From date')),
                DatePicker::make('until')->label(__('Until date')),
            ])
            ->columns(2)
            ->columnSpan(2)
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate($column, '<=', $date)))
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if ($data['from'] ?? null) {
                    $indicators[] = Indicator::make(__('From :date', ['date' => Carbon::parse($data['from'])->format('d-m-Y')]))->removeField('from');
                }

                if ($data['until'] ?? null) {
                    $indicators[] = Indicator::make(__('Until :date', ['date' => Carbon::parse($data['until'])->format('d-m-Y')]))->removeField('until');
                }

                return $indicators;
            });
    }

    /**
     * @return array<string, \Closure(Entry): mixed>
     */
    public static function exportColumns(): array
    {
        $columns = [
            __('Entry #') => fn (Entry $e) => $e->number(),
            __('Date') => fn (Entry $e) => $e->entry_date,
            __('Customer') => fn (Entry $e) => $e->customer?->name,
            __('Mobile') => fn (Entry $e) => $e->customer?->mobile,
            __('Bank') => fn (Entry $e) => $e->bank?->name,
            __('Card') => fn (Entry $e) => $e->card?->last_four ? '•••• '.$e->card->last_four : null,
            __('Sent by') => fn (Entry $e) => $e->portal?->name,
            __('Received in') => fn (Entry $e) => $e->receivedPortal?->name,
            __('Bill amount') => fn (Entry $e) => (float) $e->bill_amount,
            __('Portal %') => fn (Entry $e) => (float) $e->portal_charge_percent,
            __('Portal deduction') => fn (Entry $e) => (float) $e->portal_deduction,
            __('Service %') => fn (Entry $e) => (float) $e->service_charge_percent,
            __('Service charge') => fn (Entry $e) => (float) $e->service_charge,
            __('Total due') => fn (Entry $e) => (float) $e->total_due,
            __('Received') => fn (Entry $e) => (float) $e->amount_received,
            __('Outstanding') => fn (Entry $e) => (float) $e->outstanding,
            __('Net profit') => fn (Entry $e) => (float) $e->net_profit,
            __('Status') => fn (Entry $e) => $e->displayStatus(),
            __('Due date') => fn (Entry $e) => $e->due_date,
            __('Paid on') => fn (Entry $e) => $e->paid_at,
            __('Portal transaction ID') => fn (Entry $e) => $e->reference,
            __('Notes') => fn (Entry $e) => $e->notes,
        ];

        if (! auth()->user()->isAdmin()) {
            unset($columns[__('Net profit')]);
        }

        return $columns;
    }
}
