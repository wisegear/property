<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TopSalesWarmCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        Carbon::setTestNow('2025-08-19 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_top_sales_warm_command_primes_the_latest_high_value_dashboard_cache(): void
    {
        DB::table('land_registry')->insert([
            $this->landRegistryRow('11111111-1111-1111-1111-111111111111', 12000000, '2025-01-15 00:00:00', 'SW1A 1AA', '10', null, 'Downing Street', 'GREATER LONDON'),
            $this->landRegistryRow('22222222-2222-2222-2222-222222222222', 9000000, '2025-01-14 00:00:00', 'W1K 1AB', '20', 'Flat 5', 'Park Lane', 'GREATER LONDON'),
            $this->landRegistryRow('33333333-3333-3333-3333-333333333333', 3000000, '2025-01-13 00:00:00', 'SW3 1AA', '30', null, 'Cheyne Walk', 'GREATER LONDON'),
            $this->landRegistryRow('44444444-4444-4444-4444-444444444444', 2800000, '2025-01-12 00:00:00', 'M1 1AA', '40', null, 'Deansgate', 'Greater Manchester'),
            $this->landRegistryRow('55555555-5555-5555-5555-555555555555', 2400000, '2025-01-11 00:00:00', 'LS1 1AA', '50', null, 'Park Row', 'West Yorkshire'),
            $this->landRegistryRow('66666666-6666-6666-6666-666666666666', 1800000, '2025-02-11 00:00:00', 'LS1 1AA', '60', null, 'Park Row', 'West Yorkshire'),
            $this->landRegistryRow('77777777-7777-7777-7777-777777777777', 1700000, '2024-12-11 00:00:00', 'LS1 1AA', '70', null, 'Park Row', 'West Yorkshire'),
        ]);

        $this->artisan('property:top-sales-warm')
            ->expectsOutput('Warming High Value Property dashboard cache...')
            ->expectsOutput('Warmed January 2025')
            ->expectsOutput('Warmed February 2025')
            ->expectsOutput('High Value Property cache warming complete (2 months).')
            ->assertExitCode(0);

        $dashboard = Cache::get('property:high-value:v2:202501');

        $this->assertIsArray($dashboard);
        $this->assertSame(12000000, $dashboard['threshold']);
        $this->assertSame(1, $dashboard['headline']['sales']);
        $this->assertSame('sw1a-1aa-10-downing-street', $dashboard['topSales'][0]['property_slug']);
        $this->assertNotNull(Cache::get('property:high-value:v2:202502'));
        $this->assertNull(Cache::get('property:high-value:v2:202412'));
        $this->assertNotNull(Cache::get('property:high-value:last-warmed-at'));
    }

    public function test_top_sales_warm_command_can_warm_a_specific_archive_month(): void
    {
        DB::table('land_registry')->insert([
            $this->landRegistryRow('11111111-1111-1111-1111-111111111111', 1000000, '2025-01-15 00:00:00', 'SW1A 1AA', '10', null, 'Downing Street', 'GREATER LONDON'),
            $this->landRegistryRow('22222222-2222-2222-2222-222222222222', 2000000, '2025-02-15 00:00:00', 'W1K 1AB', '20', null, 'Park Lane', 'GREATER LONDON'),
        ]);

        $this->artisan('property:top-sales-warm', ['--year' => '2025', '--month' => '1'])
            ->expectsOutput('Warmed January 2025')
            ->assertSuccessful();

        $this->assertNotNull(Cache::get('property:high-value:v2:202501'));
        $this->assertNull(Cache::get('property:high-value:v2:202502'));
    }

    public function test_top_sales_warm_command_rejects_a_month_outside_the_current_year(): void
    {
        DB::table('land_registry')->insert(
            $this->landRegistryRow('11111111-1111-1111-1111-111111111111', 1000000, '2024-01-15 00:00:00', 'SW1A 1AA', '10', null, 'Downing Street', 'GREATER LONDON')
        );

        $this->artisan('property:top-sales-warm', ['--year' => '2024', '--month' => '1'])
            ->expectsOutput('The warmer only supports months in the current year.')
            ->assertFailed();

        $this->assertNull(Cache::get('property:high-value:v2:202401'));
    }

    private function landRegistryRow(
        string $transactionId,
        int $price,
        string $date,
        string $postcode,
        string $paon,
        ?string $saon,
        string $street,
        string $county = 'London'
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
            'TownCity' => 'London',
            'County' => $county,
            'PPDCategoryType' => 'A',
        ];
    }
}
