<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PropertyControllerPostgresCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_home_route_loads_with_postgres_safe_queries(): void
    {
        DB::table('land_registry')->insert([
            $this->landRegistryRow(
                transactionId: '11111111-1111-1111-1111-11111111111111',
                price: 250000,
                date: '2024-01-15 00:00:00',
                postcode: 'AB1 2CD',
                paon: '1',
                street: 'HIGH STREET'
            ),
        ]);

        $this->get('/property')->assertOk();
    }

    public function test_property_home_uses_median_price_series_for_chart_data(): void
    {
        Cache::forget('land_registry_avg_price_by_year:catA:v3');

        DB::table('land_registry')->insert([
            $this->landRegistryRow(
                transactionId: '33333333-3333-3333-3333-33333333333333',
                price: 100000,
                date: '2024-01-15 00:00:00',
                postcode: 'AB1 2CD',
                paon: '1',
                street: 'HIGH STREET'
            ),
            $this->landRegistryRow(
                transactionId: '44444444-4444-4444-4444-44444444444444',
                price: 200000,
                date: '2024-02-15 00:00:00',
                postcode: 'AB1 2CD',
                paon: '2',
                street: 'HIGH STREET'
            ),
            $this->landRegistryRow(
                transactionId: '55555555-5555-5555-5555-55555555555555',
                price: 300000,
                date: '2024-03-15 00:00:00',
                postcode: 'AB1 2CD',
                paon: '3',
                street: 'HIGH STREET'
            ),
            $this->landRegistryRow(
                transactionId: '66666666-6666-6666-6666-66666666666666',
                price: 1000000,
                date: '2024-04-15 00:00:00',
                postcode: 'AB1 2CD',
                paon: '4',
                street: 'HIGH STREET'
            ),
        ]);

        $this->get('/property')
            ->assertOk()
            ->assertViewHas('avgPriceByYear', function ($series) {
                $expected = DB::connection()->getDriverName() === 'pgsql' ? 250000 : 400000;

                return (int) $series->first()->avg_price === $expected;
            });
    }

    public function test_property_show_route_loads_without_mysql_index_hints(): void
    {
        DB::table('land_registry')->insert([
            $this->landRegistryRow(
                transactionId: '22222222-2222-2222-2222-22222222222222',
                price: 350000,
                date: '2025-03-01 00:00:00',
                postcode: 'AB1 2CD',
                paon: '10',
                street: 'MARKET ROAD'
            ),
        ]);

        DB::table('onspd')->insert([
            'pcds' => 'AB1 2CD',
            'lat' => 52.123456,
            'long' => -1.123456,
        ]);

        $slug = 'ab1-2cd-10-market-road';

        $this->get('/property/show?postcode=AB1%202CD&paon=10&street=MARKET ROAD')
            ->assertStatus(301)
            ->assertRedirectToRoute('property.show.slug', ['slug' => $slug]);

        $this->get(route('property.show.slug', ['slug' => $slug], false))
            ->assertOk();
    }

    public function test_property_show_route_uses_median_price_series_for_charts(): void
    {
        Cache::forget('postcode:AB1 2CD:type:D:priceHistory:v4:catA');

        DB::table('land_registry')->insert([
            $this->landRegistryRow(
                transactionId: '77777777-7777-7777-7777-77777777777777',
                price: 100000,
                date: '2024-01-15 00:00:00',
                postcode: 'AB1 2CD',
                paon: '10',
                street: 'MARKET ROAD'
            ),
            $this->landRegistryRow(
                transactionId: '88888888-8888-8888-8888-88888888888888',
                price: 200000,
                date: '2024-02-15 00:00:00',
                postcode: 'AB1 2CD',
                paon: '11',
                street: 'MARKET ROAD'
            ),
            $this->landRegistryRow(
                transactionId: '99999999-9999-9999-9999-99999999999999',
                price: 300000,
                date: '2024-03-15 00:00:00',
                postcode: 'AB1 2CD',
                paon: '12',
                street: 'MARKET ROAD'
            ),
            $this->landRegistryRow(
                transactionId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaaaa',
                price: 1000000,
                date: '2024-04-15 00:00:00',
                postcode: 'AB1 2CD',
                paon: '13',
                street: 'MARKET ROAD'
            ),
        ]);

        DB::table('onspd')->insert([
            'pcds' => 'AB1 2CD',
            'lat' => 52.123456,
            'long' => -1.123456,
        ]);

        $expected = DB::connection()->getDriverName() === 'pgsql' ? 250000 : 400000;

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertViewHas('postcodePriceHistory', function ($series) use ($expected) {
                return (int) ($series->first()->avg_price ?? 0) === $expected;
            });
    }

    public function test_property_show_displays_area_link_only_when_area_exists_in_index(): void
    {
        Cache::forget('property:area:index:v1');

        $areas = json_decode(file_get_contents(public_path('data/property_districts.json')), true) ?? [];
        $districtArea = collect($areas)->first(function ($item) {
            return is_array($item)
                && (($item['type'] ?? null) === 'district')
                && ! empty($item['name'] ?? $item['label'] ?? null);
        });

        if (! $districtArea) {
            $this->markTestSkipped('No district area found in property_districts.json.');
        }

        $districtName = (string) ($districtArea['name'] ?? $districtArea['label']);
        $districtSlug = Str::slug($districtName);
        $expectedAreaUrl = route('property.area.show', [
            'type' => 'district',
            'slug' => $districtSlug,
        ], absolute: false);

        DB::table('land_registry')->insert([
            array_merge($this->landRegistryRow(
                transactionId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbbbb',
                price: 350000,
                date: '2025-03-01 00:00:00',
                postcode: 'AB1 2CD',
                paon: '10',
                street: 'MARKET ROAD'
            ), [
                'District' => $districtName,
                'County' => 'Different County',
                'TownCity' => '',
                'Locality' => '',
            ]),
        ]);

        DB::table('onspd')->insert([
            'pcds' => 'AB1 2CD',
            'lat' => 52.123456,
            'long' => -1.123456,
        ]);

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertViewHas('districtAreaLink', $expectedAreaUrl);
    }

    public function test_property_show_route_redirects_to_slug_with_saon_and_normalized_values(): void
    {
        DB::table('land_registry')->insert([
            array_merge($this->landRegistryRow(
                transactionId: 'cccccccc-cccc-cccc-cccc-cccccccccccccc',
                price: 450000,
                date: '2025-03-10 00:00:00',
                postcode: 'SW7 5PH',
                paon: '36',
                street: 'QUEENS  GATE TERRACE'
            ), [
                'SAON' => 'SECOND FLOOR FLAT',
            ]),
        ]);

        DB::table('onspd')->insert([
            'pcds' => 'SW7 5PH',
            'lat' => 51.4994,
            'long' => -0.1792,
        ]);

        $slug = 'sw7-5ph-36-queens-gate-terrace-second-floor-flat';

        $this->get('/property/show?postcode=sw7%205ph&paon=36&street=Queens%20%20Gate%20Terrace&saon=Second%20Floor%20Flat')
            ->assertStatus(301)
            ->assertRedirectToRoute('property.show.slug', ['slug' => $slug]);

        $this->get(route('property.show.slug', ['slug' => $slug], false))
            ->assertOk()
            ->assertViewHas('slug', $slug);
    }

    public function test_property_show_route_redirects_to_slug_without_saon(): void
    {
        DB::table('land_registry')->insert([
            $this->landRegistryRow(
                transactionId: 'dddddddd-dddd-dddd-dddd-dddddddddddddd',
                price: 275000,
                date: '2025-02-01 00:00:00',
                postcode: 'AB1 2CD',
                paon: '12',
                street: 'HIGH  STREET'
            ),
        ]);

        DB::table('onspd')->insert([
            'pcds' => 'AB1 2CD',
            'lat' => 52.123456,
            'long' => -1.123456,
        ]);

        $slug = 'ab1-2cd-12-high-street';

        $this->get('/property/show?postcode=AB1%202CD&paon=12&street=HIGH%20%20STREET')
            ->assertStatus(301)
            ->assertRedirectToRoute('property.show.slug', ['slug' => $slug]);

        $this->get(route('property.show.slug', ['slug' => $slug], false))
            ->assertOk()
            ->assertViewHas('slug', $slug);
    }

    private function landRegistryRow(
        string $transactionId,
        int $price,
        string $date,
        string $postcode,
        string $paon,
        string $street
    ): array {
        $row = [
            'TransactionID' => $transactionId,
            'Price' => $price,
            'Date' => $date,
            'Postcode' => $postcode,
            'PAON' => $paon,
            'Street' => $street,
            'PropertyType' => 'D',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'Locality' => 'LOCAL',
            'TownCity' => 'TOWN',
            'District' => 'DISTRICT',
            'County' => 'COUNTY',
            'PPDCategoryType' => 'A',
        ];

        if (Schema::hasColumn('land_registry', 'YearDate')) {
            $row['YearDate'] = (int) date('Y', strtotime($date));
        }

        return $row;
    }
}
