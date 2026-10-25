<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Actions\CustomerStatementAction;
use App\Filament\Actions\WhatsAppReminderAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Entries\EntryResource;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newEntry')
                ->label(__('New entry'))
                ->icon(Heroicon::OutlinedPlus)
                ->url(fn (Customer $record): string => EntryResource::getUrl('create', ['customer' => $record->getKey()])),
            WhatsAppReminderAction::make(),
            CustomerStatementAction::make(),
            EditAction::make(),
            ActionGroup::make([
                DeleteAction::make(),
            ]),
        ];
    }
}
