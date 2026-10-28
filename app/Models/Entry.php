<?php

namespace App\Models;

use App\Enums\EntryStatus;
use App\Support\Money;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One processed credit card bill payment: we pay the customer's card bill
 * from one portal / account (portal) and the customer pays the bill amount
 * back into another (receivedPortal). The bill amount nets out, so the
 * customer owes only the portal charge + our service charge.
 *
 * Only the inputs are fillable. Every derived column (deduction, service
 * charge, totals, outstanding, profit, status) is computed by recalculate(),
 * which runs on every save and whenever a payment changes.
 */
#[Fillable([
    'entry_date', 'customer_id', 'customer_card_id', 'bank_id', 'portal_id', 'received_portal_id',
    'bill_amount', 'portal_charge_percent', 'service_charge_percent',
    'due_date', 'reference', 'notes', 'created_by',
])]
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
            'bill_amount' => 'decimal:2',
            'portal_charge_percent' => 'decimal:3',
            'service_charge_percent' => 'decimal:3',
            'portal_deduction' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'total_due' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'outstanding' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'status' => EntryStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Entry $entry): void {
            $entry->created_by ??= auth()->id();
        });

        static::saving(function (Entry $entry): void {
            if ($entry->customer_card_id && $entry->isDirty('customer_card_id')) {
                $entry->bank_id = CustomerCard::query()->whereKey($entry->customer_card_id)->value('bank_id') ?? $entry->bank_id;
            }

            $entry->recalculate();
        });
    }

    /**
     * Fill the derived columns from the inputs and recorded payments.
     */
    public function recalculate(): static
    {
        $this->portal_deduction = Money::percentOf($this->bill_amount, $this->portal_charge_percent);
        $this->service_charge = Money::percentOf($this->bill_amount, $this->service_charge_percent);
        // The customer also pays the portal charge back, so our profit is the whole service charge.
        $this->total_due = Money::add($this->portal_deduction, $this->service_charge);
        $this->net_profit = $this->service_charge;

        $receivedPaise = $this->exists ? Money::toPaise($this->payments()->sum('amount')) : 0;
        $outstandingPaise = Money::toPaise($this->total_due) - $receivedPaise;

        $this->amount_received = Money::fromPaise($receivedPaise);
        $this->outstanding = Money::fromPaise($outstandingPaise);

        $this->status = match (true) {
            $outstandingPaise <= 0 => EntryStatus::Paid,
            $receivedPaise > 0 => EntryStatus::Partial,
            default => EntryStatus::Unpaid,
        };

        // The day the last rupee came in, used for "collected profit" reports.
        $this->paid_at = $this->status === EntryStatus::Paid
            ? ($this->exists ? $this->payments()->max('paid_on') : null) ?? $this->entry_date
            : null;

        return $this;
    }

    public function isOverdue(): bool
    {
        return $this->status !== EntryStatus::Paid
            && $this->due_date !== null
            && $this->due_date->lt(today());
    }

    /**
     * The stored status, or Overdue when the due date has passed.
     */
    public function displayStatus(): EntryStatus
    {
        return $this->isOverdue() ? EntryStatus::Overdue : $this->status;
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    /**
     * e.g. E-00042.
     */
    public function number(): string
    {
        return 'E-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('outstanding', '>', 0);
    }

    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('outstanding', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());
    }

    #[Scope]
    protected function betweenDates(Builder $query, $from, $until, string $column = 'entry_date'): void
    {
        $query
            ->when($from, fn (Builder $query) => $query->whereDate($column, '>=', $from))
            ->when($until, fn (Builder $query) => $query->whereDate($column, '<=', $until));
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CustomerCard, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(CustomerCard::class, 'customer_card_id');
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * The portal / account the card bill was paid from ("sent by").
     *
     * @return BelongsTo<Portal, $this>
     */
    public function portal(): BelongsTo
    {
        return $this->belongsTo(Portal::class);
    }

    /**
     * The portal / account the customer's money came into ("received in").
     *
     * @return BelongsTo<Portal, $this>
     */
    public function receivedPortal(): BelongsTo
    {
        return $this->belongsTo(Portal::class, 'received_portal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
