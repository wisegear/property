<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_public_dashboard_returns_category_a_national_market_data(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('zero', 50000, '2023-06-10', 'D', 'N', 'F', 'AB1 1AZ', 'A'),
            $this->transaction('one', 100000, '2024-05-10', 'D', 'N', 'F', 'AB1 1AA', 'A'),
            $this->transaction('two', 300000, '2024-05-11', 'S', 'Y', 'L', 'AB1 1AB', 'A'),
            $this->transaction('three', 900000, '2024-05-12', 'O', 'N', 'F', 'AB1 1AC', 'B'),
            $this->transaction('four', 200000, '2025-05-10', 'T', 'N', 'F', 'AB1 1AD', 'A'),
            $this->transaction('five', 400000, '2025-05-11', 'F', 'Y', 'L', 'AB1 1AE', 'A'),
            $this->transaction('six', 800000, '2025-05-12', 'O', 'N', 'F', 'AB1 1AF', 'A'),
        ]);

        $response = $this->getJson('/api/v1/property/dashboard');
        $expectedMedian = DB::connection()->getDriverName() === 'pgsql' ? 400000 : 466667;

        $response
            ->assertOk()
            ->assertJsonPath('data.metadata.region', 'England and Wales')
            ->assertJsonPath('data.metadata.latest_month', '2025-05')
            ->assertJsonPath('data.metadata.category', 'A')
            ->assertJsonPath('data.summary.sales', 3)
            ->assertJsonPath('data.summary.median_price', $expectedMedian)
            ->assertJsonPath('data.monthly_sales.23.period', '2025-05')
            ->assertJsonPath('data.monthly_sales.23.value', 3)
            ->assertJsonPath('data.rolling_market.0.period', '2024-05')
            ->assertJsonPath('data.rolling_market.1.period', '2025-05')
            ->assertJsonPath('data.largest_sales.3.rank', 1)
            ->assertJsonPath('data.largest_sales.3.price', 800000)
            ->assertJsonPath('data.property_types.1.other.sales', 1)
            ->assertJsonPath('data.stock_mix.1.new_build', 1)
            ->assertJsonPath('data.stock_mix.1.existing', 2)
            ->assertJsonPath('data.tenure_mix.1.freehold', 2)
            ->assertJsonPath('data.tenure_mix.1.leasehold', 1)
            ->assertJsonPath('data.year_on_year.0.sales', null)
            ->assertHeader('cache-control');

        $this->assertNotNull($response->headers->get('ETag'));
        $this->assertIsInt($response->json('data.rolling_market.1.median_price'));
        $this->assertArrayNotHasKey('TransactionID', $response->json('data.largest_sales.3'));
    }

    public function test_dashboard_etag_supports_conditional_requests(): void
    {
        DB::table('land_registry')->insert(
            $this->transaction('one', 250000, '2025-05-10', 'D', 'N', 'F', 'AB1 1AA', 'A')
        );

        $response = $this->getJson('/api/v1/property/dashboard');
        $etag = $response->headers->get('ETag');

        $this->withHeader('If-None-Match', (string) $etag)
            ->getJson('/api/v1/property/dashboard')
            ->assertStatus(304);
    }

    /**
     * @return array<string, mixed>
     */
    private function transaction(
        string $id,
        int $price,
        string $date,
        string $propertyType,
        string $newBuild,
        string $duration,
        string $postcode,
        string $category
    ): array {
        return [
            'TransactionID' => str_pad($id, 36, '0'),
            'Price' => $price,
            'Date' => $date,
            'Postcode' => $postcode,
            'PropertyType' => $propertyType,
            'NewBuild' => $newBuild,
            'Duration' => $duration,
            'PAON' => '1',
            'Street' => 'HIGH STREET',
            'PPDCategoryType' => $category,
        ];
    }
}
