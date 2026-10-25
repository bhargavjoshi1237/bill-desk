<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Payment status of an entry. "Overdue" is not stored: it is an unpaid or
 * partial entry whose due date has passed (see Entry::displayStatus()).
 */
enum EntryStatus: string implements HasColor, HasIcon, HasLabel
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';

    /**
     * Statuses that can be persisted on an entry.
     *
     * @return array<self>
     */
    public static function stored(): array
    {
        return [self::Unpaid, self::Partial, self::Paid];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::Partial => __('Partial'),
            self::Paid => __('Paid'),
            self::Overdue => __('Overdue'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Partial => 'info',
            self::Paid => 'success',
            self::Overdue => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Unpaid => Heroicon::OutlinedClock,
            self::Partial => Heroicon::OutlinedAdjustmentsHorizontal,
            self::Paid => Heroicon::OutlinedCheckCircle,
            self::Overdue => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
