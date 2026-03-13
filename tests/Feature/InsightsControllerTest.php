<?php

namespace Tests\Feature;

use App\Models\MarketInsight;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InsightsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_insights_ordered_by_postcode_area(): void
    {
        $this->insertInsight('NW8', 'price_spike', 'North west insight', '2026-03-09 12:00:00');
        $this->insertInsight('AB1', 'demand_collapse', 'Aberdeen insight', '2026-03-08 12:00:00');
        $this->insertInsight('M1', 'sector_outperformance', 'Manchester insight', '2026-03-07 12:00:00');
        $this->insertInsight('ZZ1', 'momentum_reversal', 'Momentum reversal insight', '2026-03-06 12:00:00');

        $routeResponse = $this->get('/insights');

        $routeResponse->assertOk();
        $routeResponse->assertViewIs('insights.index');
        $routeResponse->assertViewHas('query', function ($query): bool {
            if (! $query instanceof LengthAwarePaginator) {
                return false;
            }

            return count($query->items()) === 4
                && $query->items()[0]->area_code === 'AB1'
                && $query->items()[1]->area_code === 'M1'
                && $query->items()[2]->area_code === 'NW8'
                && $query->items()[3]->area_code === 'ZZ1';
        });
    }

    public function test_search_filters_by_area_code_and_insight_text(): void
    {
        $this->insertInsight('NW8', 'price_spike', 'North west price spike detected', '2026-03-09 12:00:00');
        $this->insertInsight('M1', 'demand_collapse', 'Sharp demand collapse in the city centre', '2026-03-08 12:00:00');
        $this->insertInsight('AB1', 'price_spike', 'No relevant match here', '2026-03-07 12:00:00');
        for ($i = 0; $i < 25; $i++) {
            $this->insertInsight(
                'CITY'.$i,
                'demand_collapse',
                'Sharp demand collapse in the city centre',
                sprintf('2026-02-%02d 12:00:00', ($i % 9) + 1)
            );
        }

        $areaRouteResponse = $this->get('/insights/search?search=NW8');

        $areaRouteResponse->assertOk();
        $areaRouteResponse->assertViewHas('query', function ($query): bool {
            if (! $query instanceof LengthAwarePaginator) {
                return false;
            }

            return count($query->items()) === 1
                && $query->items()[0]->area_code === 'NW8';
        });

        $routeResponse = $this->get('/insights/search?search=city centre');

        $routeResponse->assertOk();
        $routeResponse->assertViewIs('insights.index');
        $routeResponse->assertViewHas('query', function ($query): bool {
            if (! $query instanceof LengthAwarePaginator) {
                return false;
            }

            $items = $query->items();

            return count($items) === 20
                && $items[0]->area_code === 'CITY0'
                && $items[1]->area_code === 'CITY1';
        });
        $routeResponse->assertSee('search=city', false);
    }

    public function test_search_filters_by_insight_type(): void
    {
        $this->insertInsight('NW8', 'price_spike', 'North west price spike detected', '2026-03-09 12:00:00');
        $this->insertInsight('M1', 'demand_collapse', 'Sharp demand collapse in the city centre', '2026-03-08 12:00:00');
        $this->insertInsight('SW1A', 'sector_outperformance', 'Sector outperformance against the national trend', '2026-03-07 12:00:00');
        $this->insertInsight('AL12', 'unexpected_hotspot', 'Unexpected hotspot detected', '2026-03-06 12:00:00');

        $routeResponse = $this->get('/insights/search?type=unexpected_hotspot');

        $routeResponse->assertOk();
        $routeResponse->assertViewHas('selectedType', 'unexpected_hotspot');
        $routeResponse->assertViewHas('query', function ($query): bool {
            if (! $query instanceof LengthAwarePaginator) {
                return false;
            }

            return count($query->items()) === 1
                && $query->items()[0]->area_code === 'AL12';
        });
    }

    public function test_index_can_sort_by_transactions_descending(): void
    {
        $this->insertInsight('AB1', 'price_spike', 'Alpha', '2026-03-09 12:00:00', 10);
        $this->insertInsight('M1', 'demand_collapse', 'Middle', '2026-03-08 12:00:00', 75);
        $this->insertInsight('ZZ1', 'unexpected_hotspot', 'Zulu', '2026-03-07 12:00:00', 40);

        $response = $this->get('/insights?sort=transactions_desc');

        $response->assertOk();
        $response->assertViewHas('sort', 'transactions_desc');
        $response->assertViewHas('query', function ($query): bool {
            if (! $query instanceof LengthAwarePaginator) {
                return false;
            }

            return array_map(
                static fn ($insight) => $insight->area_code,
                $query->items(),
            ) === ['M1', 'ZZ1', 'AB1'];
        });
    }

    public function test_insights_index_view_renders_requested_listing_fields(): void
    {
        $view = $this->view('insights.index', [
            'query' => new LengthAwarePaginator([
                MarketInsight::make([
                    'area_code' => 'Manchester',
                    'insight_type' => 'Price acceleration',
                    'insight_text' => 'Prices increased 21% year-on-year based on 142 sales.',
                    'transactions' => 142,
                    'period_start' => Carbon::create(2025, 1, 1),
                    'period_end' => Carbon::create(2025, 12, 31),
                ]),
            ], 1, 20),
            'insightTypes' => [
                'price_spike' => 'Price Spike',
                'price_collapse' => 'Price Collapse',
                'demand_collapse' => 'Demand Collapse',
                'liquidity_stress' => 'Liquidity Stress',
                'liquidity_surge' => 'Liquidity Surge',
                'market_freeze' => 'Market Freeze',
                'sector_outperformance' => 'Sector Outperformance',
                'momentum_reversal' => 'Momentum Reversal',
                'unexpected_hotspot' => 'Unexpected Hotspot',
            ],
            'insightDescriptions' => [
                'liquidity_stress' => 'Transaction volumes have fallen sharply while prices continue rising, suggesting weakening market liquidity.',
            ],
            'selectedType' => 'price_spike',
            'search' => 'Manchester',
            'sort' => 'transactions_desc',
        ]);
        $rendered = $view->render();

        $view->assertSee('Property Market Insights');
        $view->assertSee('/insights/search', false);
        $view->assertSee('Filter insights');
        $view->assertSee('Price Spike');
        $view->assertSee('Price Collapse');
        $view->assertSee('Demand Collapse');
        $view->assertSee('Liquidity Stress');
        $view->assertSee('Liquidity Surge');
        $view->assertSee('Market Freeze');
        $view->assertSee('Sector Outperformance');
        $view->assertSee('Momentum Reversal');
        $view->assertSee('Unexpected Hotspot');
        $view->assertSee('Most transactions');
        $view->assertSee('Rolling Period');
        $view->assertSee('Manchester');
        $view->assertSee('Price acceleration');
        $view->assertSee('Prices increased 21% year-on-year based on 142 sales.');
        $view->assertSee('01 Jan 2025');
        $view->assertSee('31 Dec 2025');
        $view->assertSee('142');
        $view->assertSee(route('insights.show', ['sector' => 'manchester']), false);
        $this->assertStringContainsString('min-h-[240px]', $rendered);
        $this->assertStringNotContainsString('line-clamp-2', $rendered);
    }

    public function test_show_normalizes_sector_and_returns_insights_with_historical_data(): void
    {
        $this->ensureLandRegistryTable();

        $this->insertInsight('B1', 'price_spike', 'Median prices surged across B1.', '2026-03-09 12:00:00');

        $rows = [];
        $prices2024 = array_merge(array_fill(0, 15, 200000), array_fill(0, 15, 220000));
        $prices2025 = array_merge(array_fill(0, 15, 260000), array_fill(0, 15, 280000));
        $prices2026 = [190000, 195000, 205000];
        $transactionId = 1;

        foreach ($prices2024 as $index => $price) {
            $rows[] = [
                'TransactionID' => sprintf('%08d-1111-1111-1111-111111111111', $transactionId++),
                'Price' => $price,
                'Date' => sprintf('2024-05-%02d 00:00:00', ($index % 28) + 1),
                'Postcode' => sprintf('B1 %dAA', ($index % 9) + 1),
                'NewBuild' => 'N',
                'PPDCategoryType' => 'A',
            ];
        }

        foreach ($prices2025 as $index => $price) {
            $rows[] = [
                'TransactionID' => sprintf('%08d-2222-2222-2222-222222222222', $transactionId++),
                'Price' => $price,
                'Date' => sprintf('2025-06-%02d 00:00:00', ($index % 28) + 1),
                'Postcode' => sprintf('B1 %dBB', ($index % 9) + 1),
                'NewBuild' => 'N',
                'PPDCategoryType' => 'A',
            ];
        }

        foreach ($prices2026 as $index => $price) {
            $rows[] = [
                'TransactionID' => sprintf('%08d-3333-3333-3333-333333333333', $transactionId++),
                'Price' => $price,
                'Date' => sprintf('2026-01-%02d 00:00:00', ($index % 28) + 1),
                'Postcode' => sprintf('B1 %dCC', ($index % 3) + 1),
                'NewBuild' => 'N',
                'PPDCategoryType' => 'A',
            ];
        }

        $rows[] = [
            'TransactionID' => '99999999-5555-5555-5555-555555555555',
            'Price' => 999999,
            'Date' => '2025-08-15 00:00:00',
            'Postcode' => 'SW1A 1AA',
            'NewBuild' => 'N',
            'PPDCategoryType' => 'A',
        ];

        DB::table('land_registry')->insert($rows);

        $response = $this->get('/insights/b1');

        $response->assertOk();
        $response->assertViewIs('insights.show');
        $response->assertViewHas('sector', 'B1');
        $response->assertViewHas('insights', function ($insights): bool {
            return $insights->count() === 1
                && $insights->first()->area_code === 'B1';
        });
        $response->assertViewHas('historyRows', function ($historyRows): bool {
            return $historyRows->all() === [
                ['year' => 2024, 'sales' => 30, 'median_price' => 210000.0],
                ['year' => 2025, 'sales' => 30, 'median_price' => 270000.0],
            ];
        });
        $response->assertViewHas('recentPriceChange', function ($recentPriceChange): bool {
            return is_array($recentPriceChange)
                && $recentPriceChange['current_label'] === '04 Jan 2025 to 03 Jan 2026'
                && $recentPriceChange['previous_label'] === '04 Jan 2024 to 03 Jan 2025'
                && $recentPriceChange['current_price'] === 260000.0
                && $recentPriceChange['previous_price'] === 210000.0
                && $recentPriceChange['growth'] === 23.81;
        });
        $response->assertViewHas('rollingPriceChart', function ($rollingPriceChart): bool {
            return is_array($rollingPriceChart)
                && count($rollingPriceChart['labels']) === 10
                && end($rollingPriceChart['labels']) === '2026'
                && end($rollingPriceChart['values']) === 260000.0;
        });
        $response->assertSee('Property Market Insights – B1');
        $response->assertSee('Median prices surged across B1.');
        $response->assertSee('price spikes, demand collapse, sector outperformance and momentum reversals', false);
        $response->assertSee('Rolling 12-Month Median Price');
    }

    public function test_show_renders_when_no_current_insights_exist(): void
    {
        $this->ensureLandRegistryTable();

        DB::table('land_registry')->insert([
            [
                'TransactionID' => '66666666-6666-6666-6666-666666666666',
                'Price' => 210000,
                'Date' => '2022-02-15 00:00:00',
                'Postcode' => 'AL12 1AA',
                'NewBuild' => 'N',
                'PPDCategoryType' => 'A',
            ],
            [
                'TransactionID' => '77777777-7777-7777-7777-777777777777',
                'Price' => 210000,
                'Date' => '2022-08-15 00:00:00',
                'Postcode' => 'AL12 2AA',
                'NewBuild' => 'N',
                'PPDCategoryType' => 'A',
            ],
        ]);

        $response = $this->get('/insights/al12');

        $response->assertOk();
        $response->assertViewHas('sector', 'AL12');
        $response->assertViewHas('insights', function ($insights): bool {
            return $insights->count() === 0;
        });
        $response->assertViewHas('historyRows', function ($historyRows): bool {
            return $historyRows->all() === [
                ['year' => 2022, 'sales' => 2, 'median_price' => 210000.0],
            ];
        });
        $response->assertSee('No current market insights are stored for AL12.');
    }

    private function insertInsight(string $areaCode, string $insightType, string $insightText, string $createdAt, int $transactions = 25): void
    {
        DB::table('market_insights')->insert([
            'area_type' => 'postcode',
            'area_code' => $areaCode,
            'insight_type' => $insightType,
            'metric_value' => 12.34,
            'transactions' => $transactions,
            'period_start' => Carbon::parse('2025-01-01')->toDateString(),
            'period_end' => Carbon::parse('2025-12-31')->toDateString(),
            'supporting_data' => json_encode(['area_code' => $areaCode], JSON_THROW_ON_ERROR),
            'insight_text' => $insightText,
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);
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
            $table->enum('NewBuild', ['Y', 'N'])->nullable();
            $table->enum('PPDCategoryType', ['A', 'B'])->nullable();
        });
    }
}
