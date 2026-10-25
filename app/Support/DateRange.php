<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A from–until date range, built from a preset ("this_month") or explicit dates.
 */
final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $until,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return [
            'today' => __('Today'),
            'yesterday' => __('Yesterday'),
            'this_week' => __('This week'),
            'last_week' => __('Last week'),
            'this_month' => __('This month'),
            'last_month' => __('Last month'),
            'last_30_days' => __('Last 30 days'),
            'this_quarter' => __('This quarter'),
            'this_year' => __('This year'),
            'financial_year' => __('This financial year (Apr–Mar)'),
            'custom' => __('Custom range'),
        ];
    }

    public static function fromPreset(?string $preset, mixed $from = null, mixed $until = null): self
    {
        $today = CarbonImmutable::today();

        return match ($preset) {
            'today' => new self($today, $today),
            'yesterday' => new self($today->subDay(), $today->subDay()),
            'this_week' => new self($today->startOfWeek(), $today),
            'last_week' => new self($today->subWeek()->startOfWeek(), $today->subWeek()->endOfWeek()->startOfDay()),
            'last_month' => new self($today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()),
            'last_30_days' => new self($today->subDays(29), $today),
            'this_quarter' => new self($today->startOfQuarter(), $today),
            'this_year' => new self($today->startOfYear(), $today),
            'financial_year' => new self(self::financialYearStart($today), $today),
            'custom' => self::between($from, $until),
            default => new self($today->startOfMonth(), $today),
        };
    }

    /**
     * Build a range from the filters array used by dashboards and reports.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function fromFilters(?array $filters, string $defaultPreset = 'this_month'): self
    {
        return self::fromPreset(
            $filters['preset'] ?? $defaultPreset,
            $filters['from'] ?? null,
            $filters['until'] ?? null,
        );
    }

    public static function between(mixed $from, mixed $until): self
    {
        $today = CarbonImmutable::today();
        $from = filled($from) ? CarbonImmutable::parse($from)->startOfDay() : $today->startOfMonth();
        $until = filled($until) ? CarbonImmutable::parse($until)->startOfDay() : $today;

        return $from->gt($until) ? new self($until, $from) : new self($from, $until);
    }

    private static function financialYearStart(CarbonImmutable $date): CarbonImmutable
    {
        $year = $date->month >= 4 ? $date->year : $date->year - 1;

        return CarbonImmutable::create($year, 4, 1);
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->until) + 1;
    }

    /**
     * The grouping that gives a readable number of chart points.
     */
    public function suggestedGrouping(): string
    {
        return match (true) {
            $this->days() <= 45 => 'day',
            $this->days() <= 120 => 'week',
            $this->days() <= 800 => 'month',
            default => 'year',
        };
    }

    public function label(): string
    {
        if ($this->from->isSameDay($this->until)) {
            return $this->from->format('d-m-Y');
        }

        return $this->from->format('d-m-Y').' – '.$this->until->format('d-m-Y');
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function untilDate(): string
    {
        return $this->until->toDateString();
    }
}
