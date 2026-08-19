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
