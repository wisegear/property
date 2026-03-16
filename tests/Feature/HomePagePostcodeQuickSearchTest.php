<?php

namespace Tests\Feature;

use App\Models\MarketInsight;
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
        $view->assertSee('Top declining counties');
        $view->assertSee('Torfaen');
        $view->assertSee('Portsmouth');
        $view->assertSee('Slough');
        $view->assertSee('Top rising counties');
        $view->assertSee('Rutland');
        $view->assertSee('Merseyside');
        $view->assertSee('Bedfordshire');
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

    public function test_home_page_shows_dynamic_top_declining_and_rising_counties(): void
    {
        $this->ensureLandRegistryTable();

        DB::table('land_registry')->insert($this->homepageMarketMovementRows());

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('▲ 19.2%', false);
        $response->assertSee('▲ 5.0%', false);
        $response->assertSee('1</span> / 4', false);
        $response->assertSee('3</span> / 4', false);
        $response->assertSeeInOrder([
            'Top declining counties:',
            'Beta',
            'Top rising counties:',
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
