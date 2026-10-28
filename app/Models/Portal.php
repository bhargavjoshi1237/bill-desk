<?php

namespace App\Models;

use App\Enums\PortalType;
use App\Enums\WalletTransactionType;
use App\Support\Money;
use Database\Factories\PortalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A payment portal wallet or one of our bank accounts. On an entry it can be
 * the side that paid the card bill (entries) or the side that received the
 * customer's money (receivedEntries).
 */
#[Fillable(['name', 'type', 'charge_percent', 'low_balance_alert', 'is_active', 'notes'])]
class Portal extends Model
{
    /** @use HasFactory<PortalFactory> */
    use HasFactory, LogsActivity;

    protected $attributes = [
        'type' => 'portal',
    ];

    protected function casts(): array
    {
        return [
            'type' => PortalType::class,
            'charge_percent' => 'decimal:3',
            'low_balance_alert' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Entries whose card bill was paid from here.
     *
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * Entries whose bill amount the customer paid back into here.
     *
     * @return HasMany<Entry, $this>
     */
    public function receivedEntries(): HasMany
    {
        return $this->hasMany(Entry::class, 'received_portal_id');
    }

    /**
     * @return HasMany<WalletTransaction, $this>
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function isBankAccount(): bool
    {
        return $this->type === PortalType::BankAccount;
    }

    public function totalTopUps(): string
    {
        return Money::fromPaise(Money::toPaise(
            $this->walletTransactions()->where('type', WalletTransactionType::TopUp)->sum('amount'),
        ));
    }

    public function totalWithdrawals(): string
    {
        return Money::fromPaise(Money::toPaise(
            $this->walletTransactions()->where('type', WalletTransactionType::Withdrawal)->sum('amount'),
        ));
    }

    /**
     * Money sent out: every entry paid from here debits bill + portal charge.
     */
    public function totalUsed(?int $exceptEntryId = null): string
    {
        $sum = $this->entries()
            ->when($exceptEntryId, fn ($query) => $query->whereKeyNot($exceptEntryId))
            ->selectRaw('COALESCE(SUM(bill_amount + portal_deduction), 0) AS used')
            ->value('used');

        return Money::fromPaise(Money::toPaise($sum));
    }

    /**
     * Money received: every entry received here credits the bill amount.
     */
    public function totalReceived(?int $exceptEntryId = null): string
    {
        $sum = $this->receivedEntries()
            ->when($exceptEntryId, fn ($query) => $query->whereKeyNot($exceptEntryId))
            ->sum('bill_amount');

        return Money::fromPaise(Money::toPaise($sum));
    }

    public function walletBalance(?int $exceptEntryId = null): string
    {
        return Money::fromPaise(
            Money::toPaise($this->totalTopUps())
            - Money::toPaise($this->totalWithdrawals())
            + Money::toPaise($this->totalReceived($exceptEntryId))
            - Money::toPaise($this->totalUsed($exceptEntryId)),
        );
    }

    public function isLowOnBalance(): bool
    {
        $balance = Money::toPaise($this->walletBalance());

        return $balance < 0
            || ($this->low_balance_alert !== null && $balance < Money::toPaise($this->low_balance_alert));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
