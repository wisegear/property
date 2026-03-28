<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrimeWarmCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_warms_national_and_area_crime_caches(): void
    {
        DB::table('crime')->insert([
            [
                'crime_id' => 'crime-1',
                'month' => '2025-04-01',
                'falls_within' => 'Alpha County',
                'crime_type' => 'Theft',
            ],
            [
                'crime_id' => 'crime-2',
                'month' => '2026-03-01',
                'falls_within' => 'Alpha County',
                'crime_type' => 'Theft',
            ],
            [
                'crime_id' => 'crime-3',
                'month' => '2026-03-01',
                'falls_within' => 'Beta Region',
                'crime_type' => 'Burglary',
            ],
        ]);

        $this->artisan('crime:warm')
            ->expectsOutput('Warming national crime dashboard cache.')
            ->expectsOutput('Warming alpha-county')
            ->expectsOutput('Warming beta-region')
            ->expectsOutput('Crime dashboard cache warming complete (2 areas)')
            ->assertExitCode(0);

        $this->assertNotNull(Cache::get('insights:crime:national:v3'));
        $this->assertNotNull(Cache::get('insights:crime:area:v3:alpha-county'));
        $this->assertNotNull(Cache::get('insights:crime:area:v3:beta-region'));
        $this->assertNotNull(Cache::get('insights:crime:last_warmed_at'));
    }
}
