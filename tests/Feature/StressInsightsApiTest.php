<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StressInsightsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-25 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_returns_the_complete_stress_dashboard_response(): void
    {
        $this->insertDashboardData();

        $response = $this->getJson(route('api.v1.insights.stress', absolute: false));

        $response
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'title',
                'description',
                'last_updated',
                'score' => [
                    'value',
                    'maximum',
                    'raw_value',
                    'raw_maximum',
                    'status',
                    'status_label',
                    'description',
                ],
                'indicators' => ['*' => [
                    'key',
                    'title',
                    'description',
                    'value',
                    'secondary_value',
                    'unit',
                    'period',
                    'bad_streak',
                    'status',
                    'status_label',
                    'score',
                    'maximum_score',
                    'api_url',
                    'website_url',
                ]],
                'website_url',
            ]])
            ->assertJsonCount(8, 'data.indicators')
            ->assertJsonPath('data.last_updated', '2026-06-01')
            ->assertJsonPath('data.score.maximum', 100)
            ->assertJsonPath('data.score.raw_maximum', 24)
            ->assertJsonPath('data.website_url', route('economic.dashboard'));

        $this->assertContains($response->json('data.score.status'), ['low', 'amber', 'red', 'dark_red']);
        $this->assertIsInt($response->json('data.score.value'));
        $this->assertIsInt($response->json('data.score.raw_value'));
    }

    public function test_it_returns_all_indicators_in_website_order_with_correct_urls(): void
    {
        $response = $this->getJson(route('api.v1.insights.stress', absolute: false));

        $this->assertSame([
            'mortgage_approvals',
            'house_price_index',
            'interest_rates',
            'inflation',
            'wage_growth',
            'unemployment',
            'mortgage_arrears',
            'repossessions',
        ], $response->json('data.indicators.*.key'));

        $this->assertSame([
            route('mortgages.home'),
            route('hpi.home'),
            route('interest.home'),
            route('inflation.home'),
            route('wagegrowth.home'),
            route('unemployment.home'),
            route('arrears.index'),
            route('repossessions.index'),
        ], $response->json('data.indicators.*.website_url'));

        $this->assertSame(array_fill(0, 8, null), $response->json('data.indicators.*.api_url'));
    }

    public function test_it_handles_an_empty_dataset_with_nullable_values(): void
    {
        $response = $this->getJson(route('api.v1.insights.stress', absolute: false));

        $response
            ->assertOk()
            ->assertJsonPath('data.last_updated', null)
            ->assertJsonPath('data.indicators.0.value', null)
            ->assertJsonPath('data.indicators.0.period', null)
            ->assertJsonPath('data.indicators.4.secondary_value', null)
            ->assertJsonPath('data.indicators.7.value', null);

        $this->assertSame(array_fill(0, 8, null), $response->json('data.indicators.*.value'));
        $this->assertSame(array_fill(0, 8, 'amber'), $response->json('data.indicators.*.status'));
    }

    public function test_it_keeps_unavailable_values_nullable_in_a_partial_dataset(): void
    {
        DB::table('interest_rates')->insert([
            'effective_date' => '2026-06-01',
            'rate' => 4.25,
        ]);

        $response = $this->getJson(route('api.v1.insights.stress', absolute: false));

        $response
            ->assertOk()
            ->assertJsonPath('data.indicators.0.value', null)
            ->assertJsonPath('data.indicators.2.value', 4.25)
            ->assertJsonPath('data.indicators.2.period', '1 Jun 2026')
            ->assertJsonPath('data.indicators.6.value', null);
    }

    private function insertDashboardData(): void
    {
        foreach (range(1, 6) as $month) {
            $date = sprintf('2026-%02d-01', $month);

            DB::table('mortgage_approvals')->insert([
                'series_code' => 'LPMVTVX',
                'period' => $date,
                'value' => 60000 + ($month * 1000),
                'unit' => 'count',
                'source' => 'BoE',
            ]);
            DB::table('hpi_monthly')->insert([
                'AreaCode' => 'K02000001',
                'Date' => $date,
                'RegionName' => 'United Kingdom',
                'AveragePrice' => 280000 + ($month * 1000),
            ]);
            DB::table('inflation_cpih_monthly')->insert(['date' => $date, 'rate' => 3.0]);
            DB::table('wage_growth_monthly')->insert(['date' => $date, 'three_month_avg_yoy' => 4.0]);
            DB::table('unemployment_monthly')->insert(['date' => $date, 'three_month' => 4.2]);
        }

        DB::table('interest_rates')->insert([
            ['effective_date' => '2026-01-01', 'rate' => 4.5],
            ['effective_date' => '2026-06-01', 'rate' => 4.25],
        ]);

        foreach ([['2026', 'Q1', 1.0], ['2026', 'Q2', 1.1]] as [$year, $quarter, $value]) {
            DB::table('mlar_arrears')->insert([
                ['year' => $year, 'quarter' => $quarter, 'description' => '2.5 < 5% in arrears', 'value' => $value],
                ['year' => $year, 'quarter' => $quarter, 'description' => 'In possession', 'value' => 0.05],
            ]);
        }
    }
}
