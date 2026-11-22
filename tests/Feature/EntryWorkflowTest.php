<?php

namespace Tests\Feature;

use App\Enums\EntryStatus;
use App\Enums\PaymentMode;
use App\Filament\Resources\Entries\Pages\CreateEntry;
use App\Filament\Resources\Entries\Pages\EditEntry;
use App\Filament\Resources\Entries\Pages\ListEntries;
use App\Filament\Resources\Entries\Pages\ViewEntry;
use App\Filament\Resources\Entries\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\EditPayment;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\CustomerCard;
use App\Models\Entry;
use App\Models\Payment;
use App\Models\Portal;
use App\Models\User;
use App\Models\WalletTransaction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EntryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Portal $mos;

    private Customer $customer;

    private CustomerCard $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->mos = Portal::factory()->create(['name' => 'MOS', 'charge_percent' => 1.5]);
        $this->customer = Customer::factory()->create(['name' => 'Rajesh']);
        $this->card = CustomerCard::factory()->create([
            'customer_id' => $this->customer->id,
            'bank_id' => Bank::factory()->create(['name' => 'ICICI Bank'])->id,
            'last_four' => '1122',
        ]);

        $this->actingAs($this->admin);
    }

    public function test_creating_an_entry_with_a_partial_payment_matches_the_sheet(): void
    {
        Livewire::test(CreateEntry::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'entry_date' => '2026-10-25',
                'bill_amount' => 80000,
                'portal_id' => $this->mos->id,
            ])
            // Picking the customer selects their only card and its bank; picking the portal fills its rate.
            ->assertSchemaStateSet([
                'customer_card_id' => $this->card->id,
                'bank_id' => $this->card->bank_id,
                'portal_charge_percent' => 1.5,
                'service_charge_percent' => 2.5,
            ])
            ->fillForm(['initial_payment' => ['amount' => 40000, 'mode' => 'upi', 'reference' => 'UTR123']])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = Entry::query()->sole();

        $this->assertSame('1200.00', $entry->portal_deduction);
        $this->assertSame('2000.00', $entry->service_charge);
        $this->assertSame('82000.00', $entry->total_due);
        $this->assertSame('40000.00', $entry->amount_received);
        $this->assertSame('42000.00', $entry->outstanding);
        $this->assertSame('800.00', $entry->net_profit);
        $this->assertSame(EntryStatus::Partial, $entry->status);
        $this->assertSame('2026-11-01', $entry->due_date->toDateString());
        $this->assertSame($this->admin->id, $entry->created_by);

        $payment = $entry->payments()->sole();
        $this->assertSame(PaymentMode::Upi, $payment->mode);
        $this->assertSame('UTR123', $payment->reference);
        $this->assertSame('2026-10-25', $payment->paid_on->toDateString());
    }

    public function test_entry_with_no_payment_is_unpaid(): void
    {
        Livewire::test(CreateEntry::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'bill_amount' => 100000,
                'portal_id' => $this->mos->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = Entry::query()->sole();
        $this->assertSame(EntryStatus::Unpaid, $entry->status);
        $this->assertSame('102500.00', $entry->outstanding);
        $this->assertCount(0, $entry->payments);
    }

    public function test_initial_payment_cannot_exceed_total_due(): void
    {
        Livewire::test(CreateEntry::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'bill_amount' => 50000,
                'portal_id' => $this->mos->id,
                'initial_payment' => ['amount' => 51250.01],
            ])
            ->call('create')
            ->assertHasFormErrors(['initial_payment.amount']);

        $this->assertSame(0, Entry::query()->count());
    }

    public function test_required_fields_are_validated(): void
    {
        Livewire::test(CreateEntry::class)
            ->fillForm(['service_charge_percent' => null])
            ->call('create')
            ->assertHasFormErrors([
                'customer_id' => 'required',
                'bank_id' => 'required',
                'bill_amount' => 'required',
                'portal_id' => 'required',
                'service_charge_percent' => 'required',
            ]);
    }

    public function test_per_entry_rates_can_override_the_defaults(): void
    {
        Livewire::test(CreateEntry::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'bill_amount' => 33333,
                'portal_id' => $this->mos->id,
            ])
            ->fillForm(['portal_charge_percent' => 1.7, 'service_charge_percent' => 2])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = Entry::query()->sole();
        $this->assertSame('566.66', $entry->portal_deduction);   // 33,333 × 1.7% = 566.661
        $this->assertSame('666.66', $entry->service_charge);     // 33,333 × 2% = 666.66
        $this->assertSame('100.00', $entry->net_profit);
    }

    public function test_new_entry_page_preselects_customer_from_the_url(): void
    {
        Livewire::withQueryParams(['customer' => $this->customer->id])
            ->test(CreateEntry::class)
            ->assertSchemaStateSet(['customer_id' => $this->customer->id]);
    }

    public function test_editing_bill_below_amount_already_received_is_rejected(): void
    {
        $entry = $this->entry(bill: 50000);
        Payment::factory()->create(['entry_id' => $entry->id, 'amount' => 30000]);

        Livewire::test(EditEntry::class, ['record' => $entry->id])
            ->fillForm(['bill_amount' => 20000])
            ->call('save')
            ->assertHasFormErrors(['bill_amount']);

        Livewire::test(EditEntry::class, ['record' => $entry->id])
            ->fillForm(['bill_amount' => 40000])
            ->call('save')
            ->assertHasNoFormErrors();

        $entry->refresh();
        $this->assertSame('41000.00', $entry->total_due);
        $this->assertSame('11000.00', $entry->outstanding);
        $this->assertSame(EntryStatus::Partial, $entry->status);
    }

    public function test_record_payment_action_from_the_entries_table(): void
    {
        $entry = $this->entry(bill: 80000);

        Livewire::test(ListEntries::class)
            ->callAction(TestAction::make('recordPayment')->table($entry), [
                'paid_on' => today()->toDateString(),
                'amount' => 82000,
                'mode' => 'cash',
            ])
            ->assertHasNoFormErrors();

        $entry->refresh();
        $this->assertSame(EntryStatus::Paid, $entry->status);
        $this->assertSame('0.00', $entry->outstanding);
        $this->assertSame(today()->toDateString(), $entry->paid_at->toDateString());

        // Nothing left to collect, so the action disappears.
        Livewire::test(ListEntries::class)->assertActionHidden(TestAction::make('recordPayment')->table($entry));
    }

    public function test_payment_more_than_outstanding_is_rejected(): void
    {
        $entry = $this->entry(bill: 80000);

        Livewire::test(ViewEntry::class, ['record' => $entry->id])
            ->callAction('recordPayment', ['paid_on' => today()->toDateString(), 'amount' => 82000.01, 'mode' => 'cash'])
            ->assertHasFormErrors(['amount']);

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_payments_relation_manager_adds_and_removes_payments(): void
    {
        $entry = $this->entry(bill: 80000);

        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => ViewEntry::class])
            ->callAction(TestAction::make('create')->table(), ['paid_on' => today()->toDateString(), 'amount' => 30000, 'mode' => 'upi'])
            ->assertHasNoFormErrors()
            ->assertDispatched('entry-payments-changed');

        $this->assertSame('52000.00', $entry->refresh()->outstanding);

        $payment = $entry->payments()->sole();

        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => ViewEntry::class])
            ->callAction(TestAction::make('delete')->table($payment));

        $this->assertSame('82000.00', $entry->refresh()->outstanding);
        $this->assertSame(EntryStatus::Unpaid, $entry->status);
    }

    public function test_standalone_payment_form_limits_amount_to_outstanding(): void
    {
        $entry = $this->entry(bill: 80000);

        Livewire::test(CreatePayment::class)
            ->fillForm(['entry_id' => $entry->id])
            ->assertSchemaStateSet(['amount' => '82000.00'])
            ->fillForm(['amount' => 90000])
            ->call('create')
            ->assertHasFormErrors(['amount']);

        Livewire::test(CreatePayment::class)
            ->fillForm(['entry_id' => $entry->id, 'amount' => 2000, 'mode' => 'cheque'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('80000.00', $entry->refresh()->outstanding);
    }

    public function test_editing_a_payment_allows_its_own_amount_again(): void
    {
        $entry = $this->entry(bill: 80000);
        $payment = Payment::factory()->create(['entry_id' => $entry->id, 'amount' => 82000]);

        // The entry is fully paid, but editing this payment may keep (or lower) its amount.
        Livewire::test(EditPayment::class, ['record' => $payment->id])
            ->fillForm(['amount' => 82000])
            ->call('save')
            ->assertHasNoFormErrors()
            ->fillForm(['amount' => 82000.5])
            ->call('save')
            ->assertHasFormErrors(['amount']);

        Livewire::test(EditPayment::class, ['record' => $payment->id])
            ->fillForm(['amount' => 50000])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('32000.00', $entry->refresh()->outstanding);
    }

    public function test_moving_a_payment_to_another_entry_retotals_both(): void
    {
        $first = $this->entry(bill: 10000);
        $second = $this->entry(bill: 20000);
        $payment = Payment::factory()->create(['entry_id' => $first->id, 'amount' => 5000]);

        $payment->update(['entry_id' => $second->id]);

        $this->assertSame('10250.00', $first->refresh()->outstanding);
        $this->assertSame('15500.00', $second->refresh()->outstanding);
    }

    public function test_status_tabs_filter_the_list(): void
    {
        $unpaid = $this->entry(bill: 1000);
        $paid = $this->entry(bill: 2000);
        Payment::factory()->create(['entry_id' => $paid->id, 'amount' => 2050]);
        $overdue = $this->entry(bill: 3000, attributes: ['entry_date' => today()->subDays(20), 'due_date' => today()->subDays(3)]);

        Livewire::test(ListEntries::class)
            ->set('activeTab', 'paid')
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$unpaid, $overdue])
            ->set('activeTab', 'overdue')
            ->assertCanSeeTableRecords([$overdue])
            ->assertCanNotSeeTableRecords([$unpaid, $paid]);
    }

    public function test_status_filter_supports_overdue(): void
    {
        $current = $this->entry(bill: 1000);
        $overdue = $this->entry(bill: 3000, attributes: ['entry_date' => today()->subDays(20), 'due_date' => today()->subDays(3)]);

        Livewire::test(ListEntries::class)
            ->filterTable('status', 'overdue')
            ->assertCanSeeTableRecords([$overdue])
            ->assertCanNotSeeTableRecords([$current]);
    }

    public function test_wallet_warning_after_an_entry_that_overdraws_the_portal(): void
    {
        WalletTransaction::factory()->create(['portal_id' => $this->mos->id, 'amount' => 50000]);

        Livewire::test(CreateEntry::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'bill_amount' => 80000,
                'portal_id' => $this->mos->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Portal wallet balance is negative');

        $this->assertSame('-31200.00', $this->mos->walletBalance());   // 50,000 − (80,000 + 1,200)
    }

    public function test_operators_cannot_delete_entries_but_admins_can(): void
    {
        $entry = $this->entry(bill: 1000);

        $this->actingAs(User::factory()->create());
        Livewire::test(EditEntry::class, ['record' => $entry->id])->assertActionHidden(DeleteAction::class);

        $this->actingAs($this->admin);
        Livewire::test(EditEntry::class, ['record' => $entry->id])->callAction(DeleteAction::class);

        $this->assertModelMissing($entry);
    }

    public function test_operators_do_not_see_the_profit_column(): void
    {
        $this->entry(bill: 1000);

        Livewire::test(ListEntries::class)->assertTableColumnVisible('net_profit');

        $this->actingAs(User::factory()->create());
        Livewire::test(ListEntries::class)->assertTableColumnHidden('net_profit');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entry(int $bill, array $attributes = []): Entry
    {
        return Entry::factory()->create([
            'customer_id' => $this->customer->id,
            'customer_card_id' => $this->card->id,
            'bank_id' => $this->card->bank_id,
            'portal_id' => $this->mos->id,
            'bill_amount' => $bill,
            'portal_charge_percent' => 1.5,
            ...$attributes,
        ]);
    }
}
