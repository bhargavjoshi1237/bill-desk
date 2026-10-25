<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\Portal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entry_date' => today(),
            'customer_id' => Customer::factory(),
            'bank_id' => Bank::factory(),
            'portal_id' => Portal::factory(),
            'bill_amount' => 100000,
            'portal_charge_percent' => 1.5,
            'service_charge_percent' => 2.5,
            'due_date' => today()->addDays(7),
        ];
    }
}
