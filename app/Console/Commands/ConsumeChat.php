<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConsumeChat extends Command
{
    protected $signature = 'chat:consume {--once : Verificar o comando e encerrar}';

    protected $description = 'Processo provisório da US01; integração de chat será implementada na US06.';

    public function handle(): int
    {
        $this->warn('Ingestor em espera: integração de chat ainda não implementada (US06).');

        if ($this->option('once')) {
            return self::SUCCESS;
        }

        $running = true;
        $this->trap([SIGTERM, SIGINT], function () use (&$running): void {
            $running = false;
        });

        while ($running) {
            sleep(1);
        }

        return self::SUCCESS;
    }
}
