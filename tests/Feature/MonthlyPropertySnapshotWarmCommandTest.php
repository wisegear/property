<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthlyPropertySnapshotWarmCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_command_warms_every_available_category_a_month(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('january', 200000, '2025-01-10', 'A'),
            $this->transaction('march', 400000, '2026-03-10', 'A'),
            $this->transaction('excluded', 900000, '2026-04-10', 'B'),
        ]);

        $this->artisan('property:monthly-snapshot-warm')
            ->expectsOutput('Warming Property Monthly Snapshot caches...')
            ->expectsOutput('Property Monthly Snapshot warming complete (2 months).')
            ->assertSuccessful();

        $this->assertNotNull(Cache::get('property:monthly-snapshot:v3:202501'));
        $this->assertNotNull(Cache::get('property:monthly-snapshot:v3:202603'));
        $this->assertNull(Cache::get('property:monthly-snapshot:v3:202604'));
        $this->assertNotNull(Cache::get('property:monthly-snapshot:last-warmed-at'));
    }

    public function test_command_can_limit_warming_to_one_year(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('january', 200000, '2025-01-10', 'A'),
            $this->transaction('march', 400000, '2026-03-10', 'A'),
        ]);

        $this->artisan('property:monthly-snapshot-warm', ['--year' => '2026'])
            ->expectsOutput('Property Monthly Snapshot warming complete (1 month).')
            ->assertSuccessful();

        $this->assertNull(Cache::get('property:monthly-snapshot:v3:202501'));
        $this->assertNotNull(Cache::get('property:monthly-snapshot:v3:202603'));
    }

    /** @return array<string, mixed> */
    private function transaction(string $id, int $price, string $date, string $category): array
    {
        return [
            'TransactionID' => str_pad($id, 36, '0'),
            'Price' => $price,
            'Date' => $date,
            'Postcode' => 'AB1 1AA',
            'PropertyType' => 'T',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'PAON' => '1',
            'Street' => 'HIGH STREET',
            'District' => 'TEST DISTRICT',
            'County' => 'TEST COUNTY',
            'PPDCategoryType' => $category,
        ];
    }
}
