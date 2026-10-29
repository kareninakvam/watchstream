<?php

namespace Tests\Unit\Security;

use App\Logging\RedactSecrets;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class RedactSecretsTest extends TestCase
{
    public function test_password_confirmation_is_redacted(): void
    {
        $record = new LogRecord(new DateTimeImmutable, 'test', Level::Info, 'Cadastro', [
            'password_confirmation' => 'confirmation-must-not-leak',
        ]);

        $result = (new RedactSecrets)($record);

        $this->assertStringNotContainsString('confirmation-must-not-leak', json_encode($result->context, JSON_THROW_ON_ERROR));
    }

    public function test_url_credentials_and_token_query_are_redacted_without_losing_safe_parameters(): void
    {
        $record = new LogRecord(new DateTimeImmutable, 'test', Level::Warning, 'https://user:url-password@provider.test/oauth?access_token=query-secret&state=safe-state');

        $result = (new RedactSecrets)($record);

        $this->assertStringNotContainsString('url-password', $result->message);
        $this->assertStringNotContainsString('query-secret', $result->message);
        $this->assertStringContainsString('state=safe-state', $result->message);
    }

    public function test_exception_and_object_context_do_not_expose_secrets(): void
    {
        $record = new LogRecord(new DateTimeImmutable, 'test', Level::Error, 'Falha', [
            'exception' => new \RuntimeException('access_token=exception-secret'),
            'object' => (object) ['password' => 'object-secret'],
        ], ['Authorization' => 'Bearer extra-secret']);

        $result = (new RedactSecrets)($record);
        $serialized = json_encode([$result->context, $result->extra], JSON_THROW_ON_ERROR);

        foreach (['exception-secret', 'object-secret', 'extra-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
        $this->assertSame(\RuntimeException::class, $result->context['exception']['type']);
    }

    public function test_sensitive_context_is_redacted_recursively_without_removing_operational_details(): void
    {
        $record = new LogRecord(new DateTimeImmutable, 'test', Level::Error, 'Falha OAuth', [
            'access_token' => 'secret-access', 'refresh_token' => 'secret-refresh',
            'request' => ['Authorization' => 'Bearer secret-bearer', 'password' => 'secret-password'],
            'event_id' => 'event-123',
        ]);
        $result = (new RedactSecrets)($record);
        $json = json_encode($result->context, JSON_THROW_ON_ERROR);

        foreach (['secret-access', 'secret-refresh', 'secret-bearer', 'secret-password'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertSame('event-123', $result->context['event_id']);
    }

    public function test_authorization_header_in_message_is_redacted(): void
    {
        $record = new LogRecord(new DateTimeImmutable, 'test', Level::Warning, 'Authorization: Bearer secret-header');
        $result = (new RedactSecrets)($record);

        $this->assertStringNotContainsString('secret-header', $result->message);
    }
}
