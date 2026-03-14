<?php

namespace Tests\Feature;

use App\Http\Controllers\OuterPrimeLondonController;
use App\Http\Controllers\PrimeLondonController;
use App\Http\Controllers\UltraLondonController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostgresControllerRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('land_registry', 'YearDate')) {
            Schema::table('land_registry', function (Blueprint $table): void {
                $table->unsignedSmallInteger('YearDate')->nullable();
            });
        }
    }

    public function test_prime_london_route_loads_without_mysql_specific_sql(): void
    {
        $this->get('/property/prime-central-london')->assertOk();
    }

    public function test_prime_london_route_uses_median_price_series(): void
    {
        $this->seedPrimeDataset('Prime Central', 'AB1');

        $expected = DB::connection()->getDriverName() === 'pgsql' ? 250000 : 400000;

        $this->get('/property/prime-central-london')
            ->assertOk()
            ->assertViewHas('charts', function ($charts) use ($expected) {
                $series = collect($charts['ALL']['avgPrice'] ?? []);

                return (int) ($series->first()->avg_price ?? 0) === $expected;
            });
    }

    public function test_prime_london_chart_ticks_keep_the_latest_year_visible(): void
    {
        $this->seedPrimeDataset('Prime Central', 'AB1');

        $this->get('/property/prime-central-london')
            ->assertOk()
            ->assertSee('text: yearRangeTitle', false);
    }

    public function test_prime_london_route_uses_warmed_cache_without_district_queries(): void
    {
        $this->seedPrimeDataset('Prime Central', 'AB1');

        $prefix = 'pcl:home:rolling:202404';

        Cache::put($prefix.':ALL:avgPrice', collect([(object) ['year' => 2024, 'avg_price' => 777777]]), now()->addHour());
        Cache::put($prefix.':ALL:sales', collect([(object) ['year' => 2024, 'sales' => 99]]), now()->addHour());
        Cache::put($prefix.':ALL:propertyTypes', collect(), now()->addHour());
        Cache::put($prefix.':ALL:avgPriceByType', collect(), now()->addHour());
        Cache::put($prefix.':ALL:newBuildPct', collect(), now()->addHour());
        Cache::put($prefix.':ALL:tenurePct', collect(), now()->addHour());
        Cache::put($prefix.':ALL:p90', collect(), now()->addHour());
        Cache::put($prefix.':ALL:top5', collect(), now()->addHour());
        Cache::put($prefix.':ALL:topSalePerYear', collect(), now()->addHour());
        Cache::put($prefix.':ALL:top3PerYear', collect(), now()->addHour());

        Cache::put($prefix.':AB1:avgPrice', collect([(object) ['year' => 2024, 'avg_price' => 777777]]), now()->addHour());
        Cache::put($prefix.':AB1:sales', collect([(object) ['year' => 2024, 'sales' => 99]]), now()->addHour());
        Cache::put($prefix.':AB1:propertyTypes', collect(), now()->addHour());
        Cache::put($prefix.':AB1:avgPriceByType', collect(), now()->addHour());
        Cache::put($prefix.':AB1:newBuildPct', collect(), now()->addHour());
        Cache::put($prefix.':AB1:tenurePct', collect(), now()->addHour());
        Cache::put($prefix.':AB1:p90', collect(), now()->addHour());
        Cache::put($prefix.':AB1:top5', collect(), now()->addHour());
        Cache::put($prefix.':AB1:topSalePerYear', collect(), now()->addHour());
        Cache::put($prefix.':AB1:top3PerYear', collect(), now()->addHour());

        $response = app(PrimeLondonController::class)->home();

        $this->assertSame('prime.home', $response->name());
        $charts = $response->getData()['charts'];

        $this->assertSame(777777, (int) $charts['ALL']['avgPrice']->first()->avg_price);
        $this->assertSame(99, (int) $charts['ALL']['sales']->first()->sales);
    }

    public function test_ultra_prime_london_route_uses_warmed_cache_without_district_queries(): void
    {
        $this->seedPrimeDataset('Ultra Prime', 'EF1');

        $prefix = 'upcl:home:rolling:202404';

        Cache::put($prefix.':ALL:avgPrice', collect([(object) ['year' => 2024, 'avg_price' => 888888]]), now()->addHour());
        Cache::put($prefix.':ALL:sales', collect([(object) ['year' => 2024, 'sales' => 66]]), now()->addHour());
        Cache::put($prefix.':ALL:propertyTypes', collect(), now()->addHour());
        Cache::put($prefix.':ALL:avgPriceByType', collect(), now()->addHour());
        Cache::put($prefix.':ALL:newBuildPct', collect(), now()->addHour());
        Cache::put($prefix.':ALL:tenurePct', collect(), now()->addHour());
        Cache::put($prefix.':ALL:p90', collect(), now()->addHour());
        Cache::put($prefix.':ALL:top5', collect(), now()->addHour());
        Cache::put($prefix.':ALL:topSalePerYear', collect(), now()->addHour());
        Cache::put($prefix.':ALL:top3PerYear', collect(), now()->addHour());

        Cache::put($prefix.':EF1:avgPrice', collect([(object) ['year' => 2024, 'avg_price' => 888888]]), now()->addHour());
        Cache::put($prefix.':EF1:sales', collect([(object) ['year' => 2024, 'sales' => 66]]), now()->addHour());
        Cache::put($prefix.':EF1:propertyTypes', collect(), now()->addHour());
        Cache::put($prefix.':EF1:avgPriceByType', collect(), now()->addHour());
        Cache::put($prefix.':EF1:newBuildPct', collect(), now()->addHour());
        Cache::put($prefix.':EF1:tenurePct', collect(), now()->addHour());
        Cache::put($prefix.':EF1:p90', collect(), now()->addHour());
        Cache::put($prefix.':EF1:top5', collect(), now()->addHour());
        Cache::put($prefix.':EF1:topSalePerYear', collect(), now()->addHour());
        Cache::put($prefix.':EF1:top3PerYear', collect(), now()->addHour());

        $response = app(UltraLondonController::class)->home();

        $this->assertSame('ultra.home', $response->name());
        $charts = $response->getData()['charts'];

        $this->assertSame(888888, (int) $charts['ALL']['avgPrice']->first()->avg_price);
        $this->assertSame(66, (int) $charts['ALL']['sales']->first()->sales);
    }

    public function test_outer_prime_london_route_loads_without_mysql_specific_sql(): void
    {
        $this->get('/property/outer-prime-london')->assertOk();
    }

    public function test_outer_prime_london_route_uses_median_price_series(): void
    {
        $this->seedPrimeDataset('Outer Prime London', 'CD1');

        $expected = DB::connection()->getDriverName() === 'pgsql' ? 250000 : 400000;

        $this->get('/property/outer-prime-london')
            ->assertOk()
            ->assertViewHas('charts', function ($charts) use ($expected) {
                $series = collect($charts['ALL']['avgPrice'] ?? []);

                return (int) ($series->first()->avg_price ?? 0) === $expected;
            });
    }

    public function test_outer_prime_london_route_uses_warmed_cache_without_district_queries(): void
    {
        $this->seedPrimeDataset('Outer Prime London', 'CD1');

        $prefix = 'outerprime:home:rolling:202404';

        Cache::put($prefix.':ALL:avgPrice', collect([(object) ['year' => 2024, 'avg_price' => 999999]]), now()->addHour());
        Cache::put($prefix.':ALL:sales', collect([(object) ['year' => 2024, 'sales' => 77]]), now()->addHour());
        Cache::put($prefix.':ALL:propertyTypes', collect(), now()->addHour());
        Cache::put($prefix.':ALL:avgPriceByType', collect(), now()->addHour());
        Cache::put($prefix.':ALL:newBuildPct', collect(), now()->addHour());
        Cache::put($prefix.':ALL:tenurePct', collect(), now()->addHour());
        Cache::put($prefix.':ALL:p90', collect(), now()->addHour());
        Cache::put($prefix.':ALL:top5', collect(), now()->addHour());
        Cache::put($prefix.':ALL:topSalePerYear', collect(), now()->addHour());
        Cache::put($prefix.':ALL:top3PerYear', collect(), now()->addHour());

        Cache::put($prefix.':CD1:avgPrice', collect([(object) ['year' => 2024, 'avg_price' => 999999]]), now()->addHour());
        Cache::put($prefix.':CD1:sales', collect([(object) ['year' => 2024, 'sales' => 77]]), now()->addHour());
        Cache::put($prefix.':CD1:propertyTypes', collect(), now()->addHour());
        Cache::put($prefix.':CD1:avgPriceByType', collect(), now()->addHour());
        Cache::put($prefix.':CD1:newBuildPct', collect(), now()->addHour());
        Cache::put($prefix.':CD1:tenurePct', collect(), now()->addHour());
        Cache::put($prefix.':CD1:p90', collect(), now()->addHour());
        Cache::put($prefix.':CD1:top5', collect(), now()->addHour());
        Cache::put($prefix.':CD1:topSalePerYear', collect(), now()->addHour());
        Cache::put($prefix.':CD1:top3PerYear', collect(), now()->addHour());

        $response = app(OuterPrimeLondonController::class)->home(request());

        $this->assertSame('outerprime.home', $response->name());
        $charts = $response->getData()['charts'];

        $this->assertSame(999999, (int) $charts['ALL']['avgPrice']->first()->avg_price);
        $this->assertSame(77, (int) $charts['ALL']['sales']->first()->sales);
    }

    public function test_ultra_prime_london_route_loads_without_mysql_specific_sql(): void
    {
        $this->get('/property/ultra-prime-central-london')->assertOk();
    }

    public function test_ultra_prime_london_route_uses_median_price_series(): void
    {
        $this->seedPrimeDataset('Ultra Prime', 'EF1');

        $expected = DB::connection()->getDriverName() === 'pgsql' ? 250000 : 400000;

        $this->get('/property/ultra-prime-central-london')
            ->assertOk()
            ->assertViewHas('charts', function ($charts) use ($expected) {
                $series = collect($charts['ALL']['avgPrice'] ?? []);

                return (int) ($series->first()->avg_price ?? 0) === $expected;
            });
    }

    public function test_new_old_route_loads_without_mysql_specific_sql(): void
    {
        $this->get('/new-old')->assertOk();
    }

    private function seedPrimeDataset(string $category, string $postcodePrefix): void
    {
        DB::table('prime_postcodes')->insert([
            'postcode' => $postcodePrefix,
            'category' => $category,
            'notes' => 'Test',
        ]);

        DB::table('land_registry')->insert([
            $this->landRegistryRow(
                transactionId: '11111111-1111-1111-1111-11111111111111',
                price: 100000,
                date: '2024-01-15 00:00:00',
                postcode: $postcodePrefix.' 1AA'
            ),
            $this->landRegistryRow(
                transactionId: '22222222-2222-2222-2222-22222222222222',
                price: 200000,
                date: '2024-02-15 00:00:00',
                postcode: $postcodePrefix.' 2BB'
            ),
            $this->landRegistryRow(
                transactionId: '33333333-3333-3333-3333-33333333333333',
                price: 300000,
                date: '2024-03-15 00:00:00',
                postcode: $postcodePrefix.' 3CC'
            ),
            $this->landRegistryRow(
                transactionId: '44444444-4444-4444-4444-44444444444444',
                price: 1000000,
                date: '2024-04-15 00:00:00',
                postcode: $postcodePrefix.' 4DD'
            ),
        ]);
    }

    private function landRegistryRow(
        string $transactionId,
        int $price,
        string $date,
        string $postcode
    ): array {
        return [
            'TransactionID' => $transactionId,
            'Price' => $price,
            'Date' => $date,
            'Postcode' => $postcode,
            'PAON' => '1',
            'Street' => 'HIGH STREET',
            'PropertyType' => 'D',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'Locality' => 'LOCAL',
            'TownCity' => 'TOWN',
            'District' => 'DISTRICT',
            'County' => 'COUNTY',
            'PPDCategoryType' => 'A',
            'YearDate' => (int) date('Y', strtotime($date)),
        ];
    }
}
