<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use App\Support\Money;
use Database\Factories\PortalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'charge_percent', 'low_balance_alert', 'is_active', 'notes'])]
class Portal extends Model
{
    /** @use HasFactory<PortalFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'charge_percent' => 'decimal:3',
            'low_balance_alert' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * @return HasMany<WalletTransaction, $this>
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
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
     * Money spent from the wallet: every entry debits bill + portal charge.
     */
    public function totalUsed(?int $exceptEntryId = null): string
    {
        $sum = $this->entries()
            ->when($exceptEntryId, fn ($query) => $query->whereKeyNot($exceptEntryId))
            ->selectRaw('COALESCE(SUM(bill_amount + portal_deduction), 0) AS used')
            ->value('used');

        return Money::fromPaise(Money::toPaise($sum));
    }

    public function walletBalance(?int $exceptEntryId = null): string
    {
        return Money::fromPaise(
            Money::toPaise($this->totalTopUps())
            - Money::toPaise($this->totalWithdrawals())
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
