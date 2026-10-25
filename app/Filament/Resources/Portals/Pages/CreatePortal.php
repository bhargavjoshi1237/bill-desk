<?php

namespace App\Filament\Resources\Portals\Pages;

use App\Filament\Concerns\HasFormContentWidth;
use App\Filament\Resources\Portals\PortalResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePortal extends CreateRecord
{
    use HasFormContentWidth;

    protected static string $resource = PortalResource::class;
}
