<?php

namespace Database\Factories;

use App\Models\Portal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Portal>
 */
class PortalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Portal',
            'charge_percent' => fake()->randomElement([1.5, 1.7, 1.2]),
            'low_balance_alert' => null,
            'is_active' => true,
        ];
    }
}
