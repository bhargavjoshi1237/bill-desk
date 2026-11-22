<?php

namespace Database\Seeders;

use App\Enums\PaymentMode;
use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\Portal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Masters (banks, portals), two logins, and the three sample rows from the
 * client's "Portal Accounting Sheet" so reports have data from day one.
 *
 * Model events must stay on: they compute every entry's totals.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin',
            'password' => 'password',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        User::query()->updateOrCreate(['email' => 'operator@example.com'], [
            'name' => 'Operator',
            'password' => 'password',
            'role' => UserRole::Operator,
            'is_active' => true,
        ]);

        $banks = collect([
            'Axis Bank', 'HDFC Bank', 'ICICI Bank', 'SBI Card', 'Kotak Mahindra Bank', 'IndusInd Bank',
            'Yes Bank', 'RBL Bank', 'IDFC FIRST Bank', 'AU Small Finance Bank', 'Bank of Baroda (BOBCARD)',
            'American Express', 'HSBC', 'Standard Chartered', 'Federal Bank', 'Punjab National Bank',
        ])->mapWithKeys(fn (string $name): array => [$name => Bank::query()->firstOrCreate(['name' => $name])]);

        $portals = collect([
            ['name' => 'MOS', 'charge_percent' => 1.5],
            ['name' => 'Digi Seva', 'charge_percent' => 1.7],
        ])->mapWithKeys(fn (array $portal): array => [
            $portal['name'] => Portal::query()->firstOrCreate(['name' => $portal['name']], [...$portal, 'low_balance_alert' => 50000]),
        ]);

        if (Entry::query()->exists()) {
            return;
        }

        $date = Carbon::create(2026, 10, 25);

        foreach (['MOS' => 500000, 'Digi Seva' => 200000] as $portal => $amount) {
            $portals[$portal]->walletTransactions()->create([
                'transaction_date' => $date,
                'type' => WalletTransactionType::TopUp,
                'amount' => $amount,
                'notes' => 'Opening balance (sample data)',
                'created_by' => $admin->id,
            ]);
        }

        // Date, customer, mobile, bank, card, bill, portal, portal %, amount received.
        $rows = [
            ['Rahul', '9876500001', 'Axis Bank', '4321', 100000, 'MOS', 1.5, 0],
            ['Amit', '9876500002', 'HDFC Bank', '8765', 50000, 'Digi Seva', 1.7, 51250],
            ['Rajesh', '9876500003', 'ICICI Bank', '1122', 80000, 'MOS', 1.5, 40000],
        ];

        foreach ($rows as [$name, $mobile, $bank, $lastFour, $bill, $portal, $portalPercent, $received]) {
            $customer = Customer::query()->create(['name' => $name, 'mobile' => $mobile, 'notes' => 'Sample customer']);
            $card = $customer->cards()->create(['bank_id' => $banks[$bank]->id, 'last_four' => $lastFour]);

            $entry = Entry::query()->create([
                'entry_date' => $date,
                'customer_id' => $customer->id,
                'customer_card_id' => $card->id,
                'bank_id' => $banks[$bank]->id,
                'portal_id' => $portals[$portal]->id,
                'bill_amount' => $bill,
                'portal_charge_percent' => $portalPercent,
                'service_charge_percent' => 2.5,
                'due_date' => $date->copy()->addDays(7),
                'created_by' => $admin->id,
            ]);

            if ($received > 0) {
                $entry->payments()->create([
                    'paid_on' => $date,
                    'amount' => $received,
                    'mode' => PaymentMode::Cash,
                    'received_by' => $admin->id,
                ]);
            }
        }
    }
}
