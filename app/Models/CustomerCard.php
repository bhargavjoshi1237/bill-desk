<?php

namespace App\Models;

use App\Enums\CardNetwork;
use Database\Factories\CustomerCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A customer's credit card. Only the last four digits are kept; the full
 * card number is never stored.
 */
#[Fillable(['customer_id', 'bank_id', 'last_four', 'network'])]
class CustomerCard extends Model
{
    /** @use HasFactory<CustomerCardFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'network' => CardNetwork::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * e.g. "HDFC Bank •••• 1234 (Visa)".
     */
    public function label(): string
    {
        return collect([
            $this->bank?->name,
            $this->last_four ? '•••• '.$this->last_four : null,
            $this->network ? '('.$this->network->getLabel().')' : null,
        ])->filter()->implode(' ');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
