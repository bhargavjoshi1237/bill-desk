<?php

namespace App\Filament\Resources\Portals\Pages;

use App\Filament\Resources\Portals\PortalResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPortal extends ViewRecord
{
    protected static string $resource = PortalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
