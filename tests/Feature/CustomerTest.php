<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\Payment;
use App\Models\User;
use App\Support\WhatsApp;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_customer_is_created_with_cards_and_a_normalised_mobile(): void
    {
        $bank = Bank::factory()->create(['name' => 'HDFC Bank']);

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Amit',
                'mobile' => '+91 98765-43210',
                'city' => 'Ahmedabad',
                'pan_number' => 'abcde1234f',
                'aadhaar_number' => '1234 5678 9012',
                'cards' => [
                    ['bank_id' => $bank->id, 'last_four' => '8765', 'network' => 'visa'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $customer = Customer::query()->sole();
        $this->assertSame('9876543210', $customer->mobile);
        $this->assertSame('ABCDE1234F', $customer->pan_number);
        $this->assertSame('123456789012', $customer->aadhaar_number);
        $this->assertSame('XXXX XXXX 9012', $customer->maskedAadhaar());
        $this->assertSame('HDFC Bank •••• 8765 (Visa)', $customer->cards()->sole()->label());
    }

    public function test_duplicate_mobile_is_rejected_even_when_formatted_differently(): void
    {
        Customer::factory()->create(['name' => 'Rahul', 'mobile' => '9876500001']);

        Livewire::test(CreateCustomer::class)
            ->fillForm(['name' => 'Someone', 'mobile' => '09876500001'])
            ->call('create')
            ->assertHasFormErrors(['mobile']);

        $this->assertSame(1, Customer::query()->count());
    }

    public function test_invalid_details_are_rejected(): void
    {
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'X',
                'mobile' => '12345',
                'pan_number' => 'BAD',
                'aadhaar_number' => '1234',
                'cards' => [['bank_id' => Bank::factory()->create()->id, 'last_four' => '12a4']],
            ])
            ->call('create')
            ->assertHasFormErrors(['mobile', 'pan_number', 'aadhaar_number', 'cards.0.last_four']);
    }

    public function test_editing_keeps_own_mobile_valid(): void
    {
        $customer = Customer::factory()->create(['mobile' => '9876500001']);

        Livewire::test(EditCustomer::class, ['record' => $customer->id])
            ->fillForm(['city' => 'Surat'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Surat', $customer->refresh()->city);
    }

    public function test_id_proof_files_are_stored_privately(): void
    {
        Storage::fake('local');

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Rajesh',
                'mobile' => '9876500003',
                'id_proof_files' => [UploadedFile::fake()->create('aadhaar.pdf', 120, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $files = Customer::query()->sole()->id_proof_files;
        $this->assertCount(1, $files);
        $this->assertStringStartsWith('id-proofs/', $files[0]);
        Storage::disk('local')->assertExists($files[0]);
    }

    public function test_customer_with_entries_cannot_be_deleted(): void
    {
        $withEntries = Customer::factory()->create();
        Entry::factory()->create(['customer_id' => $withEntries->id]);
        $empty = Customer::factory()->create();

        Livewire::test(ViewCustomer::class, ['record' => $withEntries->id])->assertActionHidden(DeleteAction::class);
        Livewire::test(ViewCustomer::class, ['record' => $empty->id])->callAction(DeleteAction::class);

        $this->assertModelExists($withEntries);
        $this->assertModelMissing($empty);
    }

    public function test_list_shows_outstanding_and_filters_customers_who_owe(): void
    {
        $owing = Customer::factory()->create(['name' => 'Owing']);
        Entry::factory()->create(['customer_id' => $owing->id, 'bill_amount' => 80000]);

        $settled = Customer::factory()->create(['name' => 'Settled']);
        $entry = Entry::factory()->create(['customer_id' => $settled->id, 'bill_amount' => 1000]);
        Payment::factory()->create(['entry_id' => $entry->id, 'amount' => 40]);   // 15 portal + 25 service

        Livewire::test(ListCustomers::class)
            ->assertSee('₹3,200.00')   // 1,200 portal + 2,000 service
            ->filterTable('owes_money', true)
            ->assertCanSeeTableRecords([$owing])
            ->assertCanNotSeeTableRecords([$settled]);
    }

    public function test_whatsapp_reminder_link_contains_the_dues_message(): void
    {
        $customer = Customer::factory()->create(['name' => 'Rajesh', 'mobile' => '9876500003']);
        Entry::factory()->create(['customer_id' => $customer->id, 'bill_amount' => 80000]);

        $url = WhatsApp::reminderUrl($customer);

        $this->assertStringStartsWith('https://wa.me/919876500003?text=', $url);
        $this->assertStringContainsString(rawurlencode('Namaste Rajesh'), $url);
        $this->assertStringContainsString(rawurlencode('₹3,200.00'), $url);

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->assertActionVisible('whatsappReminder')
            ->assertActionHasUrl('whatsappReminder', $url);
    }

    public function test_statement_action_downloads_a_pdf(): void
    {
        $customer = Customer::factory()->create();
        Entry::factory()->create(['customer_id' => $customer->id, 'entry_date' => today()->subDays(10)]);

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->callAction('statement', ['from' => today()->subMonth()->toDateString(), 'until' => today()->toDateString()])
            ->assertHasNoFormErrors()
            ->assertFileDownloaded();
    }

    public function test_operators_cannot_delete_customers(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create());

        Livewire::test(ListCustomers::class)->assertActionHidden(TestAction::make('delete')->table($customer));
    }
}
