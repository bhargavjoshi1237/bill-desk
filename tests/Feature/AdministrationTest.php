<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Banks\Pages\ManageBanks;
use App\Filament\Resources\Entries\Pages\CreateEntry;
use App\Filament\Resources\Portals\Pages\ListPortals;
use App\Filament\Resources\Portals\Pages\ViewPortal;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Bank;
use App\Models\Entry;
use App\Models\Portal;
use App\Models\User;
use App\Support\Settings;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_settings_are_saved_and_used_as_defaults_for_new_entries(): void
    {
        Livewire::test(ManageSettings::class)
            ->assertSchemaStateSet(['default_service_charge_percent' => 2.5])
            ->fillForm([
                'business_name' => 'Shree Bill Desk',
                'default_service_charge_percent' => 3,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Shree Bill Desk', Settings::businessName());
        $this->assertSame(3.0, Settings::serviceChargePercent());

        Livewire::test(CreateEntry::class)
            ->assertSchemaStateSet(['service_charge_percent' => 3.0]);
    }

    public function test_admin_creates_an_operator(): void
    {
        Livewire::test(ManageUsers::class)
            ->callAction('create', [
                'name' => 'Counter 1',
                'email' => 'counter1@example.com',
                'password' => 'secret-pass',
                'role' => UserRole::Operator->value,
                'locale' => 'hi',
                'is_active' => true,
            ])
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'counter1@example.com')->sole();
        $this->assertSame(UserRole::Operator, $user->role);
        $this->assertSame('hi', $user->locale);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
    }

    public function test_blank_password_on_edit_keeps_the_old_one(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($user), ['name' => 'Renamed', 'password' => ''])
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed', $user->refresh()->name);
        $this->assertSame($hash, $user->password);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $other = User::factory()->create();

        Livewire::test(ManageUsers::class)
            ->assertActionHidden(TestAction::make('delete')->table($this->admin))
            ->assertActionVisible(TestAction::make('delete')->table($other));
    }

    public function test_portals_have_no_wallet(): void
    {
        $portal = Portal::factory()->create(['name' => 'MOS', 'charge_percent' => 1.5]);
        Entry::factory()->create(['portal_id' => $portal->id, 'bill_amount' => 100000, 'portal_charge_percent' => 1.5]);

        Livewire::test(ListPortals::class)
            ->assertCanSeeTableRecords([$portal])
            ->assertActionDoesNotExist(TestAction::make('topUp')->table($portal))
            ->assertDontSee('Wallet balance');

        Livewire::test(ViewPortal::class, ['record' => $portal->id])
            ->assertActionDoesNotExist('topUp')
            ->assertDontSee('Current balance');

        $this->get('/admin/wallet-transactions')->assertNotFound();
    }

    public function test_banks_in_use_cannot_be_deleted(): void
    {
        $used = Bank::factory()->create();
        Entry::factory()->create(['bank_id' => $used->id]);
        $unused = Bank::factory()->create();

        Livewire::test(ManageBanks::class)
            ->assertActionHidden(TestAction::make('delete')->table($used))
            ->callAction(TestAction::make('delete')->table($unused));

        $this->assertModelMissing($unused);
    }

    public function test_changes_are_recorded_in_the_activity_log(): void
    {
        $entry = Entry::factory()->create(['bill_amount' => 1000]);
        $entry->update(['bill_amount' => 2000]);

        $activity = Activity::query()->where('subject_type', Entry::class)->where('event', 'updated')->sole();

        $this->assertTrue($activity->causer->is($this->admin));
        $this->assertSame('1000.00', $activity->attribute_changes['old']['bill_amount']);
        $this->assertSame('2000.00', $activity->attribute_changes['attributes']['bill_amount']);

        Livewire::test(ListActivities::class)
            ->assertCanSeeTableRecords([$activity])
            ->assertSee($entry->number());
    }

    public function test_sign_ins_are_logged(): void
    {
        auth()->logout();

        $user = User::factory()->create(['email' => 'op@example.com']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'op@example.com', 'password' => 'password'])
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Activity::query()->where('event', 'login')->where('causer_id', $user->id)->exists());
    }

    public function test_language_switch_translates_the_interface(): void
    {
        $this->get('/locale/hi')->assertRedirect();
        $this->assertSame('hi', $this->admin->refresh()->locale);

        $this->get('/admin/entries')->assertOk()->assertSee('प्रविष्टियाँ');

        $this->get('/locale/en');
        $this->get('/admin/entries')->assertOk()->assertSee('Entries');

        $this->get('/locale/xx')->assertNotFound();
    }

    public function test_operators_only_see_day_to_day_navigation(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Entries')
            ->assertSee('Customers')
            ->assertSee('Outstanding &amp; dues', false)
            ->assertDontSee('Activity log')
            ->assertDontSee('Wallet top-ups');
    }
}
