<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\DateRange;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Reads the page's period filters (preset / from / until / grouping).
 */
trait ResolvesDateRange
{
    use InteractsWithPageFilters;

    protected function dateRange(): DateRange
    {
        return DateRange::fromFilters($this->pageFilters);
    }

    protected function grouping(): ?string
    {
        $grouping = $this->pageFilters['grouping'] ?? null;

        return in_array($grouping, ['day', 'week', 'month', 'year'], true) ? $grouping : null;
    }

    protected function isAdmin(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
