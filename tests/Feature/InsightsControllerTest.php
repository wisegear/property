<?php

namespace Tests\Feature;

use App\Models\MarketInsight;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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

        $routeResponse = $this->get('/insights/search?type=sector_outperformance');

        $routeResponse->assertOk();
        $routeResponse->assertViewHas('selectedType', 'sector_outperformance');
        $routeResponse->assertViewHas('query', function ($query): bool {
            if (! $query instanceof LengthAwarePaginator) {
                return false;
            }

            return count($query->items()) === 1
                && $query->items()[0]->area_code === 'SW1A';
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
                'demand_collapse' => 'Demand Collapse',
                'sector_outperformance' => 'Sector Outperformance',
                'momentum_reversal' => 'Momentum Reversal',
            ],
            'selectedType' => 'price_spike',
            'search' => 'Manchester',
        ]);

        $view->assertSee('Property Market Insights');
        $view->assertSee('/insights/search', false);
        $view->assertSee('Filter insights');
        $view->assertSee('Price Spike');
        $view->assertSee('Demand Collapse');
        $view->assertSee('Sector Outperformance');
        $view->assertSee('Momentum Reversal');
        $view->assertSee('Area');
        $view->assertSee('Insight');
        $view->assertSee('Period');
        $view->assertSee('Transactions');
        $view->assertSee('Manchester');
        $view->assertSee('Price acceleration');
        $view->assertSee('Prices increased 21% year-on-year based on 142 sales.');
        $view->assertSee('01 Jan 2025 to 31 Dec 2025');
        $view->assertSee('142');
    }

    private function insertInsight(string $areaCode, string $insightType, string $insightText, string $createdAt): void
    {
        DB::table('market_insights')->insert([
            'area_type' => 'postcode',
            'area_code' => $areaCode,
            'insight_type' => $insightType,
            'metric_value' => 12.34,
            'transactions' => 25,
            'period_start' => Carbon::parse('2025-01-01')->toDateString(),
            'period_end' => Carbon::parse('2025-12-31')->toDateString(),
            'supporting_data' => json_encode(['area_code' => $areaCode], JSON_THROW_ON_ERROR),
            'insight_text' => $insightText,
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);
    }
}
