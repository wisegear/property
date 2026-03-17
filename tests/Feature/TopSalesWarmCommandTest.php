<?php

namespace Tests\Feature;

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
    }

    public function test_top_sales_warm_command_primes_all_mode_caches(): void
    {
        DB::table('land_registry')->insert([
            $this->landRegistryRow('11111111-1111-1111-1111-111111111111', 12000000, '2025-01-15 00:00:00', 'SW1A 1AA', '10', null, 'Downing Street', 'GREATER LONDON'),
            $this->landRegistryRow('22222222-2222-2222-2222-222222222222', 9000000, '2025-01-14 00:00:00', 'W1K 1AB', '20', 'Flat 5', 'Park Lane', 'GREATER LONDON'),
            $this->landRegistryRow('33333333-3333-3333-3333-333333333333', 3000000, '2025-01-13 00:00:00', 'SW3 1AA', '30', null, 'Cheyne Walk', 'GREATER LONDON'),
            $this->landRegistryRow('44444444-4444-4444-4444-444444444444', 2800000, '2025-01-12 00:00:00', 'M1 1AA', '40', null, 'Deansgate', 'Greater Manchester'),
            $this->landRegistryRow('55555555-5555-5555-5555-555555555555', 2400000, '2025-01-11 00:00:00', 'LS1 1AA', '50', null, 'Park Row', 'West Yorkshire'),
        ]);

        $this->artisan('property:top-sales-warm')
            ->expectsOutput('Warming top property sales...')
            ->expectsOutput('Warmed ultra')
            ->expectsOutput('Warmed london')
            ->expectsOutput('Warmed rest')
            ->expectsOutput('Done.')
            ->assertExitCode(0);

        $ultra = Cache::get('top_sales:ultra');
        $london = Cache::get('top_sales:london');
        $rest = Cache::get('top_sales:rest');

        $this->assertNotNull($ultra);
        $this->assertNotNull($london);
        $this->assertNotNull($rest);
        $this->assertCount(1, $ultra);
        $this->assertCount(2, $london);
        $this->assertCount(1, $rest);
        $this->assertSame(12000000, (int) $ultra->first()->Price);
        $this->assertSame(9000000, (int) $london->first()->Price);
        $this->assertSame(2800000, (int) $rest->first()->Price);
        $this->assertSame('sw1a-1aa-10-downing-street', $ultra->first()->property_slug);
        $this->assertNotNull(Cache::get('top_sales:last_warmed_at'));
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
