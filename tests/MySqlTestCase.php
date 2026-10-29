<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

abstract class MySqlTestCase extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Esta verificacao acontece antes de RefreshDatabase executar migrations.
        if (! $app->environment('testing')
            || $app['config']->get('database.default') !== 'mysql'
            || $app['config']->get('database.connections.mysql.host') !== 'mysql-test'
            || $app['config']->get('database.connections.mysql.database') !== 'watchstream_test') {
            throw new RuntimeException('Suite MySQL permitida somente no banco isolado watchstream_test em mysql-test.');
        }

        return $app;
    }
}
