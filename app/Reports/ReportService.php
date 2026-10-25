<?php

namespace App\Reports;

use App\Models\Bank;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\Payment;
use App\Models\Portal;
use App\Support\DateRange;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Every figure on the dashboard, report pages and exports comes from here,
 * so they always agree with each other.
 *
 * "Booked" figures follow the entry date (like the original sheet).
 * "Collected" figures follow the money: payments by the date received, and
 * collected profit by the date an entry became fully paid.
 */
final class ReportService
{
    /**
     * Sum columns shared by the summary and every breakdown.
     */
    private const ENTRY_SUMS = [
        'bill_amount', 'portal_deduction', 'service_charge', 'total_due',
        'amount_received', 'outstanding', 'net_profit',
    ];

    /**
     * @return array<string, int|string>
     */
    public function summary(DateRange $range): array
    {
        $totals = $this->entryTotals(
            Entry::query()->betweenDates($range->fromDate(), $range->untilDate()),
        );

        $collected = Payment::query()
            ->whereDate('paid_on', '>=', $range->fromDate())
            ->whereDate('paid_on', '<=', $range->untilDate());

        $settled = Entry::query()->betweenDates($range->fromDate(), $range->untilDate(), 'paid_at');

        return [
            ...$totals,
            'collected_amount' => self::money($collected->sum('amount')),
            'payments_count' => $collected->count(),
            'collected_profit' => self::money($settled->sum('net_profit')),
            'settled_entries' => $settled->count(),
        ];
    }

    /**
     * Figures that don't depend on a date range: what is owed right now.
     *
     * @return array<string, int|string>
     */
    public function currentPosition(): array
    {
        return [
            'outstanding' => self::money(Entry::query()->open()->sum('outstanding')),
            'open_entries' => Entry::query()->open()->count(),
            'overdue' => self::money(Entry::query()->overdue()->sum('outstanding')),
            'overdue_entries' => Entry::query()->overdue()->count(),
            'customers_owing' => Entry::query()->open()->distinct()->count('customer_id'),
        ];
    }

    /**
     * Totals per day / week / month / year, including empty periods so charts
     * have a continuous axis.
     *
     * @return Collection<string, array<string, int|string>>
     */
    public function byPeriod(DateRange $range, ?string $grouping = null): Collection
    {
        $grouping ??= $range->suggestedGrouping();

        $periods = $this->emptyPeriods($range, $grouping);

        $daily = Entry::query()
            ->betweenDates($range->fromDate(), $range->untilDate())
            ->selectRaw('entry_date AS day, '.$this->sumSelects())
            ->groupBy('entry_date')
            ->toBase()
            ->get();

        foreach ($daily as $row) {
            $key = $this->periodKey(CarbonImmutable::parse($row->day), $grouping);
            $periods[$key]['entries'] += (int) $row->entries;

            foreach (self::ENTRY_SUMS as $column) {
                $periods[$key][$column] = Money::add($periods[$key][$column], $row->{$column});
            }
        }

        $collections = Payment::query()
            ->whereDate('paid_on', '>=', $range->fromDate())
            ->whereDate('paid_on', '<=', $range->untilDate())
            ->selectRaw('paid_on AS day, SUM(amount) AS amount')
            ->groupBy('paid_on')
            ->toBase()
            ->get();

        foreach ($collections as $row) {
            $key = $this->periodKey(CarbonImmutable::parse($row->day), $grouping);
            $periods[$key]['collected_amount'] = Money::add($periods[$key]['collected_amount'], $row->amount);
        }

        $settlements = Entry::query()
            ->betweenDates($range->fromDate(), $range->untilDate(), 'paid_at')
            ->selectRaw('paid_at AS day, SUM(net_profit) AS profit')
            ->groupBy('paid_at')
            ->toBase()
            ->get();

        foreach ($settlements as $row) {
            $key = $this->periodKey(CarbonImmutable::parse($row->day), $grouping);
            $periods[$key]['collected_profit'] = Money::add($periods[$key]['collected_profit'], $row->profit);
        }

        return collect($periods);
    }

    /**
     * A period with no entries and no money collected. Charts keep these for a
     * continuous axis; tables and exports leave them out.
     *
     * @param  array<string, int|string>  $row
     */
    public static function isEmptyPeriod(array $row): bool
    {
        return $row['entries'] === 0
            && Money::toPaise($row['collected_amount']) === 0
            && Money::toPaise($row['collected_profit']) === 0;
    }

    /**
     * @return Collection<int, array<string, int|string>>
     */
    public function byPortal(DateRange $range): Collection
    {
        return $this->byDimension($range, 'portal_id', Portal::query()->pluck('name', 'id'));
    }

    /**
     * @return Collection<int, array<string, int|string>>
     */
    public function byBank(DateRange $range): Collection
    {
        return $this->byDimension($range, 'bank_id', Bank::query()->pluck('name', 'id'));
    }

    /**
     * @return Collection<int, array<string, int|string>>
     */
    public function byCustomer(DateRange $range, ?int $limit = null): Collection
    {
        $rows = $this->byDimension($range, 'customer_id', null, $limit);

        $customers = Customer::query()->whereKey($rows->keys())->get(['id', 'name', 'mobile'])->keyBy('id');

        return $rows->map(fn (array $row, int $id): array => [
            ...$row,
            'name' => $customers->get($id)?->name ?? '—',
            'mobile' => $customers->get($id)?->mobile,
        ]);
    }

    /**
     * Open (not fully paid) entries grouped by how long ago they were made.
     *
     * @return array<string, array{label: string, entries: int, outstanding: string}>
     */
    public function aging(): array
    {
        $buckets = [
            '0_7' => ['label' => __('0–7 days'), 'entries' => 0, 'outstanding' => '0.00'],
            '8_30' => ['label' => __('8–30 days'), 'entries' => 0, 'outstanding' => '0.00'],
            '31_plus' => ['label' => __('30+ days'), 'entries' => 0, 'outstanding' => '0.00'],
        ];

        $today = CarbonImmutable::today();

        Entry::query()->open()->select(['id', 'entry_date', 'outstanding'])->lazy()
            ->each(function (Entry $entry) use (&$buckets, $today): void {
                $key = self::agingBucket((int) $entry->entry_date->diffInDays($today));
                $buckets[$key]['entries']++;
                $buckets[$key]['outstanding'] = Money::add($buckets[$key]['outstanding'], $entry->outstanding);
            });

        return $buckets;
    }

    public static function agingBucket(int $ageInDays): string
    {
        return match (true) {
            $ageInDays <= 7 => '0_7',
            $ageInDays <= 30 => '8_30',
            default => '31_plus',
        };
    }

    /**
     * Customers who still owe money, with how much, how much is overdue and
     * their oldest unpaid entry.
     *
     * @return Builder<Customer>
     */
    public function outstandingCustomersQuery(): Builder
    {
        return Customer::query()
            ->whereHas('entries', fn (Builder $query) => $query->open())
            ->withSum(['entries as outstanding_total' => fn (Builder $query) => $query->open()], 'outstanding')
            ->withSum(['entries as overdue_total' => fn (Builder $query) => $query->overdue()], 'outstanding')
            ->withCount(['entries as open_entries_count' => fn (Builder $query) => $query->open()])
            ->withMin(['entries as oldest_open_entry_date' => fn (Builder $query) => $query->open()], 'entry_date');
    }

    /**
     * Daily points for trend charts.
     *
     * @return array{labels: array<string>, series: array<string, array<float>>}
     */
    public function trend(DateRange $range, ?string $grouping = null): array
    {
        $periods = $this->byPeriod($range, $grouping);

        return [
            'labels' => $periods->pluck('label')->all(),
            'series' => collect(['bill_amount', 'service_charge', 'net_profit', 'collected_amount', 'collected_profit'])
                ->mapWithKeys(fn (string $column): array => [
                    $column => $periods->pluck($column)->map(fn ($value): float => (float) $value)->values()->all(),
                ])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, string>|null  $names
     * @return Collection<int, array<string, int|string>>
     */
    private function byDimension(DateRange $range, string $column, ?Collection $names, ?int $limit = null): Collection
    {
        return Entry::query()
            ->betweenDates($range->fromDate(), $range->untilDate())
            ->selectRaw("{$column} AS dimension_id, ".$this->sumSelects())
            ->groupBy($column)
            ->orderByRaw('SUM(bill_amount) DESC')
            ->when($limit, fn ($query) => $query->limit($limit))
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->dimension_id => [
                    'name' => $names?->get($row->dimension_id) ?? '—',
                    ...$this->normalizeTotals($row),
                ],
            ]);
    }

    /**
     * @param  Builder<Entry>  $query
     * @return array<string, int|string>
     */
    private function entryTotals(Builder $query): array
    {
        return $this->normalizeTotals($query->selectRaw($this->sumSelects())->toBase()->first());
    }

    /**
     * @return array<string, int|string>
     */
    private function normalizeTotals(?object $row): array
    {
        $totals = ['entries' => (int) ($row->entries ?? 0)];

        foreach (self::ENTRY_SUMS as $column) {
            $totals[$column] = self::money($row->{$column} ?? 0);
        }

        return $totals;
    }

    private function sumSelects(): string
    {
        return collect(self::ENTRY_SUMS)
            ->map(fn (string $column): string => "COALESCE(SUM({$column}), 0) AS {$column}")
            ->prepend('COUNT(*) AS entries')
            ->implode(', ');
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    private function emptyPeriods(DateRange $range, string $grouping): array
    {
        $periods = [];
        $cursor = $this->periodStart($range->from, $grouping);

        while ($cursor->lte($range->until)) {
            $periods[$this->periodKey($cursor, $grouping)] = [
                'key' => $this->periodKey($cursor, $grouping),
                'label' => $this->periodLabel($cursor, $grouping, $range),
                'entries' => 0,
                ...array_fill_keys(self::ENTRY_SUMS, '0.00'),
                'collected_amount' => '0.00',
                'collected_profit' => '0.00',
            ];

            $cursor = match ($grouping) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow(),
                'year' => $cursor->addYear(),
                default => $cursor->addDay(),
            };
        }

        return $periods;
    }

    private function periodStart(CarbonImmutable $date, string $grouping): CarbonImmutable
    {
        return match ($grouping) {
            'week' => $date->startOfWeek(),
            'month' => $date->startOfMonth(),
            'year' => $date->startOfYear(),
            default => $date->startOfDay(),
        };
    }

    private function periodKey(CarbonImmutable $date, string $grouping): string
    {
        return $this->periodStart($date, $grouping)->toDateString();
    }

    private function periodLabel(CarbonImmutable $start, string $grouping, DateRange $range): string
    {
        return match ($grouping) {
            'week' => $start->max($range->from)->format('d-m').' – '.$start->endOfWeek()->min($range->until)->format('d-m-Y'),
            'month' => $start->format('M Y'),
            'year' => $start->format('Y'),
            default => $start->format('d-m-Y'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function groupings(): array
    {
        return [
            'day' => __('Daily'),
            'week' => __('Weekly'),
            'month' => __('Monthly'),
            'year' => __('Yearly'),
        ];
    }

    private static function money(mixed $value): string
    {
        return Money::fromPaise(Money::toPaise($value));
    }
}
