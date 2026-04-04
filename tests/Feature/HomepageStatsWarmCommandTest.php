<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomepageStatsWarmCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_homepage_stats_warm_command_caches_stats_and_homepage_panels(): void
    {
        DB::table('hpi_monthly')->insert([
            'AreaCode' => 'K02000001',
            'Date' => '2026-01-01',
            'RegionName' => 'United Kingdom',
            'AveragePrice' => 275500,
        ]);

        DB::table('rental_costs')->insert([
            'time_period' => '2026-01',
            'area_name' => 'United Kingdom',
            'rental_price' => 1450,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('interest_rates')->insert([
            'effective_date' => '2026-02-01',
            'rate' => 4.25,
            'source' => 'BoE Bank Rate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inflation_cpih_monthly')->insert([
            'date' => '2026-02-01',
            'rate' => 3.4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('land_registry')->insert([
            ...$this->countySalesRows('LON', 12000000, '2025-08-15 00:00:00', 30, 'Belgrave Square', 'GREATER LONDON'),
            ...$this->countySalesRows('LONC', 14000000, '2025-11-15 00:00:00', 30, 'Belgrave Square', 'GREATER LONDON'),
            ...$this->countySalesRows('ROY', 3500000, '2025-08-12 00:00:00', 30, 'Park Row', 'West Yorkshire'),
            ...$this->countySalesRows('ROYC', 3800000, '2025-11-12 00:00:00', 25, 'Park Row', 'West Yorkshire'),
        ]);

        DB::table('market_insights')->insert([
            [
                'area_type' => 'postcode_sector',
                'area_code' => 'M1',
                'insight_type' => 'demand_collapse',
                'metric_value' => -33.3,
                'transactions' => 20,
                'period_start' => '2025-02-01',
                'period_end' => '2026-01-31',
                'supporting_data' => json_encode(['transaction_change' => -33.3]),
                'insight_text' => 'Property transactions in M1 fell sharply.',
                'created_at' => Carbon::create(2026, 3, 15, 9, 45),
                'updated_at' => Carbon::create(2026, 3, 15, 9, 45),
            ],
            [
                'area_type' => 'postcode_sector',
                'area_code' => 'SW1',
                'insight_type' => 'unexpected_hotspot',
                'metric_value' => 6.4,
                'transactions' => 24,
                'period_start' => '2025-02-01',
                'period_end' => '2026-01-31',
                'supporting_data' => json_encode(['price_change' => 6.4]),
                'insight_text' => 'Prices in SW1 are outperforming nearby sectors.',
                'created_at' => Carbon::create(2026, 3, 17, 12, 0),
                'updated_at' => Carbon::create(2026, 3, 17, 12, 0),
            ],
        ]);

        $this->artisan('home:stats-warm')
            ->expectsOutput('Warming homepage stats cache...')
            ->expectsOutput('→ homepage_stats cached for 30 days')
            ->expectsOutput('→ homepage_panels cached for 30 days')
            ->expectsOutput('Homepage stats cache warmed successfully.')
            ->assertExitCode(0);

        $stats = Cache::get('homepage_stats');
        $panels = Cache::get('homepage_panels');

        $this->assertSame(115, $stats['property_records']);
        $this->assertSame(275500, $stats['uk_avg_price']);
        $this->assertSame(1450, $stats['uk_avg_rent']);
        $this->assertSame(4.25, (float) $stats['bank_rate']);
        $this->assertSame(3.4, (float) $stats['inflation_rate']);

        $this->assertSame(2, $panels['marketInsightsCount']);
        $this->assertSame(2, $panels['liveSignalsCount']);
        $this->assertSame('Demand Collapse', $panels['topSignal']['type']);
        $this->assertSame('M1', $panels['topSignal']['postcode']);
        $this->assertSame(-33.3, $panels['topSignal']['change']);
        $this->assertSame(-8.3, $panels['homepageMarketMovements']['transaction_change_percent']);
        $this->assertSame(20.8, $panels['homepageMarketMovements']['median_price_change_percent']);
        $this->assertSame(2, $panels['homepageMarketMovements']['rising_price_counties']);
        $this->assertSame(1, $panels['homepageMarketMovements']['declining_counties']);
        $this->assertSame('Ultra Prime London', $panels['homepageTopSales'][0]['label']);
        $this->assertSame(14000000, (int) $panels['homepageTopSales'][0]['sale']->Price);
        $this->assertSame('Rest of UK', $panels['homepageTopSales'][2]['label']);
        $this->assertSame(3800000, (int) $panels['homepageTopSales'][2]['sale']->Price);
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    private function countySalesRows(
        string $prefix,
        int $price,
        string $date,
        int $count,
        string $street,
        string $county
    ): array {
        $rows = [];

        for ($index = 1; $index <= $count; $index++) {
            $rows[] = $this->landRegistryRow(
                sprintf('%s-%04d-aaaa-bbbb-cccccccccccc', $prefix, $index),
                $price,
                $date,
                $street,
                $county,
                $index
            );
        }

        return $rows;
    }

    private function landRegistryRow(
        string $transactionId,
        int $price,
        string $date,
        string $street,
        string $county,
        int $paon = 1
    ): array {
        return [
            'TransactionID' => $transactionId,
            'Price' => $price,
            'Date' => $date,
            'Postcode' => $county === 'GREATER LONDON' ? 'SW1X 8PP' : 'LS1 5HD',
            'PropertyType' => 'D',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'PAON' => (string) $paon,
            'SAON' => null,
            'Street' => $street,
            'TownCity' => $county === 'GREATER LONDON' ? 'London' : 'Leeds',
            'District' => $county === 'GREATER LONDON' ? 'Westminster' : 'Leeds',
            'County' => $county,
            'PPDCategoryType' => 'A',
        ];
    }
}
