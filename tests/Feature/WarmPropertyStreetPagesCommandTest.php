<?php

namespace Tests\Feature;

use App\Http\Controllers\PropertyStreetController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarmPropertyStreetPagesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureLandRegistryTable();

        DB::table('land_registry')->delete();
        Cache::forget(PropertyStreetController::cacheKey('main-street', 'B79'));
        Cache::forget(PropertyStreetController::cacheKey('high-road', 'B80'));
        Cache::forget(PropertyStreetController::cacheKey('side-lane', 'B81'));
        Cache::forget(PropertyStreetController::cacheKey('market-road', 'SW7'));
        Cache::forget(PropertyStreetController::cacheKey('market-road', 'SW5'));
        Cache::forget('property:street:crime:outcode:sw7');
        Cache::forget('property:street:crime:outcode-point:sw7');
    }

    public function test_command_warms_only_qualifying_street_pages_for_the_requested_threshold(): void
    {
        DB::table('land_registry')->insert([
            $this->saleRow('tx-001', 'MAIN STREET', 'B79 7AA', '2024-01-01 00:00:00'),
            $this->saleRow('tx-002', 'MAIN STREET', 'B79 7AB', '2024-01-02 00:00:00'),
            $this->saleRow('tx-003', 'MAIN STREET', 'B79 7AC', '2024-01-03 00:00:00'),
            $this->saleRow('tx-004', 'MAIN STREET', 'B79 7AD', '2024-01-04 00:00:00'),
            $this->saleRow('tx-005', 'MAIN STREET', 'B79 7AE', '2024-01-05 00:00:00'),
            $this->saleRow('tx-006', 'HIGH ROAD', 'B80 1AA', '2024-01-06 00:00:00'),
            $this->saleRow('tx-007', 'HIGH ROAD', 'B80 1AB', '2024-01-07 00:00:00'),
            $this->saleRow('tx-008', 'HIGH ROAD', 'B80 1AC', '2024-01-08 00:00:00'),
            $this->saleRow('tx-009', 'SIDE LANE', 'B81 1AA', '2024-01-09 00:00:00'),
            $this->saleRow('tx-010', 'SIDE LANE', 'B81 1AB', '2024-01-10 00:00:00'),
            $this->saleRow('tx-011', 'SIDE LANE', 'B81 1AC', '2024-01-11 00:00:00'),
            $this->saleRow('tx-012', 'SIDE LANE', 'B81 1AD', '2024-01-12 00:00:00', 'B'),
        ]);

        $this->artisan('property:street-warm', [
            '--min-sales' => 3,
        ])->assertExitCode(0);

        $mainStreet = Cache::get(PropertyStreetController::cacheKey('main-street', 'B79'));
        $highRoad = Cache::get(PropertyStreetController::cacheKey('high-road', 'B80'));
        $sideLane = Cache::get(PropertyStreetController::cacheKey('side-lane', 'B81'));

        $this->assertIsArray($mainStreet);
        $this->assertSame('MAIN STREET', $mainStreet['street_name']);
        $this->assertSame(5, $mainStreet['summary']['total_sales']);

        $this->assertIsArray($highRoad);
        $this->assertSame(3, $highRoad['summary']['total_sales']);

        $this->assertIsArray($sideLane);
        $this->assertSame(3, $sideLane['summary']['total_sales']);

        Cache::forget(PropertyStreetController::cacheKey('main-street', 'B79'));
        Cache::forget(PropertyStreetController::cacheKey('high-road', 'B80'));
        Cache::forget(PropertyStreetController::cacheKey('side-lane', 'B81'));

        $this->artisan('property:street-warm', [
            '--min-sales' => 5,
        ])->assertExitCode(0);

        $this->assertIsArray(Cache::get(PropertyStreetController::cacheKey('main-street', 'B79')));
        $this->assertNull(Cache::get(PropertyStreetController::cacheKey('high-road', 'B80')));
        $this->assertNull(Cache::get(PropertyStreetController::cacheKey('side-lane', 'B81')));
    }

    public function test_command_can_filter_to_a_single_outcode(): void
    {
        DB::table('land_registry')->insert([
            $this->saleRow('tx-101', 'MARKET ROAD', 'SW7 5AA', '2024-03-01 00:00:00'),
            $this->saleRow('tx-102', 'MARKET ROAD', 'SW7 5AB', '2024-03-02 00:00:00'),
            $this->saleRow('tx-103', 'MARKET ROAD', 'SW7 5AC', '2024-03-03 00:00:00'),
            $this->saleRow('tx-104', 'MARKET ROAD', 'SW7 5AD', '2024-03-04 00:00:00'),
            $this->saleRow('tx-105', 'MARKET ROAD', 'SW7 5AE', '2024-03-05 00:00:00'),
            $this->saleRow('tx-106', 'MARKET ROAD', 'SW5 0AA', '2024-03-06 00:00:00'),
            $this->saleRow('tx-107', 'MARKET ROAD', 'SW5 0AB', '2024-03-07 00:00:00'),
            $this->saleRow('tx-108', 'MARKET ROAD', 'SW5 0AC', '2024-03-08 00:00:00'),
            $this->saleRow('tx-109', 'MARKET ROAD', 'SW5 0AD', '2024-03-09 00:00:00'),
            $this->saleRow('tx-110', 'MARKET ROAD', 'SW5 0AE', '2024-03-10 00:00:00'),
        ]);

        $this->artisan('property:street-warm', [
            '--min-sales' => 5,
            '--outcode' => 'SW7',
        ])->assertExitCode(0);

        $this->assertIsArray(Cache::get(PropertyStreetController::cacheKey('market-road', 'SW7')));
        $this->assertNull(Cache::get(PropertyStreetController::cacheKey('market-road', 'SW5')));
    }

    public function test_command_uses_the_same_trimmed_street_lookup_as_the_public_page(): void
    {
        DB::table('land_registry')->insert([
            $this->saleRow('tx-201', 'MAIN STREET', 'B79 7AA', '2024-04-01 00:00:00'),
            $this->saleRow('tx-202', ' MAIN STREET ', 'B79 7AB', '2024-04-02 00:00:00'),
            $this->saleRow('tx-203', 'MAIN STREET', 'B79 7AC', '2024-04-03 00:00:00'),
            $this->saleRow('tx-204', 'MAIN STREET ', 'B79 7AD', '2024-04-04 00:00:00'),
            $this->saleRow('tx-205', '  MAIN STREET', 'B79 7AE', '2024-04-05 00:00:00'),
        ]);

        $this->artisan('property:street-warm', [
            '--min-sales' => 5,
        ])->assertExitCode(0);

        $mainStreet = Cache::get(PropertyStreetController::cacheKey('main-street', 'B79'));

        $this->assertIsArray($mainStreet);
        $this->assertSame('MAIN STREET', $mainStreet['street_name']);
        $this->assertSame(5, $mainStreet['summary']['total_sales']);
    }

    public function test_refresh_overwrites_existing_payloads_and_successful_full_run_deletes_only_stale_street_pages(): void
    {
        $this->useDatabaseCache();

        DB::table('land_registry')->insert([
            $this->saleRow('tx-301', 'MAIN STREET', 'B79 7AA', '2024-01-01 00:00:00'),
            $this->saleRow('tx-302', 'MAIN STREET', 'B79 7AB', '2024-01-02 00:00:00'),
            $this->saleRow('tx-303', 'MAIN STREET', 'B79 7AC', '2024-01-03 00:00:00'),
            $this->saleRow('tx-304', 'MAIN STREET', 'B79 7AD', '2024-01-04 00:00:00'),
            $this->saleRow('tx-305', 'MAIN STREET', 'B79 7AE', '2024-01-05 00:00:00'),
        ]);

        $currentKey = PropertyStreetController::cacheKey('main-street', 'B79');
        $staleKey = PropertyStreetController::cacheKey('former-road', 'B79');
        $sharedKey = 'property:street:v4:outcode-comparison:b79';
        Cache::put($currentKey, ['stale' => true], 3600);
        Cache::put($staleKey, ['stale' => true], 3600);
        Cache::put($sharedKey, ['shared' => true], 3600);
        Cache::put('unrelated-cache-key', ['keep' => true], 3600);

        $this->artisan('property:street-warm', [
            '--min-sales' => 5,
            '--refresh' => true,
            '--cleanup-stale' => true,
            '--no-progress' => true,
        ])->assertExitCode(0);

        $this->assertSame('MAIN STREET', Cache::get($currentKey)['street_name']);
        $this->assertNull(Cache::get($staleKey));
        $this->assertSame(['shared' => true], Cache::get($sharedKey));
        $this->assertSame(['keep' => true], Cache::get('unrelated-cache-key'));
    }

    public function test_failed_full_run_does_not_delete_stale_street_pages(): void
    {
        $this->useDatabaseCache();

        DB::table('land_registry')->insert([
            $this->saleRow('tx-401', 'MAIN STREET', 'B79 7AA', '2024-01-01 00:00:00'),
            $this->saleRow('tx-402', 'MAIN STREET', 'B79 7AB', '2024-01-02 00:00:00'),
            $this->saleRow('tx-403', 'MAIN STREET', 'B79 7AC', '2024-01-03 00:00:00'),
            $this->saleRow('tx-404', 'MAIN STREET', 'B79 7AD', '2024-01-04 00:00:00'),
            $this->saleRow('tx-405', 'MAIN STREET', 'B79 7AE', '2024-01-05 00:00:00'),
        ]);

        $staleKey = PropertyStreetController::cacheKey('former-road', 'B79');
        Cache::put($staleKey, ['stale' => true], 3600);

        $controller = \Mockery::mock(PropertyStreetController::class);
        $controller->shouldReceive('buildStreetCacheBatch')
            ->once()
            ->andThrow(new \RuntimeException('Deliberate batch failure'));
        $this->app->instance(PropertyStreetController::class, $controller);

        $this->artisan('property:street-warm', [
            '--min-sales' => 5,
            '--refresh' => true,
            '--cleanup-stale' => true,
            '--no-progress' => true,
        ])->assertExitCode(1);

        $this->assertSame(['stale' => true], Cache::get($staleKey));
    }

    public function test_stale_cleanup_is_rejected_for_a_partial_run(): void
    {
        $this->artisan('property:street-warm', [
            '--limit' => 1,
            '--cleanup-stale' => true,
        ])->assertExitCode(1);
    }

    public function test_slug_collisions_preserve_the_public_page_street_resolution(): void
    {
        DB::table('land_registry')->insert([
            $this->saleRow('tx-501', 'MAIN STREET', 'B79 7AA', '2024-01-01 00:00:00'),
            $this->saleRow('tx-502', 'MAIN STREET', 'B79 7AB', '2024-01-02 00:00:00'),
            $this->saleRow('tx-503', 'MAIN STREET', 'B79 7AC', '2024-01-03 00:00:00'),
            $this->saleRow('tx-504', 'MAIN STREET', 'B79 7AD', '2024-01-04 00:00:00'),
            $this->saleRow('tx-505', 'MAIN STREET', 'B79 7AE', '2024-01-05 00:00:00'),
            $this->saleRow('tx-506', 'MAIN-STREET', 'B79 8AA', '2024-02-01 00:00:00'),
            $this->saleRow('tx-507', 'MAIN-STREET', 'B79 8AB', '2024-02-02 00:00:00'),
            $this->saleRow('tx-508', 'MAIN-STREET', 'B79 8AC', '2024-02-03 00:00:00'),
            $this->saleRow('tx-509', 'MAIN-STREET', 'B79 8AD', '2024-02-04 00:00:00'),
            $this->saleRow('tx-510', 'MAIN-STREET', 'B79 8AE', '2024-02-05 00:00:00'),
        ]);

        $this->artisan('property:street-warm', [
            '--min-sales' => 5,
            '--refresh' => true,
            '--no-progress' => true,
        ])->assertExitCode(0);

        $payload = Cache::get(PropertyStreetController::cacheKey('main-street', 'B79'));

        $this->assertSame('MAIN STREET', $payload['street_name']);
        $this->assertSame(5, $payload['summary']['total_sales']);
    }

    private function ensureLandRegistryTable(): void
    {
        if (Schema::hasTable('land_registry')) {
            return;
        }

        Schema::create('land_registry', function (Blueprint $table): void {
            $table->char('TransactionID', 36)->nullable();
            $table->unsignedInteger('Price')->nullable();
            $table->dateTime('Date')->nullable();
            $table->string('Postcode', 10)->nullable();
            $table->enum('PropertyType', ['D', 'S', 'T', 'F', 'O'])->nullable();
            $table->enum('NewBuild', ['Y', 'N'])->nullable();
            $table->enum('Duration', ['F', 'L'])->nullable();
            $table->string('PAON', 100)->nullable();
            $table->string('SAON', 100)->nullable();
            $table->string('Street', 100)->nullable();
            $table->string('Locality', 100)->nullable();
            $table->string('TownCity', 100)->nullable();
            $table->string('District', 100)->nullable();
            $table->string('County', 100)->nullable();
            $table->enum('PPDCategoryType', ['A', 'B'])->nullable();
            $table->char('RecordStatus', 1)->nullable();
        });
    }

    private function useDatabaseCache(): void
    {
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration')->index();
            });
        }

        DB::table('cache')->delete();
        config()->set('cache.default', 'database');
        Cache::setDefaultDriver('database');
    }

    /**
     * @return array<string, int|string|null>
     */
    private function saleRow(
        string $transactionId,
        string $street,
        string $postcode,
        string $date,
        string $category = 'A'
    ): array {
        return [
            'TransactionID' => $transactionId,
            'Price' => 250000,
            'Date' => $date,
            'Postcode' => $postcode,
            'PropertyType' => 'T',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'PAON' => '1',
            'SAON' => null,
            'Street' => $street,
            'Locality' => null,
            'TownCity' => 'Town',
            'District' => 'District',
            'County' => 'County',
            'PPDCategoryType' => $category,
            'RecordStatus' => null,
        ];
    }
}
