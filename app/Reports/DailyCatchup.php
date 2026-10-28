<?php

namespace App\Reports;

use App\Enums\PortalType;
use App\Enums\WalletTransactionType;
use App\Models\Entry;
use App\Models\Payment;
use App\Models\Portal;
use App\Models\WalletTransaction;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * End-of-day position of every portal and bank account: opening balance,
 * the day's movements and the closing (latest) balance.
 *
 * Movements follow Portal::walletBalance(): top-ups add, withdrawals take
 * out, an entry sent from a portal takes out bill + portal deduction, and
 * an entry received into a portal adds the bill amount.
 */
final class DailyCatchup
{
    private const MOVEMENTS = ['top_ups', 'withdrawals', 'received', 'sent'];

    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $rows = null;

    public function __construct(public readonly CarbonInterface $date) {}

    /**
     * One row per active portal, plus inactive ones that still hold money
     * or moved money that day. Amounts are 2-decimal strings.
     *
     * @return Collection<int, array{id: int, name: string, type: PortalType, is_active: bool, is_low: bool, opening: string, top_ups: string, withdrawals: string, received: string, sent: string, closing: string}>
     */
    public function rows(): Collection
    {
        return $this->rows ??= $this->buildRows();
    }

    /**
     * @return array{total: string, bank_accounts: string, portals: string, received: string, sent: string, fees_collected: string, payments_count: int, entries: int}
     */
    public function totals(): array
    {
        $rows = $this->rows();
        $sum = fn (string $column, ?PortalType $type = null): string => Money::fromPaise($rows
            ->when($type, fn (Collection $rows) => $rows->where('type', $type))
            ->sum(fn (array $row): int => Money::toPaise($row[$column])));

        $payments = Payment::query()->whereDate('paid_on', $this->date);

        return [
            'total' => $sum('closing'),
            'bank_accounts' => $sum('closing', PortalType::BankAccount),
            'portals' => $sum('closing', PortalType::Portal),
            'received' => $sum('received'),
            'sent' => $sum('sent'),
            'fees_collected' => Money::fromPaise(Money::toPaise((clone $payments)->sum('amount'))),
            'payments_count' => (clone $payments)->count(),
            'entries' => Entry::query()->whereDate('entry_date', $this->date)->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRows(): Collection
    {
        $before = $this->movements('<');
        $onDay = $this->movements('=');

        return Portal::query()->orderBy('type')->orderBy('name')->get()
            ->map(function (Portal $portal) use ($before, $onDay): array {
                $prior = $before[$portal->id] ?? [];
                $today = $onDay[$portal->id] ?? [];

                $openingPaise = self::net($prior);
                $closingPaise = $openingPaise + self::net($today);

                return [
                    'id' => $portal->id,
                    'name' => $portal->name,
                    'type' => $portal->type,
                    'is_active' => $portal->is_active,
                    'is_low' => $closingPaise < 0
                        || ($portal->low_balance_alert !== null && $closingPaise < Money::toPaise($portal->low_balance_alert)),
                    'opening' => Money::fromPaise($openingPaise),
                    ...collect(self::MOVEMENTS)->mapWithKeys(fn (string $key): array => [$key => Money::fromPaise($today[$key] ?? 0)])->all(),
                    'closing' => Money::fromPaise($closingPaise),
                ];
            })
            ->filter(fn (array $row): bool => $row['is_active']
                || Money::toPaise($row['closing']) !== 0
                || collect(self::MOVEMENTS)->contains(fn (string $key): bool => Money::toPaise($row[$key]) !== 0))
            ->keyBy('id');
    }

    /**
     * Paise moved per portal before ('<') or on ('=') the date.
     *
     * @return array<int, array<string, int>>
     */
    private function movements(string $operator): array
    {
        $totals = [];
        $add = function (int|string|null $portalId, string $key, mixed $amount) use (&$totals): void {
            if ($portalId !== null) {
                $totals[(int) $portalId][$key] = ($totals[(int) $portalId][$key] ?? 0) + Money::toPaise($amount);
            }
        };

        WalletTransaction::query()
            ->whereDate('transaction_date', $operator, $this->date)
            ->selectRaw('portal_id, type, SUM(amount) AS total')
            ->groupBy('portal_id', 'type')
            ->get()
            ->each(fn ($row) => $add($row->portal_id, $row->type === WalletTransactionType::TopUp ? 'top_ups' : 'withdrawals', $row->total));

        Entry::query()
            ->whereDate('entry_date', $operator, $this->date)
            ->selectRaw('portal_id, SUM(bill_amount + portal_deduction) AS total')
            ->groupBy('portal_id')
            ->get()
            ->each(fn ($row) => $add($row->portal_id, 'sent', $row->total));

        Entry::query()
            ->whereNotNull('received_portal_id')
            ->whereDate('entry_date', $operator, $this->date)
            ->selectRaw('received_portal_id, SUM(bill_amount) AS total')
            ->groupBy('received_portal_id')
            ->get()
            ->each(fn ($row) => $add($row->received_portal_id, 'received', $row->total));

        return $totals;
    }

    /**
     * @param  array<string, int>  $movements
     */
    private static function net(array $movements): int
    {
        return ($movements['top_ups'] ?? 0) - ($movements['withdrawals'] ?? 0)
            + ($movements['received'] ?? 0) - ($movements['sent'] ?? 0);
    }
}
