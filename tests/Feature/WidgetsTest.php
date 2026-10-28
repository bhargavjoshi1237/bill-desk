<?php

namespace Tests\Feature;

use App\Enums\PortalType;
use App\Filament\Widgets\AgingOverview;
use App\Filament\Widgets\BreakdownTable;
use App\Filament\Widgets\CollectionsTrendChart;
use App\Filament\Widgets\DailyCatchupOverview;
use App\Filament\Widgets\DailyCatchupTable;
use App\Filament\Widgets\OutstandingCustomersTable;
use App\Filament\Widgets\OverdueEntriesTable;
use App\Filament\Widgets\PeriodStatsOverview;
use App\Filament\Widgets\ProfitTrendChart;
use App\Filament\Widgets\VolumeByDimensionChart;
use App\Filament\Widgets\WalletBalancesOverview;
use App\Models\User;
use App\Reports\DailyCatchup;
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
            ->assertSee('₹5,750.00')      // service charge 2,500 + 1,250 + 2,000 (also the booked profit)
            ->assertSee('₹3,700.00')      // collected 2,100 + 1,600
            ->assertSee('₹5,600.00')      // outstanding fees 4,000 + 1,600
            ->assertSee('₹1,250.00');     // collected profit: only Amit is fully paid
    }

    public function test_operators_do_not_see_profit(): void
    {
        $this->actingAs(User::where('email', 'operator@example.com')->first());

        Livewire::test(PeriodStatsOverview::class, ['pageFilters' => self::FILTERS])
            ->assertSee('₹2,30,000.00')
            ->assertDontSee('Net profit')
            ->assertDontSee('₹1,250.00');

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
        Livewire::test(WalletBalancesOverview::class)
            ->assertSee('₹3,17,300.00')    // MOS: 5,00,000 − (1,00,000 + 1,500) − (80,000 + 1,200)
            ->assertSee('₹1,49,150.00')    // Digi Seva: 2,00,000 − (50,000 + 850)
            ->assertSee('₹3,30,000.00');   // Current Account: 1,00,000 + every bill amount received
        Livewire::test(AgingOverview::class)->assertSee('₹5,600.00');

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

    public function test_daily_catchup_shows_each_account_and_portal_balance(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->first());

        $rows = (new DailyCatchup(Carbon::parse('2026-10-25')))->rows()->keyBy('name');

        // Opening top-ups and the three sample entries all fall on 25-10-2026.
        $this->assertSame(['0.00', '100000.00', '230000.00', '0.00', '330000.00'], [
            $rows['Current Account']['opening'], $rows['Current Account']['top_ups'], $rows['Current Account']['received'],
            $rows['Current Account']['sent'], $rows['Current Account']['closing'],
        ]);
        $this->assertSame('182700.00', $rows['MOS']['sent']);   // (1,00,000 + 1,500) + (80,000 + 1,200)
        $this->assertSame('317300.00', $rows['MOS']['closing']);
        $this->assertSame(PortalType::BankAccount, $rows['Current Account']['type']);

        // The next day opens where the 25th closed.
        $next = (new DailyCatchup(Carbon::parse('2026-10-26')))->rows()->keyBy('name');
        $this->assertSame('317300.00', $next['MOS']['opening']);
        $this->assertSame('0.00', $next['MOS']['sent']);
        $this->assertSame('317300.00', $next['MOS']['closing']);

        $totals = (new DailyCatchup(Carbon::parse('2026-10-25')))->totals();
        $this->assertSame('796450.00', $totals['total']);
        $this->assertSame('330000.00', $totals['bank_accounts']);
        $this->assertSame('466450.00', $totals['portals']);
        $this->assertSame('3700.00', $totals['fees_collected']);

        Livewire::test(DailyCatchupOverview::class, ['pageFilters' => ['date' => '2026-10-25']])
            ->assertSee('₹7,96,450.00')
            ->assertSee('₹3,30,000.00')
            ->assertSee('₹4,66,450.00');

        Livewire::test(DailyCatchupTable::class, ['pageFilters' => ['date' => '2026-10-25']])
            ->assertSee('Current Account')
            ->assertSee('₹3,30,000.00')
            ->assertSee('₹3,17,300.00');

        // Before anything happened, every balance is zero.
        Livewire::test(DailyCatchupTable::class, ['pageFilters' => ['date' => '2026-10-24']])
            ->assertDontSee('₹3,17,300.00');
    }

    public function test_operators_cannot_see_the_daily_catchup(): void
    {
        $this->actingAs(User::where('email', 'operator@example.com')->first());

        $this->assertFalse(DailyCatchupOverview::canView());
        $this->assertFalse(DailyCatchupTable::canView());
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
