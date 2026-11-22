<?php

namespace App\Filament\Resources\Entries\Schemas;

use App\Enums\EntryStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Entry;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class EntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        // Net profit is admin-only; the amounts strip fits one row either way.
        $amountColumns = (auth()->user()?->isAdmin() ?? false) ? 7 : 6;

        return $schema
            ->components([
                Section::make(__('Amounts'))
                    ->icon(Heroicon::OutlinedCalculator)
                    ->schema([
                        TextEntry::make('bill_amount')
                            ->label(__('Bill amount'))
                            ->money(),
                        TextEntry::make('portal_deduction')
                            ->label(fn (Entry $record): string => __('Portal deduction (:percent)', ['percent' => Money::percent($record->portal_charge_percent)]))
                            ->money()
                            ->color('danger'),
                        TextEntry::make('service_charge')
                            ->label(fn (Entry $record): string => __('Service charge (:percent)', ['percent' => Money::percent($record->service_charge_percent)]))
                            ->money(),
                        TextEntry::make('total_due')
                            ->label(__('Total due from customer'))
                            ->money()
                            ->weight(FontWeight::Bold),
                        TextEntry::make('amount_received')
                            ->label(__('Amount received'))
                            ->money()
                            ->color('success'),
                        TextEntry::make('outstanding')
                            ->label(__('Outstanding'))
                            ->money()
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->color(fn (Entry $record): string => Money::toPaise($record->outstanding) > 0 ? 'danger' : 'success'),
                        TextEntry::make('net_profit')
                            ->label(__('Net profit'))
                            ->money()
                            ->weight(FontWeight::Bold)
                            ->color(fn (Entry $record): string => Money::toPaise($record->net_profit) < 0 ? 'danger' : 'success')
                            ->visible(fn (): bool => auth()->user()->isAdmin()),
                    ])
                    ->columns(['default' => 2, 'md' => 3, 'xl' => $amountColumns])
                    ->columnSpanFull(),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make(__('Entry'))
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                TextEntry::make('number')
                                    ->label(__('Entry #'))
                                    ->state(fn (Entry $record): string => $record->number())
                                    ->weight(FontWeight::Bold)
                                    ->copyable(),
                                TextEntry::make('entry_date')
                                    ->label(__('Date'))
                                    ->date(),
                                TextEntry::make('status')
                                    ->label(__('Status'))
                                    ->state(fn (Entry $record): EntryStatus => $record->displayStatus())
                                    ->badge(),
                                TextEntry::make('due_date')
                                    ->label(__('Repayment due date'))
                                    ->date()
                                    ->placeholder('—')
                                    ->helperText(fn (Entry $record): ?string => $record->isOverdue()
                                        ? trans_choice(':count day overdue|:count days overdue', $record->daysOverdue())
                                        : null)
                                    ->color(fn (Entry $record): ?string => $record->isOverdue() ? 'danger' : null),
                                TextEntry::make('paid_at')
                                    ->label(__('Fully paid on'))
                                    ->date()
                                    ->placeholder('—'),
                                TextEntry::make('reference')
                                    ->label(__('Portal transaction ID'))
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('creator.name')
                                    ->label(__('Created by'))
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label(__('Created at'))
                                    ->dateTime(),
                                TextEntry::make('notes')
                                    ->label(__('Notes'))
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->extraAttributes(['class' => 'h-full']),
                        Section::make(__('Customer & card'))
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                TextEntry::make('customer.name')
                                    ->label(__('Customer'))
                                    ->weight(FontWeight::Bold)
                                    ->url(fn (Entry $record): string => CustomerResource::getUrl('view', ['record' => $record->customer_id])),
                                TextEntry::make('customer.mobile')
                                    ->label(__('Mobile'))
                                    ->copyable()
                                    ->url(fn (Entry $record): string => 'tel:'.$record->customer?->mobile),
                                TextEntry::make('bank.name')
                                    ->label(__('Bank')),
                                TextEntry::make('card')
                                    ->label(__('Credit card'))
                                    ->state(fn (Entry $record): ?string => $record->card?->label())
                                    ->placeholder('—'),
                                TextEntry::make('portal.name')
                                    ->label(__('Portal'))
                                    ->badge()
                                    ->color('gray'),
                            ])
                            ->columns(2)
                            ->extraAttributes(['class' => 'h-full']),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
