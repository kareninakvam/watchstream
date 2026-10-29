<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogSecurityTest extends TestCase
{
    public function test_application_log_channel_redacts_secrets_before_formatting(): void
    {
        config(['logging.channels.stderr.handler_with.stream' => 'php://memory']);
        Log::forgetChannel('stderr');
        $logger = Log::channel('stderr');

        $logger->error('Authorization: Bearer token-must-not-leak', [
            'password' => 'password-must-not-leak',
            'access_token' => 'access-must-not-leak',
            'event_id' => 'event-visible',
        ]);
        $stream = $logger->getHandlers()[0]->getStream();
        rewind($stream);
        $output = stream_get_contents($stream);

        $this->assertStringContainsString('event-visible', $output);
        foreach (['token-must-not-leak', 'password-must-not-leak', 'access-must-not-leak'] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }
}
