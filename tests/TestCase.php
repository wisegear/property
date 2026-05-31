<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! is_string(config('app.key')) || config('app.key') === '') {
            config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        }

        $db = config('database.connections.pgsql.database');

        if ($db === 'prop') {
            throw new RuntimeException(
                'Tests are attempting to run against LIVE database "prop". Aborting.'
            );
        }

        if (app()->configurationIsCached()) {
            throw new RuntimeException(
                'Config cache detected. Run: php artisan config:clear before running tests.'
            );
        }
    }
}
