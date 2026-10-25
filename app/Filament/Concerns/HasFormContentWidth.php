<?php

namespace App\Filament\Concerns;

use Filament\Support\Enums\Width;

/**
 * Keeps form pages at a readable, centered width on wide monitors. The panel
 * stays full width for dashboards, tables and view pages.
 */
trait HasFormContentWidth
{
    public function getMaxContentWidth(): Width
    {
        return Width::SevenExtraLarge;
    }
}
