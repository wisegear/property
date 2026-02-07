<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
