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
        Carbon::setTestNow(Carbon::create(2026, 6, 30, 12));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_homepage_stats_warm_command_caches_stats_and_homepage_panels(): void
    {
        DB::table('hpi_monthly')->insert([
            'AreaCode' => 'K02000001',
            'Date' => '2026-01-01',
            'RegionName' => 'United Kingdom',
            'AveragePrice' => 275500,
            '12m%Change' => 4.2,
        ]);

        DB::table('rental_costs')->insert([
            'time_period' => '2026-01',
            'area_name' => 'United Kingdom',
            'rental_price' => 1450,
            'annual_change' => 3.1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('interest_rates')->insert([
            [
                'effective_date' => '2024-12-01',
                'rate' => 6.25,
                'source' => 'BoE Bank Rate',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'effective_date' => '2025-08-01',
                'rate' => 5.75,
                'source' => 'BoE Bank Rate',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'effective_date' => '2026-02-01',
                'rate' => 4.25,
                'source' => 'BoE Bank Rate',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('inflation_cpih_monthly')->insert([
            'date' => '2026-02-01',
            'rate' => 3.4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('swap_rates')->insert([
            [
                'rate_date' => '2026-03-27',
                'curve_type' => 'ois',
                'term_years' => 2,
                'rate' => 4.1000,
                'daily_change' => -0.0200,
                'source' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rate_date' => '2026-03-31',
                'curve_type' => 'ois',
                'term_years' => 2,
                'rate' => 4.0700,
                'daily_change' => -0.0390,
                'source' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rate_date' => '2026-03-31',
                'curve_type' => 'ois',
                'term_years' => 5,
                'rate' => 4.0900,
                'daily_change' => -0.0330,
                'source' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rate_date' => '2026-03-31',
                'curve_type' => 'ois',
                'term_years' => 10,
                'rate' => 4.3800,
                'daily_change' => 0.0450,
                'source' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('land_registry')->insert([
            ...$this->countySalesRows('LON', 12000000, '2025-08-15 00:00:00', 30, 'Belgrave Square', 'GREATER LONDON'),
            ...$this->countySalesRows('LONC', 14000000, '2025-11-15 00:00:00', 30, 'Belgrave Square', 'GREATER LONDON'),
            ...$this->countySalesRows('ROY', 3500000, '2025-08-12 00:00:00', 30, 'Park Row', 'West Yorkshire'),
            ...$this->countySalesRows('ROYC', 3800000, '2025-11-12 00:00:00', 25, 'Park Row', 'West Yorkshire'),
            ...$this->countySalesRows('LOND', 15000000, '2026-01-15 00:00:00', 30, 'Belgrave Square', 'GREATER LONDON'),
            ...$this->countySalesRows('ROYD', 4200000, '2026-03-12 00:00:00', 30, 'Park Row', 'West Yorkshire'),
        ]);

        DB::table('epc_certificates')->insert([
            'LMK_KEY' => 'ew-2026-homepage-stat',
            'LODGEMENT_DATE' => '2026-01-15',
        ]);

        DB::table('epc_certificates_scotland')->insert([
            'LODGEMENT_DATE' => '2026-02-15',
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

        $this->assertSame(175, $stats['property_records']);
        $this->assertSame(275500, $stats['uk_avg_price']);
        $this->assertSame(1450, $stats['uk_avg_rent']);
        $this->assertSame(4.25, (float) $stats['bank_rate']);
        $this->assertSame(3.4, (float) $stats['inflation_rate']);
        $this->assertSame(2, $stats['epc_count']);

        $this->assertSame(2, $panels['marketInsightsCount']);
        $this->assertSame(2, $panels['liveSignalsCount']);
        $this->assertSame('Demand Collapse', $panels['topSignal']['type']);
        $this->assertSame('M1', $panels['topSignal']['postcode']);
        $this->assertSame(-33.3, $panels['topSignal']['change']);
        $this->assertSame('↑ 60 this year', $panels['homepageStatMovements']['property_records']['change']);
        $this->assertSame('↑ 2 this year', $panels['homepageStatMovements']['epc_count']['change']);
        $this->assertSame('↑ 4.2% YoY', $panels['homepageStatMovements']['uk_avg_price']['change']);
        $this->assertSame('↑ 3.1% YoY', $panels['homepageStatMovements']['uk_avg_rent']['change']);
        $this->assertSame('↓ 1.50pp from peak', $panels['homepageStatMovements']['bank_rate']['change']);
        $this->assertSame(9.1, $panels['homepageMarketMovements']['transaction_change_percent']);
        $this->assertSame(2.5, $panels['homepageMarketMovements']['median_price_change_percent']);
        $this->assertSame(2, $panels['homepageMarketMovements']['rising_price_counties']);
        $this->assertSame(0, $panels['homepageMarketMovements']['declining_counties']);
        $this->assertSame('2026-03-31', $panels['homepageSwapRates']['latestAvailableDate']->toDateString());
        $this->assertSame('2Y Swap', $panels['homepageSwapRates']['rates'][0]['label']);
        $this->assertSame(4.07, $panels['homepageSwapRates']['rates'][0]['rate']);
        $this->assertSame(-3.9, $panels['homepageSwapRates']['rates'][0]['daily_change']);
        $this->assertSame('10Y Swap', $panels['homepageSwapRates']['rates'][2]['label']);
        $this->assertSame(4.38, $panels['homepageSwapRates']['rates'][2]['rate']);
        $this->assertSame(4.5, $panels['homepageSwapRates']['rates'][2]['daily_change']);
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
