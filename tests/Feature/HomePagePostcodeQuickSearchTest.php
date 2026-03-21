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

    public function test_home_page_shows_quick_postcode_search_above_market_stress_panel_and_keeps_top_sales_panel(): void
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
            'liveSignalsCount' => 128,
            'signalTypesCount' => 9,
            'topSignal' => [
                'type' => 'Demand Collapse',
                'postcode' => 'M1',
                'change' => -33.3,
                'direction' => 'down',
                'color' => 'text-red-600',
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
            'homepageTopSales' => [
                [
                    'mode' => 'ultra',
                    'label' => 'Ultra Prime London',
                    'title' => 'Highest ultra-prime London sale',
                    'sale' => (object) [
                        'Price' => 14500000,
                        'Date' => '2026-01-15 00:00:00',
                        'PAON' => '1',
                        'SAON' => null,
                        'Street' => 'Belgrave Square',
                        'TownCity' => 'London',
                        'County' => 'GREATER LONDON',
                        'Postcode' => 'SW1X 8PP',
                        'property_slug' => 'sw1x-8pp-1-belgrave-square',
                    ],
                ],
                [
                    'mode' => 'london',
                    'label' => 'Prime London',
                    'title' => 'Highest prime London sale',
                    'sale' => (object) [
                        'Price' => 6500000,
                        'Date' => '2026-01-12 00:00:00',
                        'PAON' => '8',
                        'SAON' => null,
                        'Street' => 'Cheyne Walk',
                        'TownCity' => 'London',
                        'County' => 'GREATER LONDON',
                        'Postcode' => 'SW3 5HL',
                        'property_slug' => 'sw3-5hl-8-cheyne-walk',
                    ],
                ],
                [
                    'mode' => 'rest',
                    'label' => 'Rest of UK',
                    'title' => 'Highest rest-of-UK sale',
                    'sale' => (object) [
                        'Price' => 3800000,
                        'Date' => '2026-01-10 00:00:00',
                        'PAON' => '25',
                        'SAON' => null,
                        'Street' => 'Park Row',
                        'TownCity' => 'Leeds',
                        'County' => 'West Yorkshire',
                        'Postcode' => 'LS1 5HD',
                        'property_slug' => 'ls1-5hd-25-park-row',
                    ],
                ],
            ],
        ]);

        $searchUrl = route('property.search', absolute: false);

        $view->assertSee($searchUrl, false);
        $view->assertSee('name="postcode"', false);
        $view->assertSee('placeholder="Search postcode (e.g. SW7 5PH)"', false);
        $view->assertSee('max-w-3xl rounded-xl border border-zinc-200 bg-white p-6 shadow-sm', false);
        $view->assertSee('rounded-lg bg-zinc-900 px-5 py-2 text-sm text-white transition hover:bg-black', false);
        $view->assertSee('Jump straight to full property data for any postcode in England &amp; Wales', false);
        $view->assertSee('Top Property Sales');
        $view->assertSee('Open Top Property Sales');
        $view->assertSee(route('top-sales.index', absolute: false), false);
        $view->assertSee('Ultra Prime London');
        $view->assertSee('Prime London');
        $view->assertSee('Rest of UK');
        $view->assertDontSee('Highest ultra-prime London sale');
        $view->assertDontSee('Highest prime London sale');
        $view->assertDontSee('Highest rest-of-UK sale');
        $view->assertSee('£14,500,000');
        $view->assertSee('£6,500,000');
        $view->assertSee('£3,800,000');
        $view->assertSee('View Detail');
        $view->assertSee('15 Jan 2026');
        $view->assertDontSee('GREATER LONDON');
        $view->assertDontSee('West Yorkshire');
        $view->assertSee(route('property.show.slug', ['slug' => 'sw1x-8pp-1-belgrave-square'], false), false);
        $view->assertSee('UK Housing Market Snapshot');
        $view->assertSee('Market Condition:');
        $view->assertSee('Cooling');
        $view->assertSee('Counties with Falling Sales');
        $view->assertSee('Counties with Rising Prices');
        $view->assertSee('Demand weakening');
        $view->assertSee('Price growth stalling');
        $view->assertSee('Limited market breadth');
        $view->assertSee('Liquidity falling');
        $view->assertSee('18 / 112');
        $view->assertSee('(16%)');
        $view->assertSee('112 / 112');
        $view->assertSee('(100%)');
        $view->assertSee('border-zinc-200 bg-zinc-50', false);
        $view->assertSee('-34.1%', false);
        $view->assertSee('M 12 60 A 48 48 0 0 1 56 12.2', false);
        $view->assertSee('M 64 12.2 A 48 48 0 0 1 108 60', false);
        $view->assertSee('stroke="#ef4444"', false);
        $view->assertSee('stroke="#22c55e"', false);
        $view->assertSee('stroke="#dc2626"', false);
        $view->assertSee('x1="60" y1="10" x2="60" y2="18"', false);
        $view->assertSee('x1="60" y1="60" x2="60" y2="12"', false);
        $view->assertSee('rotate(-30.69, 60, 60)', false);
        $view->assertSee('rotate(-2.00, 60, 60)', false);
        $view->assertSee('rotate(14.46, 60, 60)', false);
        $view->assertSee('rotate(-90.00, 60, 60)', false);
        $view->assertSee('Top Counties with Falling Sales');
        $view->assertSee('Torfaen');
        $view->assertSee('Portsmouth');
        $view->assertSee('Slough');
        $view->assertSee('Top Counties with Rising Prices');
        $view->assertSee('Rutland');
        $view->assertSee('Merseyside');
        $view->assertSee('Bedfordshire');
        $view->assertSee('Signals Worth Watching');
        $view->assertSee('128 live');
        $view->assertSee('9 signal types');
        $view->assertSee('Top signal (this period)');
        $view->assertSee('Demand Collapse');
        $view->assertSee('M1');
        $view->assertSee('▼ -33.3%');
        $view->assertSee('Spot price collapses, demand freezes, and unexpected hotspots before they show in headline data.');
        $view->assertSee('w-1/3 bg-red-400', false);
        $view->assertSee('w-1/3 bg-amber-400', false);
        $view->assertSee('w-1/3 bg-green-400', false);
        $view->assertSee('Explore County Insights');
        $view->assertSee('transition transform hover:-translate-y-0.5 hover:shadow-md', false);
        $view->assertSee('lg:grid-cols-[minmax(0,1.35fr)_minmax(0,0.65fr)]', false);
        $view->assertSee('md:grid-cols-2 lg:grid-cols-3', false);
        $view->assertSee('flex h-full flex-col', false);
        $view->assertSeeInOrder([
            'Search postcode (e.g. SW7 5PH)',
            'Property Records',
            'Overall Property MArket Stress Index',
            'UK Housing Market Snapshot',
            'Top Property Sales',
            'Signals worth watching',
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
        $response->assertSee('3 live');
        $response->assertSee('9 signal types');
        $response->assertSee('Top signal (this period)');
        $response->assertSee('Demand Collapse');
        $response->assertSee('M1');
        $response->assertSee('▼ -33.3%');
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
        $response->assertSee('Price Spike');
        $response->assertSee('B1');
        $response->assertSee('▲ 89.0%');
        $response->assertDontSee('▼ -89.0%');
        $response->assertSee('text-green-600');
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
        $response->assertSee('Lee Wisener is logged in, probably means he is breaking things, beware :)');
    }

    public function test_home_page_shows_dynamic_top_declining_sales_and_rising_price_counties(): void
    {
        $this->ensureLandRegistryTable();

        DB::table('land_registry')->insert($this->homepageMarketMovementRows());

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Market Condition:');
        $response->assertSee('Expanding');
        $response->assertSee('19.2%', false);
        $response->assertSee('3 / 4', false);
        $response->assertSee('(75%)', false);
        $response->assertSee('1 / 4', false);
        $response->assertSee('(25%)', false);
        $response->assertSee('Demand weakening');
        $response->assertSee('Liquidity falling');
        $response->assertSee('Limited market breadth');
        $response->assertSee('rotate(17.28, 60, 60)', false);
        $response->assertSee('rotate(67.50, 60, 60)', false);
        $response->assertSee('rotate(-22.50, 60, 60)', false);
        $response->assertSeeInOrder([
            'Top Counties with Falling Sales:',
            'Beta',
            'Top Counties with Rising Prices:',
            'Alpha',
            'Delta',
            'Gamma',
        ], false);
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
