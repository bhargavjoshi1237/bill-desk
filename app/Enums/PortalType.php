<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Where money sits: a payment portal wallet or one of our bank accounts.
 * Both can send a bill payment or receive the customer's money for an entry.
 */
enum PortalType: string implements HasColor, HasIcon, HasLabel
{
    case Portal = 'portal';
    case BankAccount = 'bank_account';

    public function getLabel(): string
    {
        return match ($this) {
            self::Portal => __('Portal'),
            self::BankAccount => __('Bank account'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Portal => 'info',
            self::BankAccount => 'primary',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Portal => Heroicon::OutlinedGlobeAlt,
            self::BankAccount => Heroicon::OutlinedBuildingLibrary,
        };
    }
}
