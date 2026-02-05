<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LandRegistryDurationConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_duration_u_can_be_inserted_into_land_registry(): void
    {
        DB::table('land_registry')->insert([
            'Duration' => 'U',
        ]);

        $this->assertDatabaseHas('land_registry', [
            'Duration' => 'U',
        ]);
    }
}
