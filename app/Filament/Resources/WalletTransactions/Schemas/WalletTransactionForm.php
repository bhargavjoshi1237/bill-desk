<?php

namespace App\Filament\Resources\WalletTransactions\Schemas;

use App\Enums\WalletTransactionType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class WalletTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('portal_id')
                    ->label(__('Portal'))
                    ->relationship('portal', 'name')
                    ->preload()
                    ->required(),
                ...self::fields(),
            ])
            ->columns(2);
    }

    /**
     * @return array<Component>
     */
    public static function fields(): array
    {
        return [
            DatePicker::make('transaction_date')
                ->label(__('Date'))
                ->default(today())
                ->maxDate(today())
                ->required(),
            ToggleButtons::make('type')
                ->label(__('Type'))
                ->options(WalletTransactionType::class)
                ->default(WalletTransactionType::TopUp)
                ->inline()
                ->required(),
            TextInput::make('amount')
                ->label(__('Amount'))
                ->numeric()
                ->prefix('₹')
                ->minValue(0.01)
                ->step(0.01)
                ->required(),
            TextInput::make('reference')
                ->label(__('Reference / UTR'))
                ->maxLength(255),
            Textarea::make('notes')
                ->label(__('Notes'))
                ->rows(2)
                ->columnSpanFull(),
        ];
    }
}
