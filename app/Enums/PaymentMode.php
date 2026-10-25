<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMode: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Upi => __('UPI'),
            self::BankTransfer => __('Bank Transfer'),
            self::Cheque => __('Cheque'),
            self::Other => __('Other'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Upi => 'info',
            self::BankTransfer => 'primary',
            self::Cheque => 'warning',
            self::Other => 'gray',
        };
    }
}
