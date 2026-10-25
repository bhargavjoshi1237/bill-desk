<?php

namespace App\Filament\Resources\Portals\Pages;

use App\Filament\Resources\Portals\PortalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPortals extends ListRecords
{
    protected static string $resource = PortalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
