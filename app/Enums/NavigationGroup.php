<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Sidebar groups, in display order. Labels are resolved at render time so
 * they follow each user's language.
 */
enum NavigationGroup implements HasLabel
{
    case Operations;
    case Reports;
    case Portals;
    case Administration;

    public function getLabel(): string
    {
        return match ($this) {
            self::Operations => __('Operations'),
            self::Reports => __('Reports'),
            self::Portals => __('Portals & Wallet'),
            self::Administration => __('Administration'),
        };
    }
}
