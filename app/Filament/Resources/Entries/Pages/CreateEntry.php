<?php

namespace App\Filament\Resources\Entries\Pages;

use App\Filament\Resources\Entries\EntryResource;
use App\Models\Entry;
use App\Support\Money;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEntry extends CreateRecord
{
    protected static string $resource = EntryResource::class;

    /**
     * @var array<string, mixed>
     */
    protected array $initialPayment = [];

    public function getTitle(): string
    {
        return __('New entry');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->initialPayment = $data['initial_payment'] ?? [];
        unset($data['initial_payment']);

        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Entry $entry */
        $entry = $this->record;

        if (Money::toPaise($this->initialPayment['amount'] ?? 0) > 0) {
            $entry->payments()->create([
                'paid_on' => $entry->entry_date,
                'amount' => $this->initialPayment['amount'],
                'mode' => $this->initialPayment['mode'] ?? 'cash',
                'reference' => $this->initialPayment['reference'] ?? null,
            ]);

            $entry->refresh();
        }

        if (Money::toPaise($entry->portal->walletBalance()) < 0) {
            Notification::make()
                ->warning()
                ->title(__('Portal wallet balance is negative'))
                ->body(__(':portal wallet is now at :amount. Please record a top-up.', [
                    'portal' => $entry->portal->name,
                    'amount' => Money::format($entry->portal->walletBalance()),
                ]))
                ->persistent()
                ->send();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('Entry saved');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
