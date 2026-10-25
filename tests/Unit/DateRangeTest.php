<?php

namespace Tests\Unit;

use App\Support\DateRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-10-25 15:00:00'); // a Sunday
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_presets(): void
    {
        $this->assertRange('2026-10-25', '2026-10-25', DateRange::fromPreset('today'));
        $this->assertRange('2026-10-24', '2026-10-24', DateRange::fromPreset('yesterday'));
        $this->assertRange('2026-10-19', '2026-10-25', DateRange::fromPreset('this_week'));
        $this->assertRange('2026-10-12', '2026-10-18', DateRange::fromPreset('last_week'));
        $this->assertRange('2026-10-01', '2026-10-25', DateRange::fromPreset('this_month'));
        $this->assertRange('2026-09-01', '2026-09-30', DateRange::fromPreset('last_month'));
        $this->assertRange('2026-09-26', '2026-10-25', DateRange::fromPreset('last_30_days'));
        $this->assertRange('2026-10-01', '2026-10-25', DateRange::fromPreset('this_quarter'));
        $this->assertRange('2026-01-01', '2026-10-25', DateRange::fromPreset('this_year'));
        $this->assertRange('2026-04-01', '2026-10-25', DateRange::fromPreset('financial_year'));
        $this->assertRange('2026-10-01', '2026-10-25', DateRange::fromPreset(null));
    }

    public function test_financial_year_before_april_starts_the_previous_year(): void
    {
        CarbonImmutable::setTestNow('2027-02-10');

        $this->assertRange('2026-04-01', '2027-02-10', DateRange::fromPreset('financial_year'));
    }

    public function test_custom_range_and_swapped_dates(): void
    {
        $this->assertRange('2026-01-05', '2026-03-01', DateRange::fromFilters(['preset' => 'custom', 'from' => '2026-01-05', 'until' => '2026-03-01']));
        $this->assertRange('2026-01-05', '2026-03-01', DateRange::between('2026-03-01', '2026-01-05'));
    }

    public function test_suggested_grouping_and_label(): void
    {
        $this->assertSame('day', DateRange::fromPreset('this_month')->suggestedGrouping());
        $this->assertSame('month', DateRange::fromPreset('this_year')->suggestedGrouping());
        $this->assertSame('25-10-2026', DateRange::fromPreset('today')->label());
        $this->assertSame('01-10-2026 – 25-10-2026', DateRange::fromPreset('this_month')->label());
    }

    private function assertRange(string $from, string $until, DateRange $range): void
    {
        $this->assertSame([$from, $until], [$range->fromDate(), $range->untilDate()]);
    }
}
