<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Payment status of an entry, kept in sync by Entry::recalculate().
 */
enum EntryStatus: string implements HasColor, HasIcon, HasLabel
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::Partial => __('Partial'),
            self::Paid => __('Paid'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Partial => 'info',
            self::Paid => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Unpaid => Heroicon::OutlinedClock,
            self::Partial => Heroicon::OutlinedAdjustmentsHorizontal,
            self::Paid => Heroicon::OutlinedCheckCircle,
        };
    }
}
