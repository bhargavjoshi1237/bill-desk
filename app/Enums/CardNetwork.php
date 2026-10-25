<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CardNetwork: string implements HasLabel
{
    case Visa = 'visa';
    case Mastercard = 'mastercard';
    case RuPay = 'rupay';
    case Amex = 'amex';
    case Diners = 'diners';

    public function getLabel(): string
    {
        return match ($this) {
            self::Visa => 'Visa',
            self::Mastercard => 'Mastercard',
            self::RuPay => 'RuPay',
            self::Amex => 'American Express',
            self::Diners => 'Diners Club',
        };
    }
}
