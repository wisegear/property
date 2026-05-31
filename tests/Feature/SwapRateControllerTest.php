<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SwapRateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_swap_rates_page_renders_latest_cards_and_chart_data(): void
    {
        DB::table('interest_rates')->insert([
            [
                'effective_date' => '2025-12-01',
                'rate' => 4.75,
                'source' => 'BoE Bank Rate',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'effective_date' => '2025-12-10',
                'rate' => 4.50,
                'source' => 'BoE Bank Rate',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('swap_rates')->insert([
            [
                'rate_date' => '2025-12-09',
                'curve_type' => 'ois',
                'term_years' => 2,
                'rate' => 4.0000,
                'daily_change' => null,
                'source' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rate_date' => '2025-12-09',
                'curve_type' => 'ois',
                'term_years' => 5,
                'rate' => 4.2000,
                'daily_change' => null,
                'source' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rate_date' => '2025-12-10',
                'curve_type' => 'ois',
                'term_years' => 2,
                'rate' => 4.1000,
                'daily_change' => 0.1000,
                'source' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rate_date' => '2025-12-10',
                'curve_type' => 'ois',
                'term_years' => 5,
                'rate' => 4.3000,
                'daily_change' => 0.1000,
                'source' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rate_date' => '2025-12-10',
                'curve_type' => 'ois',
                'term_years' => 10,
                'rate' => 4.4500,
                'daily_change' => 0.0500,
                'source' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get(route('insights.swap-rates', absolute: false));

        $response->assertOk();
        $response->assertViewIs('insights.swap-rates');
        $response->assertSee('UK Swap Rates');
        $response->assertSee('2Y Swap');
        $response->assertSee('5Y Swap');
        $response->assertSee('10Y Swap');
        $response->assertSee('As at: 10 Dec 2025');
        $response->assertSee('Latest available date:');
        $response->assertSee('assets/images/site/swap-rates.jpg', false);
        $response->assertSee('On the latest trading day, 2Y, 5Y and 10Y swap rates all moved higher.');
        $response->assertSee('UK swap rates over time');
        $response->assertSee('Bank Rate vs swap rates');
        $response->assertSee('Current UK Swap Rates');
        $response->assertSee('52 week range');
        $response->assertDontSee('10Y minus 2Y');
        $response->assertSee('Swap Rates');
        $response->assertSee('4.00% – 4.10%');
        $response->assertSee('4.20% – 4.30%');
        $response->assertSee('4.45% – 4.45%');
        $response->assertSee('Updated daily on UK business days when new Bank of England data is available.');
        $response->assertViewHas('latestAvailableDate', fn ($date): bool => $date?->toDateString() === '2025-12-10');
        $response->assertViewHas('latestRates', function (array $latestRates): bool {
            return round((float) $latestRates[2]->rate, 4) === 4.1000
                && round((float) $latestRates[5]->rate, 4) === 4.3000
                && round((float) $latestRates[10]->rate, 4) === 4.4500;
        });
        $response->assertViewHas('rateRanges', function (array $rateRanges): bool {
            return $rateRanges[2]['low'] === 4.0
                && $rateRanges[2]['high'] === 4.1
                && $rateRanges[5]['low'] === 4.2
                && $rateRanges[5]['high'] === 4.3
                && $rateRanges[10]['low'] === 4.45
                && $rateRanges[10]['high'] === 4.45;
        });
        $response->assertViewHas('currentRatesTable', function (array $rows): bool {
            return $rows[0]['term'] === '2 Year'
                && $rows[0]['rate'] === 4.1
                && $rows[1]['term'] === '5 Year'
                && $rows[2]['term'] === '10 Year';
        });
        $response->assertViewHas('rateChart', function (array $rateChart): bool {
            return $rateChart['datasets'][0]['label'] === '2Y Swap'
                && $rateChart['datasets'][1]['label'] === '5Y Swap'
                && $rateChart['datasets'][2]['label'] === '10Y Swap';
        });
        $response->assertViewHas('latestMovementSummary', function (?array $summary): bool {
            return $summary !== null
                && $summary['text'] === 'On the latest trading day, 2Y, 5Y and 10Y swap rates all moved higher.'
                && $summary['direction'] === 'higher';
        });
        $response->assertViewHas('bankRateComparisonChart', function (?array $chart): bool {
            return $chart !== null
                && $chart['datasets'][0]['label'] === 'Bank Rate'
                && $chart['datasets'][0]['data'] === [4.75, 4.5]
                && $chart['datasets'][1]['label'] === '2Y Swap'
                && $chart['datasets'][1]['data'] === [4.0, 4.1]
                && $chart['datasets'][2]['label'] === '5Y Swap'
                && $chart['datasets'][2]['data'] === [4.2, 4.3];
        });
    }

    public function test_swap_rates_page_handles_missing_term_data_without_showing_global_no_data_text(): void
    {
        DB::table('swap_rates')->insert([
            [
                'rate_date' => '2025-12-10',
                'curve_type' => 'ois',
                'term_years' => 2,
                'rate' => 4.1000,
                'daily_change' => -0.0390,
                'source' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rate_date' => '2025-12-10',
                'curve_type' => 'ois',
                'term_years' => 5,
                'rate' => 4.3000,
                'daily_change' => -0.0330,
                'source' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get(route('insights.swap-rates', absolute: false));

        $response->assertOk();
        $response->assertSee('Latest available date:');
        $response->assertSee('10Y Swap');
        $response->assertSee('As at: 10 Dec 2025');
        $response->assertSee('No data available');
        $response->assertSee('4.10% – 4.10%');
        $response->assertSee('4.30% – 4.30%');
        $response->assertSee('On the latest trading day, 2Y and 5Y swap rates all moved lower.');
        $response->assertViewHas('latestRates', function (array $latestRates): bool {
            return isset($latestRates[2], $latestRates[5]) && ! isset($latestRates[10]);
        });
    }
}
