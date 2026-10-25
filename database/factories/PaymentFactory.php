<?php

namespace Database\Factories;

use App\Enums\PaymentMode;
use App\Models\Entry;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory(),
            'paid_on' => today(),
            'amount' => 1000,
            'mode' => PaymentMode::Cash,
        ];
    }
}
