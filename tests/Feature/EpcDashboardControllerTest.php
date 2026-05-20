<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EpcDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_dashboard_supports_uppercase_epc_columns_on_ew(): void
    {
        DB::table('epc_certificates')->insert([
            'LODGEMENT_DATE' => '2025-01-15',
            'CURRENT_ENERGY_RATING' => 'C',
            'POTENTIAL_ENERGY_RATING' => 'B',
            'TENURE' => 'Owner-occupied',
        ]);

        $response = $this->get('/epc');

        $response->assertOk();
        $this->assertTrue(Cache::has('epc:ew:stats'));
        $this->assertTrue(Cache::has('epc:ew:ratingByYear'));
        $this->assertTrue(Cache::has('epc:ew:potentialByYear'));
        $this->assertTrue(Cache::has('epc:ew:tenureByYear'));
    }

    public function test_scotland_dashboard_normalises_lowercase_tenure_values(): void
    {
        DB::table('epc_certificates_scotland')->insert([
            'LODGEMENT_DATE' => '2025-01-15',
            'CURRENT_ENERGY_RATING' => 'C',
            'POTENTIAL_ENERGY_RATING' => 'B',
            'TENURE' => 'rented (private)',
        ]);

        $this->get('/epc?nation=scotland')
            ->assertOk()
            ->assertViewHas('tenureByYear', function ($rows): bool {
                return collect($rows)->contains(function ($row): bool {
                    return (string) $row->tenure === 'Rented (private)' && (int) $row->cnt === 1;
                });
            });
    }
}
