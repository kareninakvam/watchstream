<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\ReceivedEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReceivedEvent> */
class ReceivedEventFactory extends Factory
{
    protected $model = ReceivedEvent::class;

    public function definition(): array
    {
        return [
            'provider' => 'twitch',
            'channel_id' => Channel::factory(),
            'provider_event_id' => fake()->unique()->uuid(),
            'provider_user_id' => fake()->uuid(),
            'occurred_at' => now('UTC'),
            'received_at' => now('UTC'),
        ];
    }
}
