<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

class RedactSecrets implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactString($record->message),
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    private function redact(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return '[OMITTED]';
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key));
                $sensitive = preg_match('/(?:token|password(?:confirmation)?|secret|authorization|cookie|apikey|appkey|privatekey|accesskey)$/', $normalized);
                $result[$key] = $sensitive ? '[REDACTED]' : $this->redact($item, $depth + 1);
            }

            return $result;
        }

        if ($value instanceof Throwable) {
            return ['type' => $value::class, 'message' => $this->redactString($value->getMessage())];
        }

        if (is_object($value)) {
            return ['type' => $value::class];
        }

        return is_string($value) ? $this->redactString($value) : $value;
    }

    private function redactString(string $value): string
    {
        $value = preg_replace('/\b(Bearer|Basic)\s+[a-z0-9+\/=._~\-]+/i', '$1 [REDACTED]', $value);
        $value = preg_replace('/\b(password(?:_confirmation)?|client_secret|access_token|refresh_token|id_token|api_key|app_key)\s*([=:])\s*[^\s&,;]+/i', '$1$2[REDACTED]', $value);

        return preg_replace('~(https?://)[^\s/@]+:[^\s/@]+@~i', '$1[REDACTED]@', $value);
    }
}
