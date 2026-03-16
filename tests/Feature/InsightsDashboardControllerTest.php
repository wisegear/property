<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InsightsDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_dashboard_renders_quarter_comparison_metrics_and_navigation_labels(): void
    {
        $this->ensureLandRegistryTable();

        DB::table('land_registry')->insert($this->dashboardRows());

        $response = $this->get(route('insights.dashboard', absolute: false));

        $response->assertOk();
        $response->assertViewIs('insights.dashboard');
        $response->assertSee('Housing Market Movement Dashboard');
        $response->assertSee('Dashboard');
        $response->assertSee('Granular Insights');
        $response->assertSee('Top Transaction Growth Counties');
        $response->assertSee('Market Breadth');
        $response->assertSee('Top 10 emerging hotspots');
        $response->assertSee('Top 10 cooling markets');
        $response->assertSee('Market Momentum');
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['benchmark_transactions'] === 92
                && $summary['comparison_transactions'] === 102
                && $summary['benchmark_sales'] === 92
                && $summary['comparison_sales'] === 102
                && $summary['sales_change_percent'] === 10.9
                && $summary['median_price_change_percent'] > 3
                && count($summary['transactions_sparkline']) === 12
                && count($summary['price_sparkline']) === 12
                && $summary['benchmark_transactions_sparkline'] === [31, 31, 30]
                && $summary['comparison_transactions_sparkline'] === [41, 31, 30]
                && $summary['market_momentum']['label'] === 'Market strengthening'
                && $summary['market_momentum']['tone'] === 'green'
                && str_contains($summary['market_momentum']['description'], 'Transactions are rising alongside median prices');
        });
        $response->assertViewHas('total_counties', 3);
        $response->assertViewHas('declining_counties', 1);
        $response->assertViewHas('rising_price_counties', 2);
        $response->assertViewHas('countyMovers', function (array $countyMovers): bool {
            $topSalesGrowth = $countyMovers['top_sales_growth']->first();
            $topPriceDecline = $countyMovers['top_price_decline']->first();
            $hotspot = $countyMovers['hotspots']->first();
            $coolingMarket = $countyMovers['cooling_markets']->first();

            return $topSalesGrowth['county'] === 'Alpha'
                && $topSalesGrowth['benchmark_sales'] === 30
                && $topSalesGrowth['comparison_sales'] === 45
                && $topSalesGrowth['sales_change_percent'] === 50.0
                && $countyMovers['top_sales_growth']->contains(fn (array $row): bool => $row['county'] === 'Tiny')
                    === false
                && $countyMovers['top_price_growth']->contains(fn (array $row): bool => $row['county'] === 'Tiny')
                    === false
                && $topPriceDecline['county'] === 'Beta'
                && $topPriceDecline['price_change_percent'] < 0
                && $hotspot['county'] === 'Alpha'
                && $coolingMarket['county'] === 'Beta'
                && $countyMovers['breadth']['total_counties'] === 3
                && $countyMovers['breadth']['declining_counties'] === 1
                && $countyMovers['breadth']['rising_price_counties'] === 2;
        });
        $response->assertViewHas('propertyTypeMovements', function (array $propertyTypeMovements): bool {
            return $propertyTypeMovements['labels'] === ['Detached', 'Semi Detached', 'Terraced', 'Flat']
                && $propertyTypeMovements['benchmark_sales'] === [30, 20, 20, 22]
                && $propertyTypeMovements['comparison_sales'] === [40, 20, 25, 17]
                && $propertyTypeMovements['change_percent'] === [33.3, 0.0, 25.0, -22.7];
        });
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
    private function dashboardRows(): array
    {
        $rows = [];
        $transactionId = 1;

        $insertSales = function (string $county, string $propertyType, int $price, string $date, int $count) use (&$rows, &$transactionId): void {
            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    'TransactionID' => sprintf('%08d-aaaa-bbbb-cccc-%012d', $transactionId, $transactionId),
                    'Price' => $price,
                    'Date' => $date,
                    'Postcode' => sprintf('AB%d %dCD', $transactionId % 9, ($transactionId % 9) + 1),
                    'PropertyType' => $propertyType,
                    'County' => $county,
                    'PPDCategoryType' => 'A',
                ];
                $transactionId++;
            }
        };

        $insertSales('Alpha', 'D', 200000, '2025-08-15 00:00:00', 10);
        $insertSales('Alpha', 'S', 200000, '2025-09-15 00:00:00', 10);
        $insertSales('Alpha', 'T', 200000, '2025-10-15 00:00:00', 10);
        $insertSales('Alpha', 'D', 212000, '2025-11-15 00:00:00', 20);
        $insertSales('Alpha', 'S', 212000, '2025-12-15 00:00:00', 10);
        $insertSales('Alpha', 'T', 212000, '2026-01-15 00:00:00', 15);

        $insertSales('Beta', 'D', 180000, '2025-08-20 00:00:00', 10);
        $insertSales('Beta', 'F', 180000, '2025-09-20 00:00:00', 10);
        $insertSales('Beta', 'F', 180000, '2025-10-20 00:00:00', 10);
        $insertSales('Beta', 'D', 176000, '2025-11-20 00:00:00', 10);
        $insertSales('Beta', 'F', 176000, '2025-12-20 00:00:00', 10);
        $insertSales('Beta', 'F', 176000, '2026-01-20 00:00:00', 5);

        $insertSales('Gamma', 'D', 220000, '2025-08-10 00:00:00', 10);
        $insertSales('Gamma', 'S', 220000, '2025-09-10 00:00:00', 10);
        $insertSales('Gamma', 'T', 220000, '2025-10-10 00:00:00', 10);
        $insertSales('Gamma', 'D', 221000, '2025-11-10 00:00:00', 10);
        $insertSales('Gamma', 'S', 221000, '2025-12-10 00:00:00', 10);
        $insertSales('Gamma', 'T', 221000, '2026-01-10 00:00:00', 10);

        $insertSales('Tiny', 'F', 90000, '2025-08-01 00:00:00', 1);
        $insertSales('Tiny', 'F', 90000, '2025-09-01 00:00:00', 1);
        $insertSales('Tiny', 'F', 190000, '2025-11-01 00:00:00', 1);
        $insertSales('Tiny', 'F', 190000, '2025-12-01 00:00:00', 1);

        return $rows;
    }
}
