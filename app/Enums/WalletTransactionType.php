<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WalletTransactionType: string implements HasColor, HasLabel
{
    case TopUp = 'top_up';
    case Withdrawal = 'withdrawal';

    public function getLabel(): string
    {
        return match ($this) {
            self::TopUp => __('Top-up'),
            self::Withdrawal => __('Withdrawal / Adjustment'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TopUp => 'success',
            self::Withdrawal => 'danger',
        };
    }

    /**
     * +1 for money added to the wallet, -1 for money taken out.
     */
    public function sign(): int
    {
        return $this === self::TopUp ? 1 : -1;
    }
}
