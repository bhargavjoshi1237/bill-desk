<?php

namespace App\Models;

use App\Enums\PaymentMode;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Money received from a customer against an entry. Saving or deleting a
 * payment re-totals its entry.
 */
#[Fillable(['entry_id', 'paid_on', 'amount', 'mode', 'reference', 'notes', 'received_by'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
            'mode' => PaymentMode::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->received_by ??= auth()->id();
        });

        static::saved(function (Payment $payment): void {
            $payment->retotalEntries();
        });

        static::deleted(function (Payment $payment): void {
            $payment->retotalEntries();
        });
    }

    protected function retotalEntries(): void
    {
        $entryIds = array_filter(array_unique([$this->entry_id, $this->getOriginal('entry_id')]));

        Entry::query()->whereKey($entryIds)->get()
            ->each(fn (Entry $entry) => $entry->recalculate()->saveQuietly());
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * e.g. R-00042.
     */
    public function number(): string
    {
        return 'R-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
