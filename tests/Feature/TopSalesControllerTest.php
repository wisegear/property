<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TopSalesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-18 12:00:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_latest_page_builds_the_high_value_dashboard_from_category_a_sales(): void
    {
        $rows = [];

        for ($index = 1; $index <= 10; $index++) {
            $rows[] = $this->transaction("sale-{$index}", $index * 100000, '2026-07-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), $index === 10 ? 'GREATER LONDON' : 'WEST MIDLANDS');
        }

        $rows[] = $this->transaction('excluded', 9000000, '2026-07-20', 'GREATER LONDON', 'B');
        DB::table('land_registry')->insert($rows);

        $this->get('/top-property-sales')
            ->assertOk()
            ->assertViewIs('pages.top-sales.index')
            ->assertSee('High Value Property — July 2026')
            ->assertSee('Price required to enter the top 10% of the market this month')
            ->assertSee('£900,000')
            ->assertSee('Top Property Sales This Month')
            ->assertSee('The £1 Million Market')
            ->assertSee('Record Property Sales')
            ->assertSee('10, Example Street')
            ->assertSee('/property/sw1a-1aa-10-example-street', false)
            ->assertDontSee('£9,000,000');

        $this->assertNotNull(Cache::get('property:high-value:v2:202607'));
    }

    public function test_public_api_returns_the_latest_high_value_dashboard(): void
    {
        $rows = [];

        for ($index = 1; $index <= 10; $index++) {
            $rows[] = $this->transaction("api-sale-{$index}", $index * 100000, '2026-07-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), $index === 10 ? 'GREATER LONDON' : 'WEST MIDLANDS');
        }

        DB::table('land_registry')->insert($rows);

        $response = $this->getJson('/api/v1/property/top-sales');

        $response
            ->assertOk()
            ->assertJsonPath('data.threshold', fn (int $value): bool => $value >= 900000)
            ->assertJsonPath('data.headline.sales', fn (int $value): bool => $value >= 1)
            ->assertJsonPath('data.topSales.0.price', 1000000)
            ->assertJsonPath('data.topSales.0.property_slug', 'sw1a-1aa-10-example-street')
            ->assertJsonPath('data.millionMarket.counts.1000000', 1)
            ->assertHeader('cache-control');

        $this->assertNotNull($response->headers->get('ETag'));
    }

    public function test_public_api_returns_a_requested_available_month(): void
    {
        $rows = [];
        for ($index = 1; $index <= 10; $index++) {
            $rows[] = $this->transaction("march-api-{$index}", $index * 100000, '2026-03-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'WEST MIDLANDS');
            $rows[] = $this->transaction("april-api-{$index}", $index * 200000, '2026-04-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'GREATER LONDON');
        }
        DB::table('land_registry')->insert($rows);

        $this->getJson('/api/v1/property/top-sales?year=2026&month=03')
            ->assertOk()
            ->assertJsonPath('data.month', '2026-03-01T00:00:00.000000Z')
            ->assertJsonPath('data.topSales.0.price', 1000000);
    }

    public function test_public_api_rejects_an_unavailable_month(): void
    {
        DB::table('land_registry')->insert(
            $this->transaction('march-only', 600000, '2026-03-10', 'WEST MIDLANDS'),
        );

        $this->getJson('/api/v1/property/top-sales?year=2026&month=02')
            ->assertNotFound();
    }

    public function test_month_archive_navigation_only_shows_available_current_year_months(): void
    {
        DB::table('land_registry')->insert([
            $this->transaction('jan', 400000, '2026-01-10', 'WEST MIDLANDS'),
            $this->transaction('mar', 500000, '2026-03-10', 'WEST MIDLANDS'),
            $this->transaction('jul', 600000, '2026-07-10', 'WEST MIDLANDS'),
        ]);

        $this->get('/top-property-sales/2026/03')
            ->assertOk()
            ->assertSee('High Value Property — March 2026')
            ->assertSee('data-high-value-month="2026-01"', false)
            ->assertSee('data-high-value-month="2026-03"', false)
            ->assertSee('data-high-value-month="2026-07"', false)
            ->assertDontSee('data-high-value-month="2026-02"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_unavailable_and_invalid_months_return_not_found(): void
    {
        DB::table('land_registry')->insert($this->transaction('jul', 600000, '2026-07-10', 'WEST MIDLANDS'));

        $this->get('/top-property-sales/2026/06')->assertNotFound();
        $this->get('/top-property-sales/2026/13')->assertNotFound();
    }

    public function test_june_2026_does_not_expose_the_property_map(): void
    {
        DB::table('land_registry')->insert($this->transaction('june-map', 600000, '2026-06-10', 'WEST MIDLANDS'));

        $this->get('/top-property-sales/2026/06')
            ->assertOk()
            ->assertDontSee('data-map-mode="properties"', false)
            ->assertDontSee('Property-level mapping is available from July 2026')
            ->assertDontSee('data-property-points-url', false);

        $this->getJson('/top-property-sales/2026/06/points?e_min=0&e_max=700000&n_min=0&n_max=1300000')
            ->assertNotFound();
    }

    public function test_july_2026_exposes_the_property_map(): void
    {
        DB::table('land_registry')->insert($this->transaction('july-map', 600000, '2026-07-10', 'WEST MIDLANDS'));

        $this->get('/top-property-sales/2026/07')
            ->assertOk()
            ->assertSee('data-map-mode="properties"', false)
            ->assertSee('Property Sales')
            ->assertSee('Property-level mapping is available from July 2026')
            ->assertSee('/top-property-sales/2026/07/points', false);
    }

    public function test_property_map_only_returns_high_value_transactions_with_uprns_and_onsud_coordinates(): void
    {
        $transactions = [];

        for ($index = 1; $index <= 10; $index++) {
            $transactions[] = $this->transaction("point-{$index}", $index * 100000, '2026-07-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'WEST MIDLANDS');
        }

        $transactions[7]['Price'] = 1000000;
        $transactions[8]['Price'] = 1000000;
        $transactions[6]['Price'] = 1000000;

        DB::table('land_registry')->insert($transactions);

        DB::table('land_registry_uprn')->insert([
            ['transaction_id' => $transactions[9]['TransactionID'], 'uprn' => '100000000001'],
            ['transaction_id' => $transactions[8]['TransactionID'], 'uprn' => '100000000002'],
            ['transaction_id' => $transactions[7]['TransactionID'], 'uprn' => '100000000003'],
        ]);
        DB::table('onsud')->insert([
            ['UPRN' => '100000000001', 'GRIDGB1E' => 530000, 'GRIDGB1N' => 180000],
            ['UPRN' => '100000000002', 'GRIDGB1E' => 531000, 'GRIDGB1N' => 181000],
            ['UPRN' => '100000000003', 'GRIDGB1E' => null, 'GRIDGB1N' => null],
        ]);

        $this->getJson('/top-property-sales/2026/07/points?e_min=500000&e_max=550000&n_min=150000&n_max=200000')
            ->assertOk()
            ->assertJsonCount(2, 'points')
            ->assertJsonPath('truncated', false)
            ->assertJsonPath('points.0.price', 1000000)
            ->assertJsonFragment(['easting' => 530000, 'northing' => 180000])
            ->assertJsonFragment(['easting' => 531000, 'northing' => 181000])
            ->assertJsonMissing(['address' => '8, Example Street'])
            ->assertJsonMissing(['address' => '7, Example Street']);
    }

    private function transaction(string $id, int $price, string $date, string $county, string $category = 'A'): array
    {
        $number = preg_replace('/\D/', '', $id) ?: '10';

        return [
            'TransactionID' => str_pad(substr($id, 0, 8), 8, '0').'-aaaa-bbbb-cccc-'.str_pad($number, 12, '0', STR_PAD_LEFT),
            'Price' => $price,
            'Date' => $date,
            'Postcode' => 'SW1A 1AA',
            'PropertyType' => 'D',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'PAON' => $number,
            'SAON' => null,
            'Street' => 'Example Street',
            'Locality' => null,
            'TownCity' => 'London',
            'District' => 'CITY OF WESTMINSTER',
            'County' => $county,
            'PPDCategoryType' => $category,
            'RecordStatus' => 'A',
        ];
    }
}
