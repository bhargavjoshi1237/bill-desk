<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'mobile', 'address', 'city', 'aadhaar_number', 'pan_number', 'id_proof_files', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'id_proof_files' => 'array',
        ];
    }

    /**
     * Store mobiles as plain 10-digit numbers, dropping spaces, dashes and a
     * leading +91 / 0 so the same customer can't be added twice.
     */
    protected function mobile(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::normalizeMobile($value));
    }

    protected function panNumber(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? strtoupper(trim($value)) : null);
    }

    protected function aadhaarNumber(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? preg_replace('/\D/', '', $value) : null);
    }

    public static function normalizeMobile(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }

    /**
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * @return HasMany<CustomerCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(CustomerCard::class);
    }

    /**
     * @return HasManyThrough<Payment, Entry, $this>
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Entry::class);
    }

    public function totalOutstanding(): string
    {
        return Money::fromPaise(Money::toPaise($this->entries()->sum('outstanding')));
    }

    public function displayName(): string
    {
        return "{$this->name} ({$this->mobile})";
    }

    public function maskedAadhaar(): ?string
    {
        return $this->aadhaar_number ? 'XXXX XXXX '.substr($this->aadhaar_number, -4) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logExcept(['id_proof_files'])
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
