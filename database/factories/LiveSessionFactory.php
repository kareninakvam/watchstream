<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\LiveSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LiveSession> */
class LiveSessionFactory extends Factory
{
    protected $model = LiveSession::class;

    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'provider_stream_id' => fake()->unique()->uuid(),
            'started_at' => now('UTC'),
        ];
    }
}
