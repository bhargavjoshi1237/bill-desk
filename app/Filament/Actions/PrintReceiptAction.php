<?php

namespace App\Filament\Actions;

use App\Models\Entry;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Opens the printable PDF receipt for an entry or a payment in a new tab.
 */
final class PrintReceiptAction
{
    public static function make(): Action
    {
        return Action::make('printReceipt')
            ->label(__('Receipt (PDF)'))
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(fn (Model $record): string => match (true) {
                $record instanceof Payment => route('print.payment-receipt', $record),
                $record instanceof Entry => route('print.entry-receipt', $record),
            }, shouldOpenInNewTab: true);
    }
}
