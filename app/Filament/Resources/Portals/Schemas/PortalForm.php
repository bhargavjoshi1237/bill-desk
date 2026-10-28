<?php

namespace App\Filament\Resources\Portals\Schemas;

use App\Enums\PortalType;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PortalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Portal details'))
                    ->schema([
                        ToggleButtons::make('type')
                            ->label(__('Type'))
                            ->options(PortalType::class)
                            ->default(PortalType::Portal->value)
                            ->required()
                            ->inline()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                // Bank accounts usually don't charge for paying a bill.
                                if (self::isBankAccount($get) && blank($get('charge_percent'))) {
                                    $set('charge_percent', 0);
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('name')
                            ->label(fn (Get $get): string => self::isBankAccount($get) ? __('Account name') : __('Portal name'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('charge_percent')
                            ->label(__('Default portal charge'))
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.001)
                            ->required()
                            ->helperText(__('Pre-filled on new entries; can be changed per entry. Existing entries keep their own rate.')),
                        TextInput::make('low_balance_alert')
                            ->label(__('Low balance alert'))
                            ->numeric()
                            ->prefix('₹')
                            ->minValue(0)
                            ->helperText(__('Warn on the dashboard when the wallet drops below this amount.')),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true)
                            ->inline(false),
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    private static function isBankAccount(Get $get): bool
    {
        $type = $get('type');

        return ($type instanceof PortalType ? $type : PortalType::tryFrom((string) $type)) === PortalType::BankAccount;
    }
}
