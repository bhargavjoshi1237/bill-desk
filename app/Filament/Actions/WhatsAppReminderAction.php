<?php

namespace App\Filament\Actions;

use App\Models\Customer;
use App\Models\Entry;
use App\Support\Money;
use App\Support\WhatsApp;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Opens WhatsApp with a pre-filled dues reminder, for an entry (its own
 * outstanding) or a customer (their total outstanding).
 */
final class WhatsAppReminderAction
{
    public static function make(string $name = 'whatsappReminder'): Action
    {
        return Action::make($name)
            ->label(__('WhatsApp reminder'))
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('success')
            ->visible(fn (Model $record): bool => Money::toPaise(self::outstanding($record)) > 0
                && filled(self::customer($record)?->mobile))
            ->url(fn (Model $record): string => $record instanceof Entry
                ? WhatsApp::reminderUrl($record->customer, $record->outstanding, $record)
                : WhatsApp::reminderUrl($record, self::outstanding($record)), shouldOpenInNewTab: true);
    }

    private static function customer(Model $record): ?Customer
    {
        return match (true) {
            $record instanceof Entry => $record->customer,
            $record instanceof Customer => $record,
            default => null,
        };
    }

    private static function outstanding(Model $record): string
    {
        return match (true) {
            $record instanceof Entry => $record->outstanding,
            $record instanceof Customer => $record->outstanding_total ?? $record->totalOutstanding(),
            default => '0',
        };
    }
}
