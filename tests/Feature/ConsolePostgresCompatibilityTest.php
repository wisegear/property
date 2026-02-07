<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsolePostgresCompatibilityTest extends TestCase
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

    public function test_pcl_warm_runs_with_postgres_safe_sql(): void
    {
        $this->artisan('pcl:warm --parallel=1')->assertExitCode(0);
    }

    public function test_pcl_warm_caches_median_price_series(): void
    {
        $this->seedPrimeDataset('Prime Central', 'AB1');

        $this->artisan('pcl:warm --parallel=1')->assertExitCode(0);

        $expected = DB::connection()->getDriverName() === 'pgsql' ? 250000 : 400000;
        $series = collect(Cache::get('pcl:v4:catA:ALL:avgPrice'));

        $this->assertSame($expected, (int) ($series->first()->avg_price ?? 0));
    }

    public function test_upcl_warm_runs_with_postgres_safe_sql(): void
    {
        $this->artisan('upcl:warm --parallel=1')->assertExitCode(0);
    }

    public function test_upcl_warm_caches_median_price_series(): void
    {
        $this->seedPrimeDataset('Ultra Prime', 'CD1');

        $this->artisan('upcl:warm --parallel=1')->assertExitCode(0);

        $expected = DB::connection()->getDriverName() === 'pgsql' ? 250000 : 400000;
        $series = collect(Cache::get('upcl:v6:catA:ALL:avgPrice'));

        $this->assertSame($expected, (int) ($series->first()->avg_price ?? 0));
    }

    public function test_outer_prime_warm_runs_with_postgres_safe_sql(): void
    {
        $this->artisan('app:outer-prime-warm --parallel=1')->assertExitCode(0);
    }

    public function test_outer_prime_warm_caches_median_price_series(): void
    {
        $this->seedPrimeDataset('Outer Prime London', 'EF1');

        $this->artisan('app:outer-prime-warm --parallel=1')->assertExitCode(0);

        $expected = DB::connection()->getDriverName() === 'pgsql' ? 250000 : 400000;
        $series = collect(Cache::get('outerprime:v3:catA:ALL:avgPrice'));

        $this->assertSame($expected, (int) ($series->first()->avg_price ?? 0));
    }

    public function test_epc_warmer_runs_with_postgres_safe_sql(): void
    {
        $this->artisan('epc:warm-dashboard')->assertExitCode(0);
    }

    public function test_import_scotland_uses_database_agnostic_import_path(): void
    {
        $directory = storage_path('framework/testing/scotland-epc');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $file = $directory.'/sample.csv';
        file_put_contents(
            $file,
            "REPORT_REFERENCE_NUMBER,POSTCODE,LODGEMENT_DATE\n".
            "Readable Header,Readable Header,Readable Header\n".
            "RRN-1,EH1 1AA,2024-01-15\n"
        );

        $this->artisan("epc:import-scotland {$directory}")->assertExitCode(0);

        $this->assertDatabaseHas('epc_certificates_scotland', [
            'REPORT_REFERENCE_NUMBER' => 'RRN-1',
            'POSTCODE' => 'EH1 1AA',
            'LODGEMENT_DATE' => '2024-01-15',
        ]);
    }

    private function seedPrimeDataset(string $category, string $postcodePrefix): void
    {
        DB::table('prime_postcodes')->insert([
            'postcode' => $postcodePrefix,
            'category' => $category,
            'notes' => 'Test',
        ]);

        DB::table('land_registry')->insert([
            $this->landRegistryRow('11111111-1111-1111-1111-11111111111111', 100000, '2024-01-15 00:00:00', $postcodePrefix.' 1AA'),
            $this->landRegistryRow('22222222-2222-2222-2222-22222222222222', 200000, '2024-02-15 00:00:00', $postcodePrefix.' 2BB'),
            $this->landRegistryRow('33333333-3333-3333-3333-33333333333333', 300000, '2024-03-15 00:00:00', $postcodePrefix.' 3CC'),
            $this->landRegistryRow('44444444-4444-4444-4444-44444444444444', 1000000, '2024-04-15 00:00:00', $postcodePrefix.' 4DD'),
        ]);
    }

    private function landRegistryRow(string $transactionId, int $price, string $date, string $postcode): array
    {
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
