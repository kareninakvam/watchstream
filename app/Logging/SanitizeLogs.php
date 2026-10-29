<?php

namespace App\Logging;

use Illuminate\Log\Logger;

class SanitizeLogs
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new RedactSecrets);
    }
}
