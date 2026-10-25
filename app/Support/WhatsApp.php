<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Entry;

/**
 * Builds wa.me links that open WhatsApp with a pre-filled dues reminder.
 * No API or paid account is needed: the operator just presses send.
 */
final class WhatsApp
{
    public static function reminderUrl(Customer $customer, float|int|string|null $amount = null, ?Entry $entry = null): string
    {
        $message = strtr((string) Settings::get('whatsapp_template'), [
            '{name}' => $customer->name,
            '{amount}' => Money::format($amount ?? $customer->totalOutstanding()),
            '{business}' => Settings::businessName(),
            '{due_date}' => $entry?->due_date?->format('d-m-Y') ?? '',
            '{entry}' => $entry?->number() ?? '',
        ]);

        return 'https://wa.me/'.self::phone($customer->mobile).'?text='.rawurlencode($message);
    }

    public static function phone(?string $mobile): string
    {
        $digits = (string) Customer::normalizeMobile($mobile);

        return strlen($digits) === 10 ? '91'.$digits : $digits;
    }
}
