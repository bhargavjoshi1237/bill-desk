<?php

namespace Tests\Feature;

use App\Exports\OutstandingExport;
use App\Exports\ReportExport;
use App\Exports\Spreadsheet;
use App\Filament\Pages\Outstanding;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Entries\Pages\ListEntries;
use App\Filament\Resources\Entries\Tables\EntriesTable;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\Payment;
use App\Models\Portal;
use App\Models\User;
use App\Reports\CustomerStatement;
use App\Reports\ReportService;
use App\Support\DateRange;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-25 12:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($this->admin);
    }

    public function test_summary_matches_the_client_sheet(): void
    {
        $summary = app(ReportService::class)->summary(DateRange::fromPreset('today'));

        $this->assertSame(3, $summary['entries']);
        $this->assertSame('230000.00', $summary['bill_amount']);
        $this->assertSame('3550.00', $summary['portal_deduction']);   // 1,500 + 850 + 1,200
        $this->assertSame('5750.00', $summary['service_charge']);
        $this->assertSame('235750.00', $summary['total_due']);
        $this->assertSame('91250.00', $summary['amount_received']);
        $this->assertSame('144500.00', $summary['outstanding']);       // 1,02,500 + 0 + 42,000
        $this->assertSame('2200.00', $summary['net_profit']);          // 1,000 + 400 + 800
        $this->assertSame('91250.00', $summary['collected_amount']);
        $this->assertSame('400.00', $summary['collected_profit']);     // only Amit is fully paid
    }

    public function test_collected_figures_follow_the_payment_date(): void
    {
        // Rajesh pays the remaining 42,000 a week later.
        $rajesh = Entry::whereHas('customer', fn ($q) => $q->where('name', 'Rajesh'))->first();
        Payment::factory()->create(['entry_id' => $rajesh->id, 'amount' => 42000, 'paid_on' => '2026-11-01']);

        $reports = app(ReportService::class);
        $october = $reports->summary(DateRange::between('2026-10-01', '2026-10-31'));
        $november = $reports->summary(DateRange::between('2026-11-01', '2026-11-30'));

        // Booked profit stays in October; Rajesh's 800 is collected in November.
        $this->assertSame('2200.00', $october['net_profit']);
        $this->assertSame('400.00', $october['collected_profit']);
        $this->assertSame('0.00', $november['net_profit']);
        $this->assertSame('800.00', $november['collected_profit']);
        $this->assertSame('42000.00', $november['collected_amount']);
        $this->assertSame('2026-11-01', $rajesh->refresh()->paid_at->toDateString());
    }

    public function test_period_breakdown_includes_empty_periods(): void
    {
        Entry::factory()->create(['entry_date' => '2026-10-03', 'bill_amount' => 10000]);

        $weekly = app(ReportService::class)->byPeriod(DateRange::between('2026-10-01', '2026-10-31'), 'week');

        $this->assertSame(['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'], $weekly->keys()->all());
        $this->assertSame('10000.00', $weekly['2026-09-28']['bill_amount']);
        $this->assertSame('01-10 – 04-10-2026', $weekly['2026-09-28']['label']);
        $this->assertSame(0, $weekly['2026-10-05']['entries']);
        $this->assertSame('230000.00', $weekly['2026-10-19']['bill_amount']);

        $daily = app(ReportService::class)->byPeriod(DateRange::between('2026-10-20', '2026-10-25'), 'day');
        $this->assertCount(6, $daily);
        $this->assertSame('91250.00', $daily['2026-10-25']['collected_amount']);
    }

    public function test_breakdowns_by_portal_bank_and_customer(): void
    {
        $reports = app(ReportService::class);
        $range = DateRange::fromPreset('today');

        $portals = $reports->byPortal($range)->keyBy('name');
        $this->assertSame('180000.00', $portals['MOS']['bill_amount']);
        $this->assertSame('1800.00', $portals['MOS']['net_profit']);
        $this->assertSame('400.00', $portals['Digi Seva']['net_profit']);

        $this->assertSame(['Axis Bank', 'ICICI Bank', 'HDFC Bank'], $reports->byBank($range)->pluck('name')->all());
        $this->assertSame(['Rahul', 'Rajesh'], $reports->byCustomer($range, 2)->pluck('name')->all());
    }

    public function test_aging_buckets(): void
    {
        Entry::factory()->create(['entry_date' => '2026-10-10', 'bill_amount' => 1000]);   // 15 days old
        Entry::factory()->create(['entry_date' => '2026-09-01', 'bill_amount' => 2000]);   // 54 days old

        $aging = app(ReportService::class)->aging();

        $this->assertSame(['entries' => 2, 'outstanding' => '144500.00'], array_intersect_key($aging['0_7'], ['entries' => 0, 'outstanding' => 0]));
        $this->assertSame('1025.00', $aging['8_30']['outstanding']);
        $this->assertSame('2050.00', $aging['31_plus']['outstanding']);
    }

    public function test_customer_statement_running_balance(): void
    {
        $rajesh = Customer::where('name', 'Rajesh')->first();
        $old = Entry::factory()->create(['customer_id' => $rajesh->id, 'entry_date' => '2026-09-10', 'bill_amount' => 10000]);
        Payment::factory()->create(['entry_id' => $old->id, 'paid_on' => '2026-09-15', 'amount' => 4000]);
        Payment::factory()->create(['entry_id' => $old->id, 'paid_on' => '2026-10-26', 'amount' => 1000]);

        $statement = (new CustomerStatement($rajesh, DateRange::between('2026-10-01', '2026-10-31')))->build();

        // Opening: 10,250 due − 4,000 paid in September.
        $this->assertSame('6250.00', $statement['opening']);
        $this->assertSame('82000.00', $statement['debits']);
        $this->assertSame('41000.00', $statement['credits']);
        $this->assertSame('47250.00', $statement['closing']);
        $this->assertSame(['E-00003', 'R-00002', 'R-00004'], $statement['lines']->pluck('number')->all());
        $this->assertSame(['88250.00', '48250.00', '47250.00'], $statement['lines']->pluck('balance')->all());
        $this->assertSame('47250.00', $rajesh->totalOutstanding());
    }

    public function test_report_spreadsheet_has_every_sheet(): void
    {
        $path = $this->writeWorkbook(ReportExport::sheets(DateRange::fromPreset('today'), 'day', includeProfit: true));
        $sheets = $this->readWorkbook($path);

        $this->assertSame(['Summary', 'Daily', 'By portal', 'By bank', 'By customer'], array_keys($sheets));
        $this->assertContains(['Bill volume', 230000], $sheets['Summary']);
        $this->assertContains(['Net profit (booked)', 2200], $sheets['Summary']);
        $this->assertSame(['Portal', 'Entries', 'Bill amount', 'Portal deduction', 'Service charge', 'Total due', 'Received', 'Outstanding', 'Net profit'], $sheets['By portal'][0]);
    }

    public function test_report_spreadsheet_hides_profit_from_operators(): void
    {
        $sheets = $this->readWorkbook($this->writeWorkbook(ReportExport::sheets(DateRange::fromPreset('today'), null, includeProfit: false)));

        $this->assertNotContains('Net profit', $sheets['By portal'][0]);
        $this->assertEmpty(array_filter($sheets['Summary'], fn (array $row): bool => str_contains((string) $row[0], 'profit')));
    }

    public function test_outstanding_spreadsheet(): void
    {
        $sheets = $this->readWorkbook($this->writeWorkbook(OutstandingExport::sheets()));

        $this->assertSame(['Customers', 'Open entries', 'Aging'], array_keys($sheets));
        $this->assertCount(3, $sheets['Customers']);   // heading + Rahul + Rajesh
        $this->assertSame('Rahul', $sheets['Customers'][1][0]);
        $this->assertSame(102500, $sheets['Customers'][1][3]);
    }

    public function test_report_page_exports_download(): void
    {
        Livewire::test(Reports::class)
            ->set('filters', ['preset' => 'this_month', 'grouping' => 'week'])
            ->callAction('excel')
            ->assertFileDownloaded('report-2026-10-01-to-2026-10-25.xlsx');

        Livewire::test(Reports::class)->callAction('pdf')->assertFileDownloaded();
        Livewire::test(Outstanding::class)->callAction('excel')->assertFileDownloaded('outstanding-2026-10-25.xlsx');
        Livewire::test(Outstanding::class)->callAction('pdf')->assertFileDownloaded('outstanding-2026-10-25.pdf');
    }

    public function test_table_exports_respect_filters(): void
    {
        Livewire::test(ListEntries::class)
            ->filterTable('portal_id', [Portal::where('name', 'MOS')->value('id')])
            ->callAction(TestAction::make('export_xlsx')->table())
            ->assertFileDownloaded();

        Livewire::test(ListPayments::class)
            ->callAction(TestAction::make('export_csv')->table())
            ->assertFileDownloaded();
    }

    public function test_entry_columns_export_without_profit_for_operators(): void
    {
        $this->assertArrayHasKey('Net profit', EntriesTable::exportColumns());

        $this->actingAs(User::where('email', 'operator@example.com')->first());
        $this->assertArrayNotHasKey('Net profit', EntriesTable::exportColumns());
    }

    public function test_receipts_and_statements_render_as_pdf(): void
    {
        $entry = Entry::whereHas('customer', fn ($q) => $q->where('name', 'Rajesh'))->first();
        $payment = $entry->payments()->first();

        foreach ([
            route('print.entry-receipt', $entry),
            route('print.payment-receipt', $payment),
            route('print.customer-statement', ['customer' => $entry->customer_id, 'from' => '2026-10-01', 'until' => '2026-10-31']),
        ] as $url) {
            $response = $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_pdfs_stay_in_english_for_hindi_users(): void
    {
        $this->admin->update(['locale' => 'hi']);
        $entry = Entry::first();

        $html = view('pdf.entry-receipt', ['entry' => $entry->load(['customer', 'bank', 'portal', 'card', 'payments', 'creator']), 'business' => Settings::all(), 'generatedAt' => now()])->render();
        $this->assertStringContainsString('Total payable', $html);

        $this->get(route('print.entry-receipt', $entry))->assertOk();
        $this->assertSame('hi', app()->getLocale());
    }

    /**
     * @param  array<string, array{headings: array<string>, rows: iterable<array<mixed>>}>  $sheets
     */
    private function writeWorkbook(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        Spreadsheet::write($sheets, 'xlsx', $path);

        return $path;
    }

    /**
     * @return array<string, array<int, array<mixed>>>
     */
    private function readWorkbook(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }

        $reader->close();
        unlink($path);

        return $sheets;
    }
}
