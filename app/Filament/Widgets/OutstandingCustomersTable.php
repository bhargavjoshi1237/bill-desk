<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\CustomerStatementAction;
use App\Filament\Actions\WhatsAppReminderAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Reports\ReportService;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Every customer who still owes money, oldest unpaid entry first.
 */
class OutstandingCustomersTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Customer dues'))
            ->description(__('Age is counted from the oldest unpaid entry.'))
            ->query(fn () => app(ReportService::class)->outstandingCustomersQuery())
            ->defaultSort('oldest_open_entry_date')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Customer'))
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('mobile')
                    ->label(__('Mobile'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('open_entries_count')
                    ->label(__('Open entries'))
                    ->alignCenter(),
                TextColumn::make('outstanding_total')
                    ->label(__('Outstanding'))
                    ->money()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('danger')
                    ->sortable(),
                TextColumn::make('overdue_total')
                    ->label(__('Overdue'))
                    ->money()
                    ->alignEnd()
                    ->placeholder(Money::format(0))
                    ->sortable(),
                TextColumn::make('oldest_open_entry_date')
                    ->label(__('Oldest unpaid entry'))
                    ->date()
                    ->sortable(),
                TextColumn::make('age')
                    ->label(__('Age'))
                    ->state(fn (Customer $record): int => self::age($record))
                    ->formatStateUsing(fn (int $state): string => trans_choice(':count day|:count days', $state))
                    ->badge()
                    ->color(fn (Customer $record): string => match (ReportService::agingBucket(self::age($record))) {
                        '0_7' => 'success',
                        '8_30' => 'warning',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('aging')
                    ->label(__('Age'))
                    ->options([
                        '0_7' => __('0–7 days'),
                        '8_30' => __('8–30 days'),
                        '31_plus' => __('30+ days'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $today = today();

                        return match ($data['value'] ?? null) {
                            '0_7' => $query->whereHas('entries', fn (Builder $q) => $q->open()->whereDate('entry_date', '>=', $today->copy()->subDays(7)))
                                ->whereDoesntHave('entries', fn (Builder $q) => $q->open()->whereDate('entry_date', '<', $today->copy()->subDays(7))),
                            '8_30' => $query->whereHas('entries', fn (Builder $q) => $q->open()->whereDate('entry_date', '<', $today->copy()->subDays(7)))
                                ->whereDoesntHave('entries', fn (Builder $q) => $q->open()->whereDate('entry_date', '<', $today->copy()->subDays(30))),
                            '31_plus' => $query->whereHas('entries', fn (Builder $q) => $q->open()->whereDate('entry_date', '<', $today->copy()->subDays(30))),
                            default => $query,
                        };
                    }),
                Filter::make('overdue')
                    ->label(__('Only customers with overdue entries'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('entries', fn (Builder $q) => $q->overdue())),
            ])
            ->recordActions([
                WhatsAppReminderAction::make()->iconButton(),
                CustomerStatementAction::make()->iconButton(),
                ViewAction::make()
                    ->iconButton()
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading(__('No dues'))
            ->emptyStateDescription(__('No customer owes money right now.'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50, 'all']);
    }

    public static function age(Customer $record): int
    {
        return $record->oldest_open_entry_date
            ? (int) Carbon::parse($record->oldest_open_entry_date)->startOfDay()->diffInDays(today())
            : 0;
    }
}
