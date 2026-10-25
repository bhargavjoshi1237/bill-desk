<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\Customer;
use App\Models\CustomerCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCard>
 */
class CustomerCardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'bank_id' => Bank::factory(),
            'last_four' => fake()->numerify('####'),
        ];
    }
}
