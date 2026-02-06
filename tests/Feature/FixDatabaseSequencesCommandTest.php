<?php

namespace Tests\Feature;

use Tests\TestCase;

class FixDatabaseSequencesCommandTest extends TestCase
{
    public function test_fix_database_sequences_command_runs(): void
    {
        $this->artisan('db:fix-sequences')
            ->assertExitCode(0);
    }
}
