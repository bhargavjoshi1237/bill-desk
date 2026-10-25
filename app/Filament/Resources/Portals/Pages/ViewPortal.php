<?php

namespace App\Filament\Resources\Portals\Pages;

use App\Filament\Resources\Portals\PortalResource;
use App\Filament\Resources\Portals\Tables\PortalsTable;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewPortal extends ViewRecord
{
    protected static string $resource = PortalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PortalsTable::topUpAction(),
            EditAction::make(),
        ];
    }

    #[On('wallet-transactions-changed')]
    public function refreshPortal(): void
    {
        $this->record->refresh();
    }
}
