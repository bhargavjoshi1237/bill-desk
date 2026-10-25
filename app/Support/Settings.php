<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Business-wide settings stored as key/value rows, cached forever and
 * flushed whenever they are saved.
 */
final class Settings
{
    private const CACHE_KEY = 'app-settings';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'business_name' => config('app.name'),
            'business_address' => null,
            'business_phone' => null,
            'default_service_charge_percent' => 2.5,
            'default_due_days' => 7,
            'whatsapp_template' => 'Namaste {name}, your outstanding amount with {business} is {amount}. Kindly pay at the earliest. Thank you.',
            'receipt_footer' => 'Thank you for your business.',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $stored = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );

        return array_merge(self::defaults(), array_filter($stored, fn ($value) => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            $setting = Setting::query()->firstOrNew(['key' => $key]);
            $setting->value = $value === null ? null : (string) $value;
            $setting->save();
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function serviceChargePercent(): float
    {
        return (float) self::get('default_service_charge_percent');
    }

    public static function dueDays(): int
    {
        return (int) self::get('default_due_days');
    }

    public static function businessName(): string
    {
        return (string) self::get('business_name');
    }
}
