<?php

namespace Tests\Feature;

use App\Models\MarketInsight;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomePagePostcodeQuickSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_home_page_shows_quick_postcode_search_above_market_stress_panel_and_links_to_swap_rates_from_the_panel_grid(): void
    {
        $view = $this->view('pages.home', [
            'posts' => collect(),
            'stats' => [
                'property_records' => 31092167,
                'uk_avg_price' => 268421,
                'uk_avg_rent' => 1371,
                'bank_rate' => 3.75,
                'inflation_rate' => 3.2,
                'epc_count' => 30720499,
            ],
            'totalStress' => 10,
            'marketInsightsCount' => 128,
            'marketInsightsLastRunAt' => Carbon::create(2026, 3, 15, 8, 30),
            'marketInsightSignalCount' => 9,
            'liveSignalsCount' => 128,
            'signalTypesCount' => 9,
            'topSignal' => [
                'type' => 'Demand Collapse',
                'postcode' => 'M1',
                'change' => -33.3,
                'direction' => 'down',
                'color' => 'text-red-600',
            ],
            'homepageStatMovements' => [
                'property_records' => ['change' => '↑ 184k this year', 'tone' => 'positive'],
                'epc_count' => ['change' => '↑ 412k this year', 'tone' => 'positive'],
                'uk_avg_price' => ['change' => '↑ 4.2% YoY', 'tone' => 'positive'],
                'uk_avg_rent' => ['change' => '↑ 3.1% YoY', 'tone' => 'positive'],
                'bank_rate' => ['change' => '↓ 1.50% over 12 months', 'tone' => 'positive'],
            ],
            'homepageMarketMovements' => [
                'transaction_change_percent' => -34.1,
                'median_price_change_percent' => -0.2,
                'rising_price_counties' => 18,
                'declining_counties' => 112,
                'total_counties' => 112,
                'top_declining_counties' => collect([
                    ['county' => 'Torfaen', 'sales_change_percent' => -47.4],
                    ['county' => 'Portsmouth', 'sales_change_percent' => -46.1],
                    ['county' => 'Slough', 'sales_change_percent' => -44.7],
                ]),
                'top_rising_price_counties' => collect([
                    ['county' => 'Rutland', 'price_change_percent' => 6.8],
                    ['county' => 'Merseyside', 'price_change_percent' => 5.4],
                    ['county' => 'Bedfordshire', 'price_change_percent' => 4.9],
                ]),
            ],
            'homepageSwapRates' => [
                'latestAvailableDate' => Carbon::create(2026, 5, 28),
                'rates' => [
                    ['term' => 2, 'label' => '2Y Swap', 'rate' => 4.07, 'daily_change' => -3.9, 'rate_date' => Carbon::create(2026, 5, 28)],
                    ['term' => 5, 'label' => '5Y Swap', 'rate' => 4.09, 'daily_change' => -3.3, 'rate_date' => Carbon::create(2026, 5, 28)],
                    ['term' => 10, 'label' => '10Y Swap', 'rate' => 4.38, 'daily_change' => 4.5, 'rate_date' => Carbon::create(2026, 5, 28)],
                ],
            ],
        ]);

        $searchUrl = route('property.search', absolute: false);

        $view->assertSee($searchUrl, false);
        $view->assertSee('name="postcode"', false);
        $view->assertSee('placeholder="Search postcode (e.g. SW7 5PH)"', false);
        $view->assertSee('Search by street');
        $view->assertSee('id="home-street-search"', false);
        $view->assertSee('id="home-street-suggestions"', false);
        $view->assertSee('Search by street', false);
        $view->assertSee('property_streets.json', false);
        $view->assertSee('streetMatchRank', false);
        $view->assertSee('formatStreetSuggestionLabel', false);
        $view->assertSee('Number(right.sales_count || 0) - Number(left.sales_count || 0)', false);
        $view->assertSee('.slice(0, 12)', false);
        $view->assertSee('window.location.href = item.url', false);
        $view->assertSee('only returns results where at least 3 sales exist');
        $view->assertSee('mx-auto grid max-w-5xl gap-4 md:grid-cols-2', false);
        $view->assertSee('rounded-lg bg-zinc-900 px-5 py-2 text-sm text-white transition hover:bg-black', false);
        $view->assertSee('Jump straight to full property data for any postcode in England &amp; Wales', false);
        $view->assertSee('31.1M');
        $view->assertSee('30.7M');
        $view->assertSee('£268,421');
        $view->assertSee('£1,371');
        $view->assertSee('3.75%');
        $view->assertSee('Property sales');
        $view->assertSee('EPC certificates');
        $view->assertSee('Average House Price');
        $view->assertSee('Average UK rent');
        $view->assertSee('Bank Rate');
        $view->assertSee('↑ 184k this year');
        $view->assertSee('↑ 412k this year');
        $view->assertSee('↑ 4.2% YoY');
        $view->assertSee('↑ 3.1% YoY');
        $view->assertSee('↓ 1.50% over 12 months');
        $view->assertSee('hover:-translate-y-0.5 hover:shadow-md', false);
        $view->assertSee('aria-hidden="true"', false);
        $view->assertSee('min-h-[132px] rounded-xl border border-slate-200 bg-white p-5 shadow-sm', false);
        $view->assertDontSee('Live dataset');
        $view->assertDontSee('Coverage expanding');
        $view->assertDontSee('Latest UK HPI');
        $view->assertDontSee('Latest rent index');
        $view->assertDontSee('Latest CPIH');
        $view->assertSee('lg:grid-cols-5', false);
        $view->assertDontSee('animateValue', false);
        $view->assertDontSee('requestAnimationFrame', false);
        $view->assertDontSee('x-text=', false);
        $view->assertSee('UK Swap Rates');
        $view->assertSee('Open Swap Rates');
        $view->assertSee(route('insights.swap-rates', absolute: false), false);
        $view->assertSee('View current UK swap rates and follow the wholesale pricing moves that influence fixed mortgage costs.');
        $view->assertDontSee('Most Expensive Property Sales');
        $view->assertDontSee('The current highest sale in each top-sales segment');
        $view->assertDontSee('Open Top Property Sales');
        $view->assertSee('UK Housing Market Snapshot');
        $view->assertSee('Cooling Market');
        $view->assertSee('Latest complete Land Registry quarter vs previous quarter');
        $view->assertSee('Transactions');
        $view->assertSee('Median price');
        $view->assertSee('Counties with rising prices');
        $view->assertSee('Counties with falling sales');
        $view->assertSee('Demand weakening');
        $view->assertSee('Price growth stalling');
        $view->assertSee('16% market breadth');
        $view->assertSee('100% liquidity falling');
        $view->assertSee('stroke="#ef4444"', false);
        $view->assertSee('stroke="#facc15"', false);
        $view->assertSee('stroke="#22c55e"', false);
        $view->assertSee('text-base font-bold tracking-tight text-red-700', false);
        $view->assertSee('pt-1 text-sm font-semibold leading-5 text-slate-700', false);
        $view->assertSee('18 / 112');
        $view->assertSee('112 / 112');
        $view->assertSee('rounded-xl border border-slate-200 bg-white p-5 shadow-sm', false);
        $view->assertSee('-34.1%', false);
        $view->assertSee('-0.2%', false);
        $view->assertSee('16% market breadth', false);
        $view->assertDontSee('Top Counties with Falling Sales');
        $view->assertDontSee('Top Counties with Rising Prices');
        $view->assertDontSee('Torfaen');
        $view->assertDontSee('Rutland');
        $view->assertSee('Signals Worth Watching');
        $view->assertSee('Open County Insights');
        $view->assertSee(route('insights.index', absolute: false), false);
        $view->assertSee('Browse the latest county-level property signals and market insights without crowding the homepage with specialist detail.');
        $view->assertSee('Crime Insights');
        $view->assertSee('Open Crime Insights');
        $view->assertSee('/insights/crime', false);
        $view->assertSee('Open the crime dashboard for national and local crime trends, recent movement, and area-level context alongside the property research.');
        $view->assertDontSee('128 live');
        $view->assertDontSee('Top signal (this period)');
        $view->assertSee('md:grid-cols-2 lg:grid-cols-3', false);
        $view->assertSee('flex h-full flex-col', false);
        $view->assertSeeInOrder([
            'Search by street',
            'Search postcode (e.g. SW7 5PH)',
            'Property sales',
            'Overall Property MArket Stress Index',
            'UK Housing Market Snapshot',
            'UK Swap Rates',
            'Signals Worth Watching',
            'Crime Insights',
        ]);
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

        MarketInsight::query()->create([
            'area_type' => 'postcode_sector',
            'area_code' => 'SW1',
            'insight_type' => 'unexpected_hotspot',
            'metric_value' => 6.4,
            'transactions' => 24,
            'period_start' => '2025-02-01',
            'period_end' => '2026-01-31',
            'supporting_data' => ['price_change' => 6.4],
            'insight_text' => 'Prices in SW1 are outperforming nearby sectors.',
            'created_at' => Carbon::create(2026, 3, 17, 12, 0),
            'updated_at' => Carbon::create(2026, 3, 17, 12, 0),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Signals Worth Watching');
        $response->assertSee('UK Swap Rates');
    }

    public function test_home_page_normalizes_upward_top_signal_direction_and_sign(): void
    {
        MarketInsight::query()->create([
            'area_type' => 'postcode_sector',
            'area_code' => 'B1',
            'insight_type' => 'price_spike',
            'metric_value' => -89.0,
            'transactions' => 18,
            'period_start' => '2025-02-01',
            'period_end' => '2026-01-31',
            'supporting_data' => ['price_change' => -89.0],
            'insight_text' => 'Median prices in B1 rose sharply.',
            'created_at' => Carbon::create(2026, 3, 18, 9, 0),
            'updated_at' => Carbon::create(2026, 3, 18, 9, 0),
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
        $response->assertSee('Signals Worth Watching');
        $response->assertSee('UK Swap Rates');
    }

    public function test_home_page_shows_logged_in_banner_for_user_id_one(): void
    {
        $user = User::factory()->create([
            'id' => 1,
            'name' => 'Lee Wisener',
            'email' => 'lee@example.com',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Admin Online');
        $response->assertSee('animate-ping rounded-full bg-emerald-400/70', false);
    }

    public function test_home_page_shows_dynamic_top_declining_sales_and_rising_price_counties(): void
    {
        $this->ensureLandRegistryTable();

        DB::table('land_registry')->insert($this->homepageMarketMovementRows());

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('UK Housing Market Snapshot');
        $response->assertSee('Expanding Market');
        $response->assertSee('Latest complete Land Registry quarter vs previous quarter');
        $response->assertSee('19.2%', false);
        $response->assertSee('Transactions');
        $response->assertSee('Median price');
        $response->assertSee('Counties with rising prices');
        $response->assertSee('Counties with falling sales');
        $response->assertSee('3 / 4', false);
        $response->assertSee('75% market breadth', false);
        $response->assertSee('1 / 4', false);
        $response->assertSee('25% liquidity falling', false);
        $response->assertSee('Demand weakening');
        $response->assertSee('Price growth stalling');
        $response->assertDontSee('Top Counties with Falling Sales:');
        $response->assertDontSee('Top Counties with Rising Prices:');
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
            $table->string('PAON', 100)->nullable();
            $table->string('SAON', 100)->nullable();
            $table->string('Street', 255)->nullable();
            $table->string('TownCity', 100)->nullable();
            $table->string('District', 100)->nullable();
            $table->enum('PropertyType', ['D', 'S', 'T', 'F', 'O'])->nullable();
            $table->string('County', 100)->nullable();
            $table->enum('PPDCategoryType', ['A', 'B'])->nullable();
        });
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function homepageMarketMovementRows(): array
    {
        $rows = [];
        $transactionId = 1;

        $insertSales = function (string $county, int $price, string $date, int $count) use (&$rows, &$transactionId): void {
            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    'TransactionID' => sprintf('%08d-aaaa-bbbb-cccc-%012d', $transactionId, $transactionId),
                    'Price' => $price,
                    'Date' => $date,
                    'Postcode' => sprintf('AB%d %dCD', $transactionId % 9, ($transactionId % 9) + 1),
                    'PAON' => (string) $transactionId,
                    'SAON' => null,
                    'Street' => sprintf('%s Street', $county),
                    'TownCity' => $county,
                    'District' => $county,
                    'PropertyType' => 'D',
                    'County' => $county,
                    'PPDCategoryType' => 'A',
                ];
                $transactionId++;
            }
        };

        $insertSales('Alpha', 200000, '2025-08-15 00:00:00', 10);
        $insertSales('Alpha', 200000, '2025-09-15 00:00:00', 10);
        $insertSales('Alpha', 200000, '2025-10-15 00:00:00', 10);
        $insertSales('Alpha', 220000, '2025-11-15 00:00:00', 20);
        $insertSales('Alpha', 220000, '2025-12-15 00:00:00', 10);
        $insertSales('Alpha', 220000, '2026-01-15 00:00:00', 15);

        $insertSales('Beta', 180000, '2025-08-20 00:00:00', 10);
        $insertSales('Beta', 180000, '2025-09-20 00:00:00', 10);
        $insertSales('Beta', 180000, '2025-10-20 00:00:00', 10);
        $insertSales('Beta', 170000, '2025-11-20 00:00:00', 10);
        $insertSales('Beta', 170000, '2025-12-20 00:00:00', 10);
        $insertSales('Beta', 170000, '2026-01-20 00:00:00', 5);

        $insertSales('Gamma', 220000, '2025-08-10 00:00:00', 10);
        $insertSales('Gamma', 220000, '2025-09-10 00:00:00', 10);
        $insertSales('Gamma', 220000, '2025-10-10 00:00:00', 10);
        $insertSales('Gamma', 221000, '2025-11-10 00:00:00', 11);
        $insertSales('Gamma', 221000, '2025-12-10 00:00:00', 11);
        $insertSales('Gamma', 221000, '2026-01-10 00:00:00', 11);

        $insertSales('Delta', 210000, '2025-08-05 00:00:00', 10);
        $insertSales('Delta', 210000, '2025-09-05 00:00:00', 10);
        $insertSales('Delta', 210000, '2025-10-05 00:00:00', 10);
        $insertSales('Delta', 224000, '2025-11-05 00:00:00', 13);
        $insertSales('Delta', 224000, '2025-12-05 00:00:00', 13);
        $insertSales('Delta', 224000, '2026-01-05 00:00:00', 14);

        return $rows;
    }
}
