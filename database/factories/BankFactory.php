<?php

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Bank',
            'is_active' => true,
        ];
    }
}
