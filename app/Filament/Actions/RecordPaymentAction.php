<?php

namespace App\Filament\Actions;

use App\Enums\PaymentMode;
use App\Filament\Resources\Payments\Schemas\PaymentForm;
use App\Models\Entry;
use App\Models\Payment;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * "Record payment" on an entry: opens the payment form pre-filled with the
 * outstanding amount.
 */
final class RecordPaymentAction
{
    public static function make(): Action
    {
        return Action::make('recordPayment')
            ->label(__('Record payment'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Entry $record): bool => Money::toPaise($record->outstanding) > 0
                && auth()->user()->can('create', Payment::class))
            ->modalHeading(fn (Entry $record): string => __('Record payment for :number', ['number' => $record->number()]))
            ->modalDescription(fn (Entry $record): string => __(':customer owes :amount', [
                'customer' => $record->customer->name,
                'amount' => Money::format($record->outstanding),
            ]))
            ->fillForm(fn (Entry $record): array => [
                'paid_on' => today()->toDateString(),
                'amount' => $record->outstanding,
                'mode' => PaymentMode::Cash->value,
            ])
            ->schema(PaymentForm::fields(fn (Entry $record): Entry => $record))
            ->action(function (Entry $record, array $data): void {
                $record->payments()->create($data);

                Notification::make()
                    ->success()
                    ->title(__('Payment recorded'))
                    ->body(__('Outstanding is now :amount', ['amount' => Money::format($record->refresh()->outstanding)]))
                    ->send();
            });
    }
}
