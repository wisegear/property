<?php

namespace Tests\Feature;

use Tests\TestCase;

class RefreshLandRegistryMVCommandTest extends TestCase
{
    public function test_refresh_command_skips_on_non_postgresql_connections(): void
    {
        $this->artisan('landregistry:refresh-mv')
            ->expectsOutput('Skipping materialized view refresh on non-PostgreSQL connection.')
            ->assertExitCode(0);
    }
}
