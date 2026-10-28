<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Entry;
use App\Models\Payment;
use App\Models\Portal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sharedPages(): array
    {
        return [
            'dashboard' => ['/admin'],
            'entries' => ['/admin/entries'],
            'new entry' => ['/admin/entries/create'],
            'payments' => ['/admin/payments'],
            'new payment' => ['/admin/payments/create'],
            'customers' => ['/admin/customers'],
            'new customer' => ['/admin/customers/create'],
            'reports' => ['/admin/reports'],
            'outstanding' => ['/admin/outstanding'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function adminOnlyPages(): array
    {
        return [
            'daily catch-up' => ['/admin/daily-catchup'],
            'portals' => ['/admin/portals'],
            'new portal' => ['/admin/portals/create'],
            'wallet' => ['/admin/wallet-transactions'],
            'banks' => ['/admin/banks'],
            'users' => ['/admin/users'],
            'settings' => ['/admin/settings'],
            'activity log' => ['/admin/activity-log'],
        ];
    }

    #[DataProvider('sharedPages')]
    #[DataProvider('adminOnlyPages')]
    public function test_admin_can_open_every_page(string $url): void
    {
        $this->actingAs($this->admin())->get($url)->assertOk();
    }

    #[DataProvider('sharedPages')]
    public function test_operator_can_open_day_to_day_pages(string $url): void
    {
        $this->actingAs($this->operator())->get($url)->assertOk();
    }

    #[DataProvider('adminOnlyPages')]
    public function test_operator_cannot_open_admin_pages(string $url): void
    {
        $this->actingAs($this->operator())->get($url)->assertForbidden();
    }

    public function test_record_pages_open(): void
    {
        $entry = Entry::query()->firstOrFail();
        $customer = Customer::query()->firstOrFail();
        $portal = Portal::query()->firstOrFail();
        $payment = Payment::query()->firstOrFail();

        $this->actingAs($this->admin());

        foreach ([
            "/admin/entries/{$entry->id}",
            "/admin/entries/{$entry->id}/edit",
            "/admin/customers/{$customer->id}",
            "/admin/customers/{$customer->id}/edit",
            "/admin/portals/{$portal->id}",
            "/admin/portals/{$portal->id}/edit",
            "/admin/payments/{$payment->id}/edit",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/print/entries/1/receipt')->assertRedirect('/admin/login');
    }

    public function test_inactive_users_cannot_use_the_panel(): void
    {
        $this->actingAs(User::factory()->admin()->inactive()->create())->get('/admin')->assertForbidden();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    private function operator(): User
    {
        return User::query()->where('email', 'operator@example.com')->firstOrFail();
    }
}
