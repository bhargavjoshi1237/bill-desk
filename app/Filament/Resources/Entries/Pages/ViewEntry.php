<?php

namespace App\Filament\Resources\Entries\Pages;

use App\Filament\Actions\PrintReceiptAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Actions\WhatsAppReminderAction;
use App\Filament\Resources\Entries\EntryResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewEntry extends ViewRecord
{
    protected static string $resource = EntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecordPaymentAction::make()
                ->after(fn () => $this->refreshEntry()),
            WhatsAppReminderAction::make(),
            PrintReceiptAction::make(),
            EditAction::make(),
            ActionGroup::make([
                DeleteAction::make(),
            ]),
        ];
    }

    /**
     * Payments are managed in a relation manager (a separate Livewire
     * component), so reload the totals when it reports a change.
     */
    #[On('entry-payments-changed')]
    public function refreshEntry(): void
    {
        $this->record->refresh();
    }
}
