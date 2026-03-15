<?php

namespace Tests\Feature;

use App\Models\MarketInsight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomePagePostcodeQuickSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_home_page_shows_market_stress_panel_above_quick_postcode_search_form(): void
    {
        $view = $this->view('pages.home', [
            'posts' => collect(),
            'stats' => [
                'property_records' => 0,
                'uk_avg_price' => 0,
                'uk_avg_rent' => 0,
                'bank_rate' => 0,
                'inflation_rate' => 0,
                'epc_count' => 0,
            ],
            'totalStress' => 10,
            'marketInsightsCount' => 128,
            'marketInsightsLastRunAt' => Carbon::create(2026, 3, 15, 8, 30),
            'marketInsightSignalCount' => 9,
            'homepageMarketMovements' => [
                'rising_price_counties' => 18,
                'total_counties' => 112,
            ],
        ]);

        $searchUrl = route('property.search', absolute: false);

        $view->assertSee('Quick postcode search');
        $view->assertSee($searchUrl, false);
        $view->assertSee('name="postcode"', false);
        $view->assertSee('placeholder="e.g. SW7 5PH"', false);
        $view->assertSee('Latest Market Movements');
        $view->assertSee('Counties with Falling Sales');
        $view->assertSee('Counties with Rising Prices');
        $view->assertSee('text-green-600', false);
        $view->assertSee('18');
        $view->assertSee('/ 112');
        $view->assertSee('border-zinc-200 bg-zinc-50', false);
        $view->assertSee('▼ -34.1%', false);
        $view->assertSee('Transaction Volume Change');
        $view->assertSee('154,923 vs 102,104');
        $view->assertSee('Median Price £ Change');
        $view->assertSee('£290,693');
        $view->assertSee('£290,000');
        $view->assertSee('Top declining counties');
        $view->assertSee('Torfaen');
        $view->assertSee('Portsmouth');
        $view->assertSee('Slough');
        $view->assertSee('Signals worth watching');
        $view->assertSee('128 live');
        $view->assertSee('9 signal types');
        $view->assertSee('Open Insights');
        $view->assertSee('md:grid-cols-2 lg:grid-cols-3', false);
        $view->assertSee('flex h-full flex-col', false);
        $view->assertSeeInOrder(['Latest Market Movements', 'Quick postcode search', 'Signals worth watching']);
    }

    public function test_home_page_displays_market_insight_count_and_latest_update_from_database(): void
    {
        MarketInsight::query()->create([
            'area_type' => 'postcode_sector',
            'area_code' => 'NW8',
            'insight_type' => 'price_spike',
            'metric_value' => 18.2,
            'transactions' => 20,
            'period_start' => '2025-02-01',
            'period_end' => '2026-01-31',
            'supporting_data' => ['price_change' => 18.2],
            'insight_text' => 'Median property prices in NW8 rose sharply.',
            'created_at' => Carbon::create(2026, 3, 12, 10, 0),
            'updated_at' => Carbon::create(2026, 3, 12, 10, 0),
        ]);

        MarketInsight::query()->create([
            'area_type' => 'postcode_sector',
            'area_code' => 'M1',
            'insight_type' => 'demand_collapse',
            'metric_value' => -33.3,
            'transactions' => 20,
            'period_start' => '2025-02-01',
            'period_end' => '2026-01-31',
            'supporting_data' => ['transaction_change' => -33.3],
            'insight_text' => 'Property transactions in M1 fell sharply.',
            'created_at' => Carbon::create(2026, 3, 15, 9, 45),
            'updated_at' => Carbon::create(2026, 3, 15, 9, 45),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('2 live');
        $response->assertSee('9 signal types');
    }
}
