<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Channel> */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'provider' => 'twitch',
            'provider_channel_id' => fake()->unique()->uuid(),
            'name' => fake()->word(),
        ];
    }
}
