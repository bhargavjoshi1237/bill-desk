<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Actions\CustomerStatementAction;
use App\Filament\Actions\ExportTableAction;
use App\Filament\Actions\WhatsAppReminderAction;
use App\Models\Customer;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['entries', 'cards'])
                ->withSum('entries as total_billed', 'bill_amount')
                ->withSum('entries as outstanding_total', 'outstanding')
                ->withMax('entries as last_entry_date', 'entry_date'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('mobile')
                    ->label(__('Mobile'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('city')
                    ->label(__('City'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('cards_count')
                    ->label(__('Cards'))
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('entries_count')
                    ->label(__('Entries'))
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('total_billed')
                    ->label(__('Total billed'))
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->placeholder(Money::format(0)),
                TextColumn::make('outstanding_total')
                    ->label(__('Outstanding'))
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->placeholder(Money::format(0))
                    ->color(fn (Customer $record): ?string => Money::toPaise($record->outstanding_total) > 0 ? 'danger' : null),
                TextColumn::make('last_entry_date')
                    ->label(__('Last entry'))
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                Filter::make('owes_money')
                    ->label(__('Has outstanding'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('entries', fn (Builder $query) => $query->open())),
                Filter::make('overdue')
                    ->label(__('Has overdue entries'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('entries', fn (Builder $query) => $query->overdue())),
                SelectFilter::make('city')
                    ->label(__('City'))
                    ->options(fn (): array => Customer::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city', 'city')->all())
                    ->searchable(),
            ])
            ->headerActions([
                ExportTableAction::make('customers', fn (): array => [
                    __('Customer') => fn (Customer $c) => $c->name,
                    __('Mobile') => fn (Customer $c) => $c->mobile,
                    __('City') => fn (Customer $c) => $c->city,
                    __('Address') => fn (Customer $c) => $c->address,
                    __('Cards') => fn (Customer $c) => (int) $c->cards_count,
                    __('Entries') => fn (Customer $c) => (int) $c->entries_count,
                    __('Total billed') => fn (Customer $c) => (float) $c->total_billed,
                    __('Outstanding') => fn (Customer $c) => (float) $c->outstanding_total,
                    __('Last entry') => fn (Customer $c) => $c->last_entry_date ? Carbon::parse($c->last_entry_date) : null,
                ]),
            ])
            ->recordActions([
                WhatsAppReminderAction::make()->iconButton(),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    CustomerStatementAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([25, 50, 100, 'all']);
    }
}
