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

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_top_sales_page_renders_cached_sales_and_links_to_property_pages(): void
    {
        Cache::put('top_sales:last_warmed_at', Carbon::create(2026, 3, 17, 9, 30)->toIso8601String(), now()->addDays(45));

        DB::table('land_registry')->insert([
            $this->landRegistryRow('11111111-1111-1111-1111-111111111111', 12000000, '2025-01-15 00:00:00', 'SW1A 1AA', '10', null, 'Downing Street', 'London', 'GREATER LONDON'),
            $this->landRegistryRow('22222222-2222-2222-2222-222222222222', 9000000, '2025-01-14 00:00:00', 'W1K 1AB', '20', 'Flat 5', 'Park Lane', 'London', 'GREATER LONDON'),
            $this->landRegistryRow('33333333-3333-3333-3333-333333333333', 3000000, '2025-01-13 00:00:00', 'M1 1AA', '30', null, 'Deansgate', 'Manchester', 'Greater Manchester'),
            $this->landRegistryRow('44444444-4444-4444-4444-444444444444', 2000000, '2025-01-12 00:00:00', 'B1 1AA', '40', null, 'Broad Street', 'Birmingham', 'West Midlands'),
        ]);

        $response = $this->get(route('top-sales.index', ['mode' => 'ultra'], false));

        $response->assertOk();
        $response->assertViewIs('pages.top-sales.index');
        $response->assertSee('Ultra Prime London Property Sales (£10m+)');
        $response->assertSee('Top Property Sales');
        $response->assertSee('Last warmed:');
        $response->assertSee('17 Mar 2026 09:30');
        $response->assertSee('/assets/images/site/property-insghts.jpg', false);
        $response->assertSee('Ultra Prime London (£10m+)');
        $response->assertSee('Prime London (£2m-£10m)');
        $response->assertSee('Top 1% Rest of UK');
        $response->assertSee('Most Expensive Sale');
        $response->assertSee('Ultra high-value property transactions across London.');
        $response->assertDontSee('No. 1 Sale');
        $response->assertSee('Most top sales are concentrated in prime central London.');
        $response->assertSee('10, Downing Street');
        $response->assertSee('£12,000,000');
        $response->assertSee('15 Jan 2025');
        $response->assertSee('London, GREATER LONDON');
        $response->assertSee(route('property.show.slug', ['slug' => 'sw1a-1aa-10-downing-street'], false), false);
        $response->assertDontSee(route('property.show.slug', ['slug' => 'm1-1aa-30-deansgate'], false), false);
        $this->assertNotNull(Cache::get('top_sales:ultra'));
    }

    public function test_top_sales_page_can_filter_to_london_mode(): void
    {
        Cache::put('top_sales:last_warmed_at', Carbon::create(2026, 3, 17, 10, 0)->toIso8601String(), now()->addDays(45));

        DB::table('land_registry')->insert([
            $this->landRegistryRow('55555555-5555-5555-5555-555555555555', 12000000, '2025-02-01 00:00:00', 'SW1X 7XL', '1', null, 'Belgrave Square', 'London', 'GREATER LONDON'),
            $this->landRegistryRow('66666666-6666-6666-6666-666666666666', 4500000, '2025-01-01 00:00:00', 'SW3 4RY', '2', null, 'Cheyne Walk', 'London', 'GREATER LONDON'),
            $this->landRegistryRow('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 3000000, '2025-01-05 00:00:00', 'W1K 2BB', '12', null, 'King Street', 'London', 'GREATER LONDON'),
            $this->landRegistryRow('cccccccc-cccc-cccc-cccc-cccccccccccc', 3500000, '2025-01-03 00:00:00', 'M1 2BB', '18', null, 'Deansgate', 'Manchester', 'Greater Manchester'),
        ]);

        $response = $this->get(route('top-sales.index', ['mode' => 'london'], false));

        $response->assertOk();
        $response->assertSee('Prime London Property Sales (£2m-£10m)');
        $response->assertSee('High-value property transactions across London.');
        $response->assertSee('Highest value residential transactions, refreshed via the property sales warmer.');
        $response->assertSee('These sales show where London&#039;s prime market is still clearing at scale.', false);
        $response->assertSee('Next Highest Sale');
        $response->assertSee('Cheyne Walk');
        $response->assertDontSee('Belgrave Square');
        $response->assertDontSee('Deansgate');
        $this->assertNotNull(Cache::get('top_sales:london'));
    }

    public function test_top_sales_page_can_filter_to_rest_mode(): void
    {
        Cache::put('top_sales:last_warmed_at', Carbon::create(2026, 3, 17, 11, 0)->toIso8601String(), now()->addDays(45));

        DB::table('land_registry')->insert([
            $this->landRegistryRow('77777777-7777-7777-7777-777777777777', 8000000, '2025-03-01 00:00:00', 'SW1A 2AA', '1', null, 'The Mall', 'London', 'GREATER LONDON'),
            $this->landRegistryRow('88888888-8888-8888-8888-888888888888', 7000000, '2025-02-20 00:00:00', 'M2 3NT', '2', null, 'Albert Square', 'Manchester', 'Greater Manchester'),
            $this->landRegistryRow('99999999-9999-9999-9999-999999999999', 6000000, '2025-02-10 00:00:00', 'LS1 1AA', '3', null, 'Park Row', 'Leeds', 'West Yorkshire'),
            $this->landRegistryRow('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 5000000, '2025-01-10 00:00:00', 'B1 1AA', '4', null, 'Broad Street', 'Birmingham', 'West Midlands'),
        ]);

        $response = $this->get(route('top-sales.index', ['mode' => 'rest'], false));

        $response->assertOk();
        $response->assertSee('Top 1% Property Sales (Rest of UK)');
        $response->assertSee('The highest value property transactions outside London.');
        $response->assertSee('These are the most expensive sales outside London, showing where wealth is concentrating beyond the capital.');
        $response->assertSee('Albert Square');
        $response->assertDontSee('The Mall');
        $this->assertNotNull(Cache::get('top_sales:rest'));
    }

    public function test_top_sales_page_paginates_table_results_in_batches_of_fifty(): void
    {
        Cache::put('top_sales:last_warmed_at', Carbon::create(2026, 3, 17, 12, 0)->toIso8601String(), now()->addDays(45));

        $rows = [];

        for ($index = 1; $index <= 55; $index++) {
            $rows[] = $this->landRegistryRow(
                sprintf('%08d-aaaa-bbbb-cccc-%012d', $index, $index),
                20000000 - $index,
                '2025-02-01 00:00:00',
                sprintf('SW1A %dAA', $index),
                (string) $index,
                null,
                "Example Street {$index}",
                'London',
                'GREATER LONDON'
            );
        }

        DB::table('land_registry')->insert($rows);

        $response = $this->get(route('top-sales.index', ['mode' => 'ultra', 'page' => 2], false));

        $response->assertOk();
        $response->assertSee('Showing');
        $response->assertSee('51');
        $response->assertSee('55');
        $response->assertSee('of');
        $response->assertSee('Example Street 55');
        $response->assertSee('aria-current="page"', false);
        $response->assertSee('mode=ultra&amp;page=1', false);
    }

    private function landRegistryRow(
        string $transactionId,
        int $price,
        string $date,
        string $postcode,
        string $paon,
        ?string $saon,
        string $street,
        string $townCity,
        string $county
    ): array {
        return [
            'TransactionID' => $transactionId,
            'Price' => $price,
            'Date' => $date,
            'Postcode' => $postcode,
            'PropertyType' => 'D',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'PAON' => $paon,
            'SAON' => $saon,
            'Street' => $street,
            'TownCity' => $townCity,
            'County' => $county,
            'PPDCategoryType' => 'A',
        ];
    }
}
