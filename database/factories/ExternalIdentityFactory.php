<?php

namespace Database\Factories;

use App\Models\ExternalIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExternalIdentity> */
class ExternalIdentityFactory extends Factory
{
    protected $model = ExternalIdentity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'twitch',
            'provider_user_id' => fake()->unique()->uuid(),
            'display_name' => fake()->userName(),
        ];
    }
}
