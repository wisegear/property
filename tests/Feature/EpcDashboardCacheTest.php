<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EpcDashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_epc_dashboard_warms_expected_cache_keys_for_ew(): void
    {
        DB::table('epc_certificates')->insert([
            'LODGEMENT_DATE' => '2025-01-15',
            'CURRENT_ENERGY_RATING' => 'C',
            'POTENTIAL_ENERGY_RATING' => 'B',
            'TENURE' => 'Owner-occupied',
        ]);

        $this->get('/epc')->assertOk();

        $this->assertTrue(Cache::has('epc:ew:stats'));
        $this->assertTrue(Cache::has('epc:ew:byYear'));
        $this->assertTrue(Cache::has('epc:ew:ratingByYear'));
        $this->assertTrue(Cache::has('epc:ew:potentialByYear'));
        $this->assertTrue(Cache::has('epc:ew:tenureByYear'));
        $this->assertTrue(Cache::has('epc:ew:ratingDist'));
    }
}
