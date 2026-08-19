<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthlyPropertySnapshotApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_public_api_returns_the_latest_monthly_snapshot(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('one', 200000, '2025-06-10', 'D', 'N', 'F', 'A'),
            $this->transaction('two', 250000, '2026-05-10', 'S', 'N', 'F', 'A'),
            $this->transaction('three', 300000, '2026-06-10', 'T', 'N', 'F', 'A'),
            $this->transaction('four', 1000000, '2026-06-12', 'F', 'Y', 'L', 'A'),
            $this->transaction('excluded', 9000000, '2026-06-14', 'D', 'N', 'F', 'B'),
        ]);

        $response = $this->getJson('/api/v1/property/monthly-snapshot');

        $response
            ->assertOk()
            ->assertJsonPath('data.sales', 2)
            ->assertJsonPath('data.comparison.previous_label', 'May')
            ->assertJsonPath('data.propertyTypes.2.code', 'T')
            ->assertJsonPath('data.newBuildMix.0.label', 'New build')
            ->assertJsonPath('data.notableSales.0.price', 1000000)
            ->assertJsonPath('data.isProvisional', true)
            ->assertHeader('cache-control');

        $this->assertNotNull($response->headers->get('ETag'));
    }

    /** @return array<string, mixed> */
    private function transaction(
        string $id,
        int $price,
        string $date,
        string $propertyType,
        string $newBuild,
        string $duration,
        string $category,
    ): array {
        return [
            'TransactionID' => str_pad($id, 36, '0'),
            'Price' => $price,
            'Date' => $date,
            'Postcode' => 'LS1 1AA',
            'PropertyType' => $propertyType,
            'NewBuild' => $newBuild,
            'Duration' => $duration,
            'PAON' => '1',
            'Street' => 'HIGH STREET',
            'District' => 'LEEDS',
            'County' => 'WEST YORKSHIRE',
            'PPDCategoryType' => $category,
        ];
    }
}
