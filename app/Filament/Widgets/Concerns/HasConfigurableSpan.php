<?php

namespace App\Filament\Widgets\Concerns;

/**
 * Lets a page override a widget's width, e.g. Widget::make(['span' => 'full']).
 */
trait HasConfigurableSpan
{
    public int|string|array|null $span = null;

    public function getColumnSpan(): int|string|array
    {
        return $this->span ?? $this->columnSpan;
    }
}
