<?php

namespace App\Reports;

use App\Models\Customer;
use App\Models\Entry;
use App\Models\Payment;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * A customer's account ledger: each entry is a debit (total due), each
 * payment a credit, with a running balance from the opening balance.
 */
final class CustomerStatement
{
    public function __construct(
        public readonly Customer $customer,
        public readonly DateRange $range,
    ) {}

    /**
     * @return array{opening: string, closing: string, debits: string, credits: string, lines: Collection<int, array<string, mixed>>, open_entries: Collection<int, Entry>}
     */
    public function build(): array
    {
        $from = $this->range->fromDate();
        $until = $this->range->untilDate();

        $openingPaise = Money::toPaise($this->customer->entries()->whereDate('entry_date', '<', $from)->sum('total_due'))
            - Money::toPaise($this->customer->payments()->whereDate('paid_on', '<', $from)->sum('payments.amount'));

        $entries = $this->customer->entries()
            ->with(['bank', 'portal', 'card'])
            ->betweenDates($from, $until)
            ->get()
            ->map(fn (Entry $entry): array => [
                'date' => $entry->entry_date,
                'sort' => 0,
                'id' => $entry->id,
                'number' => $entry->number(),
                'description' => __('Card bill :bill paid: :card via :portal (portal charge :fee + service :service)', [
                    'card' => $entry->card?->label() ?: $entry->bank?->name,
                    'portal' => $entry->portal?->name,
                    'bill' => Money::format($entry->bill_amount),
                    'fee' => Money::format($entry->portal_deduction),
                    'service' => Money::format($entry->service_charge),
                ]),
                'debit' => $entry->total_due,
                'credit' => null,
            ]);

        $payments = $this->customer->payments()
            ->with('entry')
            ->whereDate('paid_on', '>=', $from)
            ->whereDate('paid_on', '<=', $until)
            ->get()
            ->map(fn (Payment $payment): array => [
                'date' => $payment->paid_on,
                'sort' => 1,
                'id' => $payment->id,
                'number' => $payment->number(),
                'description' => trim(__('Payment received (:mode) for :entry', [
                    'mode' => $payment->mode->getLabel(),
                    'entry' => $payment->entry->number(),
                ]).($payment->reference ? ' · '.$payment->reference : '')),
                'debit' => null,
                'credit' => $payment->amount,
            ]);

        $balance = $openingPaise;

        $lines = $entries->concat($payments)
            ->sortBy([['date', 'asc'], ['sort', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (array $line) use (&$balance): array {
                $balance += Money::toPaise($line['debit']) - Money::toPaise($line['credit']);

                return [...$line, 'balance' => Money::fromPaise($balance)];
            });

        return [
            'opening' => Money::fromPaise($openingPaise),
            'closing' => Money::fromPaise($balance),
            'debits' => Money::fromPaise($lines->sum(fn (array $line): int => Money::toPaise($line['debit']))),
            'credits' => Money::fromPaise($lines->sum(fn (array $line): int => Money::toPaise($line['credit']))),
            'lines' => $lines,
            'open_entries' => $this->customer->entries()->open()->with('bank')->orderBy('entry_date')->get(),
        ];
    }
}
