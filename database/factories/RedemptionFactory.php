<?php

namespace Database\Factories;

use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Redemption> */
class RedemptionFactory extends Factory
{
    protected $model = Redemption::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reward_id' => Reward::factory(),
            'idempotency_key' => fake()->unique()->uuid(),
            'price' => fn (array $attributes) => Reward::findOrFail($attributes['reward_id'])->price,
            'reward_title' => fn (array $attributes) => Reward::findOrFail($attributes['reward_id'])->title,
            'reward_description' => fn (array $attributes) => Reward::findOrFail($attributes['reward_id'])->description,
        ];
    }
}
