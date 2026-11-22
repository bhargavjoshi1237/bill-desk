<?php

namespace Tests\Feature;

use App\Filament\Widgets\AgingOverview;
use App\Filament\Widgets\BreakdownTable;
use App\Filament\Widgets\CollectionsTrendChart;
use App\Filament\Widgets\DayWiseTable;
use App\Filament\Widgets\OutstandingCustomersTable;
use App\Filament\Widgets\PeriodStatsOverview;
use App\Filament\Widgets\ProfitTrendChart;
use App\Filament\Widgets\VolumeByDimensionChart;
use App\Filament\Widgets\YesterdayOverview;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class WidgetsTest extends TestCase
{
    use RefreshDatabase;

    /** The client's sample rows are dated 25-10-2026. */
    private const FILTERS = ['preset' => 'custom', 'from' => '2026-10-01', 'until' => '2026-10-31'];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-25 12:00:00');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_stats_show_the_sheet_totals_to_admins(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->first());

        Livewire::test(PeriodStatsOverview::class, ['pageFilters' => self::FILTERS])
            ->assertSee('₹2,30,000.00')   // bill volume 1,00,000 + 50,000 + 80,000
            ->assertSee('₹5,750.00')      // service charge 2,500 + 1,250 + 2,000
            ->assertSee('₹91,250.00')     // collected 51,250 + 40,000
            ->assertSee('₹1,44,500.00')   // outstanding 1,02,500 + 42,000
            ->assertSee('₹2,200.00')      // booked profit 1,000 + 400 + 800
            ->assertSee('₹400.00');       // collected profit: only Amit is fully paid
    }

    public function test_operators_do_not_see_profit(): void
    {
        $this->actingAs(User::where('email', 'operator@example.com')->first());

        Livewire::test(PeriodStatsOverview::class, ['pageFilters' => self::FILTERS])
            ->assertSee('₹2,30,000.00')
            ->assertDontSee('Net profit')
            ->assertDontSee('₹2,200.00');

        $this->assertFalse(ProfitTrendChart::canView());

        Carbon::setTestNow('2026-10-26 10:00:00');

        Livewire::test(YesterdayOverview::class)
            ->assertSee('₹2,30,000.00')
            ->assertDontSee('₹2,200.00');

        Livewire::test(DayWiseTable::class, ['pageFilters' => self::FILTERS])
            ->assertSee('₹2,30,000.00')
            ->assertDontSee('₹2,200.00');
    }

    public function test_every_widget_renders(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->first());

        Livewire::test(CollectionsTrendChart::class, ['pageFilters' => self::FILTERS])->assertOk();
        Livewire::test(ProfitTrendChart::class, ['pageFilters' => self::FILTERS])->assertOk();
        Livewire::test(VolumeByDimensionChart::class, ['pageFilters' => self::FILTERS, 'dimension' => 'portal'])->assertOk();
        Livewire::test(VolumeByDimensionChart::class, ['pageFilters' => self::FILTERS, 'dimension' => 'bank'])->assertOk();
        Livewire::test(AgingOverview::class)->assertSee('₹1,44,500.00');

        foreach (['period', 'portal', 'bank', 'customer'] as $dimension) {
            Livewire::test(BreakdownTable::class, ['pageFilters' => [...self::FILTERS, 'grouping' => 'week'], 'dimension' => $dimension])
                ->assertOk()
                ->assertSee('₹2,30,000.00');
        }

        Livewire::test(OutstandingCustomersTable::class)
            ->assertSee('Rahul')
            ->assertSee('Rajesh')
            ->assertDontSee('Amit');
    }

    public function test_yesterday_shows_bills_paid_service_charge_and_profit(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->first());

        // On the entry date itself, yesterday had nothing.
        Livewire::test(YesterdayOverview::class)->assertSee('₹0.00')->assertDontSee('₹2,30,000.00');

        Carbon::setTestNow('2026-10-26 10:00:00');

        Livewire::test(YesterdayOverview::class)
            ->assertSee('25-10-2026')
            ->assertSee('₹2,30,000.00')   // bills paid
            ->assertSee('₹5,750.00')      // service charge
            ->assertSee('₹2,200.00');     // profit
    }

    public function test_day_wise_table_lists_each_day_with_a_total(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->first());

        Livewire::test(DayWiseTable::class, ['pageFilters' => self::FILTERS])
            ->assertSee('25-10-2026')
            ->assertDontSee('24-10-2026')   // days without entries are left out
            ->assertSee('₹2,30,000.00')
            ->assertSee('₹5,750.00')
            ->assertSee('₹2,200.00')
            ->assertSee('Total');
    }
}
