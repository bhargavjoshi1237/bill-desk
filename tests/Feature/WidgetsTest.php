<?php

namespace Tests\Feature;

use App\Filament\Widgets\AgingOverview;
use App\Filament\Widgets\BreakdownTable;
use App\Filament\Widgets\CollectionsTrendChart;
use App\Filament\Widgets\OutstandingCustomersTable;
use App\Filament\Widgets\OverdueEntriesTable;
use App\Filament\Widgets\PeriodStatsOverview;
use App\Filament\Widgets\ProfitTrendChart;
use App\Filament\Widgets\VolumeByDimensionChart;
use App\Filament\Widgets\WalletBalancesOverview;
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
        $this->assertFalse(WalletBalancesOverview::canView());
    }

    public function test_every_widget_renders(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->first());

        Livewire::test(CollectionsTrendChart::class, ['pageFilters' => self::FILTERS])->assertOk();
        Livewire::test(ProfitTrendChart::class, ['pageFilters' => self::FILTERS])->assertOk();
        Livewire::test(VolumeByDimensionChart::class, ['pageFilters' => self::FILTERS, 'dimension' => 'portal'])->assertOk();
        Livewire::test(VolumeByDimensionChart::class, ['pageFilters' => self::FILTERS, 'dimension' => 'bank'])->assertOk();
        Livewire::test(WalletBalancesOverview::class)->assertSee('₹3,17,300.00')->assertSee('₹1,49,150.00');
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

    public function test_overdue_table_lists_entries_past_their_due_date(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->first());

        Livewire::test(OverdueEntriesTable::class)->assertSee('Nothing overdue');

        // Due date is 01-11-2026; on the 5th, Rahul and Rajesh are overdue.
        Carbon::setTestNow('2026-11-05 10:00:00');

        Livewire::test(OverdueEntriesTable::class)
            ->assertSee('Rahul')
            ->assertSee('Rajesh')
            ->assertDontSee('Amit')
            ->assertSee('4 days overdue');
    }
}
