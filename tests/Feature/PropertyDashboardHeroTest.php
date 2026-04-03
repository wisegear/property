<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PropertyDashboardHeroTest extends TestCase
{
    public function test_property_dashboard_hero_includes_search_button_before_outer_prime_link(): void
    {
        Cache::put('property:home:rolling:202601:typeSplit', ['data' => collect()]);
        Cache::put('property:home:rolling:202601:avgPriceByType', ['data' => collect()]);
        Cache::put('property:home:rolling:202601:newBuildSplit', collect());
        Cache::put('property:home:rolling:202601:durationSplit', ['data' => collect()]);

        $view = $this->view('property.home', [
            'salesByYear' => collect(),
            'avgPriceByYear' => collect(),
            'ewP90' => collect(),
            'ewTop5' => collect(),
            'ewTopSalePerYear' => collect(),
            'ewTop3PerYear' => collect(),
            'sales24Labels' => [],
            'sales24Data' => [],
            'latestMonth' => '2026-01-01',
            'rollingStart' => '2025-02-01',
            'rollingEnd' => '2026-01-31',
            'previousSalesTotal' => null,
            'previousMedianPrice' => null,
            'previousP90Price' => null,
            'previousTop5Average' => null,
        ]);

        $searchUrl = route('property.search', absolute: false);

        $view->assertSee('Property Search');
        $view->assertSee($searchUrl, false);
        $view->assertSeeInOrder(['Property Search', 'Outer Prime London']);
        $view->assertSee('rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6', false);
        $view->assertSee('Housing Mix', false);
        $view->assertSee('buildCommonChartOptions', false);
    }

    public function test_prime_dashboard_snapshot_has_dynamic_targets_for_district_switching(): void
    {
        $view = $this->view('property.partials.prime_dashboard', [
            'districts' => collect(['ALL', 'W1']),
            'charts' => [
                'ALL' => [
                    'sales' => collect([(object) ['year' => 2025, 'sales' => 10], (object) ['year' => 2026, 'sales' => 8]]),
                    'avgPrice' => collect([(object) ['year' => 2025, 'avg_price' => 500000], (object) ['year' => 2026, 'avg_price' => 450000]]),
                    'propertyTypes' => collect(),
                    'avgPriceByType' => collect(),
                    'newBuildPct' => collect(),
                    'tenurePct' => collect(),
                    'p90' => collect(),
                    'top5' => collect(),
                    'topSalePerYear' => collect(),
                    'top3PerYear' => collect(),
                ],
                'W1' => [
                    'sales' => collect([(object) ['year' => 2025, 'sales' => 4], (object) ['year' => 2026, 'sales' => 5]]),
                    'avgPrice' => collect([(object) ['year' => 2025, 'avg_price' => 700000], (object) ['year' => 2026, 'avg_price' => 770000]]),
                    'propertyTypes' => collect(),
                    'avgPriceByType' => collect(),
                    'newBuildPct' => collect(),
                    'tenurePct' => collect(),
                    'p90' => collect(),
                    'top5' => collect(),
                    'topSalePerYear' => collect(),
                    'top3PerYear' => collect(),
                ],
            ],
            'snapshot' => [
                'rolling_12_sales' => 8,
                'rolling_12_median_price' => 450000,
                'rolling_12_sales_yoy' => -20.0,
                'rolling_12_price_yoy' => -10.0,
            ],
            'notes' => [],
            'latestMonth' => now(),
            'rollingRangeLabel' => '12-month rolling data • Jan 1995 - Jan 2026',
        ]);

        $view->assertSee('id="snapshot-sales"', false);
        $view->assertSee('id="snapshot-median-price"', false);
        $view->assertSee('id="snapshot-price-yoy"', false);
        $view->assertSee('id="snapshot-sales-yoy"', false);
        $view->assertSee('function updateSnapshot(district)', false);
        $view->assertSee('updateSnapshot(val);', false);
        $view->assertSee('canvas id="api_ALL" class="block h-full w-full max-w-full"', false);
        $view->assertSee('canvas id="yoy_top5_ALL" class="block h-full w-full max-w-full"', false);
        $view->assertSee('Prime Signals', false);
        $view->assertSee('text: yoyRangeTitle,', false);
        $view->assertSee('font: {', false);
        $view->assertSee('size: 12', false);
    }
}
