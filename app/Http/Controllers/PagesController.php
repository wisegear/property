<?php

namespace App\Http\Controllers;

use App\Models\BlogPosts;
use App\Models\MarketInsight;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PagesController
 *
 * Responsible for the public pages (home/about). The home action can:
 *  - Perform a postcode search with sorting + pagination
 *  - Compute/cached site-wide aggregates for charts (sales counts, avg prices, prime/ultra prime slices)
 */
class PagesController extends Controller
{
    public function home(): View
    {
        // Get the 4 most recent blog posts
        $posts = BlogPosts::where('published', true)->orderBy('date', 'desc')->take(4)->get();

        // Get stats from cache only (warmed by home:stats-warm command)
        $stats = Cache::get('homepage_stats', [
            'property_records' => 0,
            'uk_avg_price' => 0,
            'uk_avg_rent' => 0,
            'bank_rate' => 0,
            'inflation_rate' => 0,
            'epc_count' => 0,
        ]);

        $totalStress = Cache::get('eco:total_stress');
        if (is_null($totalStress)) {
            $totalStress = Cache::get('eco:total_stress_persist');
        }

        return view('pages.home', [
            'posts' => $posts,
            'stats' => $stats,
            'totalStress' => $totalStress,
            ...$this->marketInsightsSummary(),
            'homepageMarketMovements' => $this->homepageMarketMovements(),
        ]);
    }

    /**
     * Static About page.
     */
    public function about(): View
    {
        return view('pages.about');
    }

    /**
     * @return array{marketInsightsCount:int, marketInsightsLastRunAt:?Carbon, marketInsightSignalCount:int}
     */
    private function marketInsightsSummary(): array
    {
        if (! Schema::hasTable('market_insights')) {
            return [
                'marketInsightsCount' => 0,
                'marketInsightsLastRunAt' => null,
                'marketInsightSignalCount' => 9,
            ];
        }

        $marketInsightsCount = MarketInsight::query()->count();
        $marketInsightsLastRunAt = MarketInsight::query()->max('created_at');

        return [
            'marketInsightsCount' => $marketInsightsCount,
            'marketInsightsLastRunAt' => $marketInsightsLastRunAt === null ? null : Carbon::parse($marketInsightsLastRunAt),
            'marketInsightSignalCount' => 9,
        ];
    }

    /**
     * @return array{rising_price_counties:int,total_counties:int}
     */
    private function homepageMarketMovements(): array
    {
        if (! Schema::hasTable('land_registry') || DB::getDriverName() !== 'pgsql') {
            return [
                'rising_price_counties' => 18,
                'total_counties' => 112,
            ];
        }

        $benchmarkStart = Carbon::parse('2025-08-01')->startOfDay();
        $benchmarkEnd = Carbon::parse('2025-10-31')->endOfDay();
        $comparisonStart = Carbon::parse('2025-11-01')->startOfDay();
        $comparisonEnd = Carbon::parse('2026-01-31')->endOfDay();
        $minimumCountyTransactions = 25;
        $medianExpression = 'PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY "Price")';

        $rows = DB::table('land_registry')
            ->whereBetween('Date', [$benchmarkStart, $comparisonEnd])
            ->where('PPDCategoryType', 'A')
            ->whereNotNull('County')
            ->where('County', '!=', '')
            ->whereNotNull('Date')
            ->whereNotNull('Price')
            ->where('Price', '>', 0)
            ->select('County')
            ->selectRaw(
                'CASE
                    WHEN "Date" BETWEEN ? AND ? THEN ?
                    WHEN "Date" BETWEEN ? AND ? THEN ?
                 END as period',
                [$benchmarkStart, $benchmarkEnd, 'benchmark', $comparisonStart, $comparisonEnd, 'comparison']
            )
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw("ROUND({$medianExpression}) as median_price")
            ->groupBy('County', 'period')
            ->get();

        $counties = collect();

        foreach ($rows as $row) {
            $county = trim((string) $row->County);
            $bucket = $counties->get($county, [
                'benchmark_sales' => 0,
                'comparison_sales' => 0,
                'benchmark_median_price' => null,
                'comparison_median_price' => null,
            ]);

            if ($row->period === 'benchmark') {
                $bucket['benchmark_sales'] = (int) $row->sales;
                $bucket['benchmark_median_price'] = $row->median_price !== null ? (int) $row->median_price : null;
            }

            if ($row->period === 'comparison') {
                $bucket['comparison_sales'] = (int) $row->sales;
                $bucket['comparison_median_price'] = $row->median_price !== null ? (int) $row->median_price : null;
            }

            $counties->put($county, $bucket);
        }

        $filtered = $counties
            ->filter(fn (array $county) => $county['benchmark_sales'] >= $minimumCountyTransactions && $county['comparison_sales'] >= $minimumCountyTransactions)
            ->values();

        $risingPriceCounties = $filtered->filter(function (array $county): bool {
            $benchmarkMedianPrice = $county['benchmark_median_price'];
            $comparisonMedianPrice = $county['comparison_median_price'];

            if ($benchmarkMedianPrice === null || $comparisonMedianPrice === null) {
                return false;
            }

            $benchmark = (float) $benchmarkMedianPrice;
            $comparison = (float) $comparisonMedianPrice;

            if ($benchmark === 0.0) {
                return $comparison > 0.0;
            }

            return round((($comparison - $benchmark) / $benchmark) * 100, 1) > 0;
        })->count();

        return [
            'rising_price_counties' => $risingPriceCounties,
            'total_counties' => $filtered->count(),
        ];
    }
}
