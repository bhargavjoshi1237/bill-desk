<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Operator = 'operator';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::Operator => __('Operator'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Operator => 'info',
        };
    }
}
