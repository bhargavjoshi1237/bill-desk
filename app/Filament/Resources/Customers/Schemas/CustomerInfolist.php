<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use App\Models\CustomerCard;
use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Account summary'))
                    ->icon(Heroicon::OutlinedChartBar)
                    ->schema([
                        TextEntry::make('summary_entries')
                            ->label(__('Entries'))
                            ->state(fn (Customer $record): int => $record->entries()->count())
                            ->size(TextSize::Large),
                        TextEntry::make('summary_billed')
                            ->label(__('Total billed'))
                            ->state(fn (Customer $record): string => Money::format($record->entries()->sum('bill_amount')))
                            ->size(TextSize::Large),
                        TextEntry::make('summary_received')
                            ->label(__('Total received'))
                            ->state(fn (Customer $record): string => Money::format($record->entries()->sum('amount_received')))
                            ->color('success')
                            ->size(TextSize::Large),
                        TextEntry::make('summary_outstanding')
                            ->label(__('Outstanding'))
                            ->state(fn (Customer $record): string => Money::format($record->totalOutstanding()))
                            ->color(fn (Customer $record): string => Money::toPaise($record->totalOutstanding()) > 0 ? 'danger' : 'success')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large),
                        TextEntry::make('summary_overdue')
                            ->label(__('Overdue'))
                            ->state(fn (Customer $record): string => Money::format($record->entries()->overdue()->sum('outstanding')))
                            ->color('danger')
                            ->size(TextSize::Large),
                        TextEntry::make('summary_profit')
                            ->label(__('Net profit earned'))
                            ->state(fn (Customer $record): string => Money::format($record->entries()->sum('net_profit')))
                            ->color('success')
                            ->size(TextSize::Large)
                            ->visible(fn (): bool => auth()->user()->isAdmin()),
                    ])
                    ->columns(['default' => 2, 'md' => 3, 'xl' => 6])
                    ->columnSpanFull(),
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Section::make(__('Customer details'))
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('Customer name'))
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('mobile')
                                    ->label(__('Mobile number'))
                                    ->copyable()
                                    ->url(fn (Customer $record): string => 'tel:'.$record->mobile),
                                TextEntry::make('city')
                                    ->label(__('City'))
                                    ->placeholder('—'),
                                TextEntry::make('address')
                                    ->label(__('Address'))
                                    ->placeholder('—'),
                                TextEntry::make('notes')
                                    ->label(__('Notes'))
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Section::make(__('Credit cards'))
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->schema([
                                RepeatableEntry::make('cards')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('label')
                                            ->hiddenLabel()
                                            ->state(fn (CustomerCard $record): string => $record->label()),
                                    ])
                                    ->placeholder(__('No cards saved')),
                            ]),
                        Section::make(__('ID proof'))
                            ->icon(Heroicon::OutlinedIdentification)
                            ->schema([
                                TextEntry::make('aadhaar_number')
                                    ->label(__('Aadhaar number'))
                                    ->formatStateUsing(fn (Customer $record): ?string => $record->maskedAadhaar())
                                    ->placeholder('—'),
                                TextEntry::make('pan_number')
                                    ->label(__('PAN number'))
                                    ->placeholder('—'),
                                TextEntry::make('id_proof_files')
                                    ->label(__('ID proof documents'))
                                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString(sprintf(
                                        '<a href="%s" target="_blank" rel="noopener" style="text-decoration: underline">%s</a>',
                                        e(Storage::disk('local')->temporaryUrl($state, now()->addMinutes(30))),
                                        e(__('View :file', ['file' => strtoupper(pathinfo($state, PATHINFO_EXTENSION))])),
                                    )))
                                    ->listWithLineBreaks()
                                    ->color('primary')
                                    ->hidden(fn (Customer $record): bool => blank($record->id_proof_files)),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
