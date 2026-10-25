<?php

namespace Database\Factories;

use App\Enums\WalletTransactionType;
use App\Models\Portal;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portal_id' => Portal::factory(),
            'transaction_date' => today(),
            'type' => WalletTransactionType::TopUp,
            'amount' => 100000,
        ];
    }

    public function withdrawal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WalletTransactionType::Withdrawal,
        ]);
    }
}
