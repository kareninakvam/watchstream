<?php

namespace Database\Factories;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LedgerEntry> */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'amount' => 10,
            'type' => 'credit',
            'origin_key' => fake()->unique()->uuid(),
        ];
    }
}
