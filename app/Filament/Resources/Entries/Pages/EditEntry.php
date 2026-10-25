<?php

namespace App\Filament\Resources\Entries\Pages;

use App\Filament\Concerns\HasFormContentWidth;
use App\Filament\Resources\Entries\EntryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEntry extends EditRecord
{
    use HasFormContentWidth;

    protected static string $resource = EntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
