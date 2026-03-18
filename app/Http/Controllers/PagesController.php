<?php

namespace App\Http\Controllers;

use App\Models\BlogPosts;
use App\Models\MarketInsight;
use App\Services\TopSalesService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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
    public function home(TopSalesService $topSalesService): View
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
            'homepageTopSales' => $this->homepageTopSales($topSalesService),
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
     * @return array{
     *     marketInsightsCount:int,
     *     marketInsightsLastRunAt:?Carbon,
     *     marketInsightSignalCount:int,
     *     liveSignalsCount:int,
     *     signalTypesCount:int,
     *     topSignal:array{type:string,postcode:string,change:float,direction:string,color:string}|null
     * }
     */
    private function marketInsightsSummary(): array
    {
        if (! Schema::hasTable('market_insights')) {
            return [
                'marketInsightsCount' => 0,
                'marketInsightsLastRunAt' => null,
                'marketInsightSignalCount' => 9,
                'liveSignalsCount' => 0,
                'signalTypesCount' => 9,
                'topSignal' => null,
            ];
        }

        $marketInsightsCount = MarketInsight::query()->count();
        $marketInsightsLastRunAt = MarketInsight::query()->max('created_at');
        $topInsight = MarketInsight::query()
            ->whereNotNull('metric_value')
            ->orderByRaw('ABS(metric_value) DESC')
            ->orderByDesc('created_at')
            ->first();

        return [
            'marketInsightsCount' => $marketInsightsCount,
            'marketInsightsLastRunAt' => $marketInsightsLastRunAt === null ? null : Carbon::parse($marketInsightsLastRunAt),
            'marketInsightSignalCount' => 9,
            'liveSignalsCount' => $marketInsightsCount,
            'signalTypesCount' => 9,
            'topSignal' => $topInsight ? $this->formatTopSignal($topInsight) : null,
        ];
    }

    /**
     * @return array{type:string,postcode:string,change:float,direction:string,color:string}
     */
    private function formatTopSignal(MarketInsight $topInsight): array
    {
        $direction = $this->insightDirection($topInsight->insight_type);
        $change = (float) $topInsight->metric_value;

        return [
            'type' => $this->insightTypeLabel($topInsight->insight_type),
            'postcode' => (string) $topInsight->area_code,
            'change' => $direction === 'up' ? abs($change) : -abs($change),
            'direction' => $direction,
            'color' => $direction === 'up' ? 'text-green-600' : 'text-red-600',
        ];
    }

    private function insightDirection(string $insightType): string
    {
        return match ($insightType) {
            'price_spike', 'liquidity_surge', 'sector_outperformance', 'unexpected_hotspot' => 'up',
            default => 'down',
        };
    }

    private function insightTypeLabel(string $insightType): string
    {
        return match ($insightType) {
            'price_spike' => 'Price Spike',
            'price_collapse' => 'Price Collapse',
            'demand_collapse' => 'Demand Collapse',
            'liquidity_stress' => 'Liquidity Stress',
            'liquidity_surge' => 'Liquidity Surge',
            'market_freeze' => 'Market Freeze',
            'sector_outperformance' => 'Sector Outperformance',
            'momentum_reversal' => 'Momentum Reversal',
            'unexpected_hotspot' => 'Unexpected Hotspot',
            default => str($insightType)->replace('_', ' ')->title()->toString(),
        };
    }

    /**
     * @return array{
     *     transaction_change_percent:float,
     *     median_price_change_percent:float,
     *     rising_price_counties:int,
     *     declining_counties:int,
     *     total_counties:int,
     *     top_declining_counties:Collection<int, array{county:string,sales_change_percent:float}>,
     *     top_rising_price_counties:Collection<int, array{county:string,price_change_percent:float}>
     * }
     */
    private function homepageMarketMovements(): array
    {
        if (! Schema::hasTable('land_registry')) {
            return [
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
            ];
        }

        $benchmarkStart = Carbon::parse('2025-08-01')->startOfDay();
        $benchmarkEnd = Carbon::parse('2025-10-31')->endOfDay();
        $comparisonStart = Carbon::parse('2025-11-01')->startOfDay();
        $comparisonEnd = Carbon::parse('2026-01-31')->endOfDay();
        $minimumCountyTransactions = 25;
        $medianExpression = DB::connection()->getDriverName() === 'pgsql'
            ? 'PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY "Price")'
            : 'AVG("Price")';

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

        $summaryRows = DB::table('land_registry')
            ->whereBetween('Date', [$benchmarkStart, $comparisonEnd])
            ->where('PPDCategoryType', 'A')
            ->whereNotNull('Date')
            ->whereNotNull('Price')
            ->where('Price', '>', 0)
            ->selectRaw(
                'CASE
                    WHEN "Date" BETWEEN ? AND ? THEN ?
                    WHEN "Date" BETWEEN ? AND ? THEN ?
                 END as period',
                [$benchmarkStart, $benchmarkEnd, 'benchmark', $comparisonStart, $comparisonEnd, 'comparison']
            )
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw("ROUND({$medianExpression}) as median_price")
            ->groupBy('period')
            ->get();

        $counties = collect();
        $summary = [
            'benchmark_sales' => 0,
            'comparison_sales' => 0,
            'benchmark_median_price' => 0,
            'comparison_median_price' => 0,
        ];

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

        foreach ($summaryRows as $row) {
            if ($row->period === 'benchmark') {
                $summary['benchmark_sales'] = (int) $row->sales;
                $summary['benchmark_median_price'] = $row->median_price !== null ? (int) $row->median_price : 0;
            }

            if ($row->period === 'comparison') {
                $summary['comparison_sales'] = (int) $row->sales;
                $summary['comparison_median_price'] = $row->median_price !== null ? (int) $row->median_price : 0;
            }
        }

        $filtered = $counties
            ->map(function (array $county, string $name): array {
                $county['county'] = $name;
                $county['sales_change_percent'] = $this->percentageChange($county['benchmark_sales'], $county['comparison_sales']);
                $county['price_change_percent'] = $this->percentageChange($county['benchmark_median_price'], $county['comparison_median_price']);

                return $county;
            })
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
            'transaction_change_percent' => $this->percentageChange($summary['benchmark_sales'], $summary['comparison_sales']),
            'median_price_change_percent' => $this->percentageChange($summary['benchmark_median_price'], $summary['comparison_median_price']),
            'rising_price_counties' => $risingPriceCounties,
            'declining_counties' => $filtered->filter(fn (array $county): bool => $county['sales_change_percent'] < 0)->count(),
            'total_counties' => $filtered->count(),
            'top_declining_counties' => $filtered
                ->filter(fn (array $county): bool => $county['sales_change_percent'] < 0)
                ->sortBy('sales_change_percent')
                ->take(3)
                ->map(fn (array $county): array => [
                    'county' => $county['county'],
                    'sales_change_percent' => $county['sales_change_percent'],
                ])
                ->values(),
            'top_rising_price_counties' => $filtered
                ->filter(fn (array $county): bool => $county['price_change_percent'] > 0)
                ->sortByDesc('price_change_percent')
                ->take(3)
                ->map(fn (array $county): array => [
                    'county' => $county['county'],
                    'price_change_percent' => $county['price_change_percent'],
                ])
                ->values(),
        ];
    }

    /**
     * @return array<int, array{mode:string,label:string,title:string,sale:?object}>
     */
    private function homepageTopSales(TopSalesService $topSalesService): array
    {
        if (! Schema::hasTable('land_registry')) {
            return [
                ['mode' => 'ultra', 'label' => 'Ultra Prime London', 'title' => 'Highest ultra-prime London sale', 'sale' => null],
                ['mode' => 'london', 'label' => 'Prime London', 'title' => 'Highest prime London sale', 'sale' => null],
                ['mode' => 'rest', 'label' => 'Rest of UK', 'title' => 'Highest rest-of-UK sale', 'sale' => null],
            ];
        }

        return collect([
            'ultra' => ['label' => 'Ultra Prime London', 'title' => 'Highest ultra-prime London sale'],
            'london' => ['label' => 'Prime London', 'title' => 'Highest prime London sale'],
            'rest' => ['label' => 'Rest of UK', 'title' => 'Highest rest-of-UK sale'],
        ])->map(function (array $config, string $mode) use ($topSalesService): array {
            return [
                'mode' => $mode,
                'label' => $config['label'],
                'title' => $config['title'],
                'sale' => $topSalesService->cachedSales($mode)->first(),
            ];
        })->values()->all();
    }

    private function percentageChange(int|float|null $benchmarkValue, int|float|null $comparisonValue): float
    {
        $benchmark = (float) ($benchmarkValue ?? 0);
        $comparison = (float) ($comparisonValue ?? 0);

        if ($benchmark === 0.0) {
            return $comparison === 0.0 ? 0.0 : 100.0;
        }

        return round((($comparison - $benchmark) / $benchmark) * 100, 1);
    }
}
