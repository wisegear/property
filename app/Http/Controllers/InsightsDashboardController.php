<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InsightsDashboardController extends Controller
{
    private const MINIMUM_COUNTY_TRANSACTIONS = 25;

    public function index(): View
    {
        $benchmarkStart = Carbon::parse('2025-08-01')->startOfDay();
        $benchmarkEnd = Carbon::parse('2025-10-31')->endOfDay();
        $comparisonStart = Carbon::parse('2025-11-01')->startOfDay();
        $comparisonEnd = Carbon::parse('2026-01-31')->endOfDay();

        $quarterSummary = $this->quarterSummary(
            $benchmarkStart,
            $benchmarkEnd,
            $comparisonStart,
            $comparisonEnd,
        );
        $monthlyTrends = $this->monthlyTrends($comparisonEnd->copy()->startOfMonth()->subMonths(11), $comparisonEnd);
        $countyMovers = $this->countyMovers($benchmarkStart, $benchmarkEnd, $comparisonStart, $comparisonEnd);
        $propertyTypeMovements = $this->propertyTypeMovements($benchmarkStart, $benchmarkEnd, $comparisonStart, $comparisonEnd);

        return view('insights.dashboard', [
            'benchmark_start' => $benchmarkStart,
            'benchmark_end' => $benchmarkEnd,
            'comparison_start' => $comparisonStart,
            'comparison_end' => $comparisonEnd,
            'total_counties' => $countyMovers['breadth']['total_counties'],
            'declining_counties' => $countyMovers['breadth']['declining_counties'],
            'rising_price_counties' => $countyMovers['breadth']['rising_price_counties'],
            'summary' => [
                ...$quarterSummary,
                'transactions_sparkline' => $monthlyTrends['transactions_values'],
                'price_sparkline' => $monthlyTrends['price_values'],
                'benchmark_transactions_sparkline' => $monthlyTrends['benchmark_quarter_transactions_values'],
                'comparison_transactions_sparkline' => $monthlyTrends['comparison_quarter_transactions_values'],
                'market_momentum' => $this->marketMomentum(
                    $quarterSummary['sales_change_percent'],
                    $quarterSummary['median_price_change_percent'],
                ),
            ],
            'monthlyTrends' => $monthlyTrends,
            'countyMovers' => $countyMovers,
            'propertyTypeMovements' => $propertyTypeMovements,
        ]);
    }

    /**
     * @return array{
     *     benchmark_sales:int,
     *     comparison_sales:int,
     *     sales_change_percent:float,
     *     benchmark_median_price:?int,
     *     comparison_median_price:?int,
     *     median_price_change_percent:float,
     *     benchmark_transactions:int,
     *     comparison_transactions:int
     * }
     */
    private function quarterSummary(
        Carbon $benchmarkStart,
        Carbon $benchmarkEnd,
        Carbon $comparisonStart,
        Carbon $comparisonEnd,
    ): array {
        $periodExpression = $this->periodCaseExpression([
            'benchmark' => [$benchmarkStart, $benchmarkEnd],
            'comparison' => [$comparisonStart, $comparisonEnd],
        ]);
        $medianExpression = $this->medianPriceExpression();

        $rows = $this->landRegistryBaseQuery()
            ->whereBetween('Date', [$benchmarkStart, $comparisonEnd])
            ->selectRaw($periodExpression['sql'], $periodExpression['bindings'])
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw("ROUND({$medianExpression}) as median_price")
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $benchmarkSales = (int) ($rows->get('benchmark')->sales ?? 0);
        $comparisonSales = (int) ($rows->get('comparison')->sales ?? 0);
        $benchmarkMedianPrice = isset($rows->get('benchmark')->median_price) ? (int) $rows->get('benchmark')->median_price : null;
        $comparisonMedianPrice = isset($rows->get('comparison')->median_price) ? (int) $rows->get('comparison')->median_price : null;

        return [
            'benchmark_sales' => $benchmarkSales,
            'comparison_sales' => $comparisonSales,
            'benchmark_transactions' => $benchmarkSales,
            'comparison_transactions' => $comparisonSales,
            'sales_change_percent' => $this->percentageChange($benchmarkSales, $comparisonSales),
            'benchmark_median_price' => $benchmarkMedianPrice,
            'comparison_median_price' => $comparisonMedianPrice,
            'median_price_change_percent' => $this->percentageChange($benchmarkMedianPrice, $comparisonMedianPrice),
        ];
    }

    /**
     * @return array{
     *     labels:array<int,string>,
     *     transactions_values:array<int,int>,
     *     price_values:array<int,int>,
     *     benchmark_quarter_transactions_values:array<int,int>,
     *     comparison_quarter_transactions_values:array<int,int>
     * }
     */
    private function monthlyTrends(Carbon $start, Carbon $end): array
    {
        $monthStartExpression = $this->monthStartExpression();
        $medianExpression = $this->medianPriceExpression();

        $rows = $this->landRegistryBaseQuery()
            ->whereBetween('Date', [$start, $end])
            ->selectRaw("{$monthStartExpression} as month_start")
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw("ROUND({$medianExpression}) as median_price")
            ->groupBy('month_start')
            ->orderBy('month_start')
            ->get();

        $labels = [];
        $transactionValues = [];
        $priceValues = [];
        $monthlyTransactions = [];
        $monthlyPrices = [];

        foreach ($rows as $row) {
            $month = Carbon::parse((string) $row->month_start);
            $key = $month->format('Y-m');
            $monthlyTransactions[$key] = (int) $row->sales;
            $monthlyPrices[$key] = (int) ($row->median_price ?? 0);
        }

        $cursor = $start->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');

            $labels[] = $cursor->format('M Y');
            $transactionValues[] = $monthlyTransactions[$key] ?? 0;
            $priceValues[] = $monthlyPrices[$key] ?? 0;

            $cursor->addMonth();
        }

        return [
            'labels' => $labels,
            'transactions_values' => $transactionValues,
            'price_values' => $priceValues,
            'benchmark_quarter_transactions_values' => $this->quarterMonthlyValues(Carbon::parse('2025-08-01'), $monthlyTransactions),
            'comparison_quarter_transactions_values' => $this->quarterMonthlyValues(Carbon::parse('2025-11-01'), $monthlyTransactions),
        ];
    }

    /**
     * @return array{
     *     top_sales_growth:\Illuminate\Support\Collection<int, array<string, int|float|string|null>>,
     *     top_sales_decline:\Illuminate\Support\Collection<int, array<string, int|float|string|null>>,
     *     top_price_growth:\Illuminate\Support\Collection<int, array<string, int|float|string|null>>,
     *     top_price_decline:\Illuminate\Support\Collection<int, array<string, int|float|string|null>>,
     *     hotspots:\Illuminate\Support\Collection<int, array<string, int|float|string|null>>,
     *     cooling_markets:\Illuminate\Support\Collection<int, array<string, int|float|string|null>>,
     *     breadth:array{total_counties:int,declining_counties:int,rising_price_counties:int}
     * }
     */
    private function countyMovers(
        Carbon $benchmarkStart,
        Carbon $benchmarkEnd,
        Carbon $comparisonStart,
        Carbon $comparisonEnd,
    ): array {
        $periodExpression = $this->periodCaseExpression([
            'benchmark' => [$benchmarkStart, $benchmarkEnd],
            'comparison' => [$comparisonStart, $comparisonEnd],
        ]);
        $medianExpression = $this->medianPriceExpression();

        $rows = $this->landRegistryBaseQuery()
            ->whereBetween('Date', [$benchmarkStart, $comparisonEnd])
            ->whereNotNull('County')
            ->where('County', '!=', '')
            ->select('County')
            ->selectRaw($periodExpression['sql'], $periodExpression['bindings'])
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw("ROUND({$medianExpression}) as median_price")
            ->groupBy('County', 'period')
            ->get();

        $counties = collect();

        foreach ($rows as $row) {
            $county = trim((string) $row->County);
            $bucket = $counties->get($county, [
                'county' => $county,
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

        $formatted = $counties
            ->map(function (array $county): array {
                $county['sales_change_percent'] = $this->percentageChange($county['benchmark_sales'], $county['comparison_sales']);
                $county['price_change_percent'] = $this->percentageChange($county['benchmark_median_price'], $county['comparison_median_price']);

                return $county;
            })
            ->filter(fn (array $county) => $county['benchmark_sales'] >= self::MINIMUM_COUNTY_TRANSACTIONS && $county['comparison_sales'] >= self::MINIMUM_COUNTY_TRANSACTIONS)
            ->values();

        return [
            'top_sales_growth' => $formatted->filter(fn (array $county) => $county['sales_change_percent'] > 0)
                ->sortByDesc('sales_change_percent')
                ->take(10)
                ->values(),
            'top_sales_decline' => $formatted->filter(fn (array $county) => $county['sales_change_percent'] < 0)
                ->sortBy('sales_change_percent')
                ->take(10)
                ->values(),
            'top_price_growth' => $formatted->filter(fn (array $county) => $county['price_change_percent'] > 0)
                ->sortByDesc('price_change_percent')
                ->take(10)
                ->values(),
            'top_price_decline' => $formatted->filter(fn (array $county) => $county['price_change_percent'] < 0)
                ->sortBy('price_change_percent')
                ->take(10)
                ->values(),
            'hotspots' => $formatted->filter(fn (array $county) => $county['sales_change_percent'] > 15 && $county['price_change_percent'] > 5)
                ->sortByDesc('sales_change_percent')
                ->take(10)
                ->values(),
            'cooling_markets' => $formatted->filter(fn (array $county) => $county['sales_change_percent'] < -15 && $county['price_change_percent'] <= 0)
                ->sortBy('sales_change_percent')
                ->take(10)
                ->values(),
            'breadth' => [
                'total_counties' => $formatted->count(),
                'declining_counties' => $formatted->filter(fn (array $county) => $county['sales_change_percent'] < 0)->count(),
                'rising_price_counties' => $formatted->filter(fn (array $county) => $county['price_change_percent'] > 0)->count(),
            ],
        ];
    }

    /**
     * @return array{
     *     labels:array<int,string>,
     *     benchmark_sales:array<int,int>,
     *     comparison_sales:array<int,int>,
     *     change_percent:array<int,float>
     * }
     */
    private function propertyTypeMovements(
        Carbon $benchmarkStart,
        Carbon $benchmarkEnd,
        Carbon $comparisonStart,
        Carbon $comparisonEnd,
    ): array {
        $periodExpression = $this->periodCaseExpression([
            'benchmark' => [$benchmarkStart, $benchmarkEnd],
            'comparison' => [$comparisonStart, $comparisonEnd],
        ]);
        $labels = [
            'D' => 'Detached',
            'S' => 'Semi Detached',
            'T' => 'Terraced',
            'F' => 'Flat',
        ];

        $rows = $this->landRegistryBaseQuery()
            ->whereBetween('Date', [$benchmarkStart, $comparisonEnd])
            ->whereIn('PropertyType', array_keys($labels))
            ->select('PropertyType')
            ->selectRaw($periodExpression['sql'], $periodExpression['bindings'])
            ->selectRaw('COUNT(*) as sales')
            ->groupBy('PropertyType', 'period')
            ->get();

        $bucketed = collect(array_keys($labels))->mapWithKeys(fn (string $propertyType) => [
            $propertyType => ['benchmark' => 0, 'comparison' => 0],
        ])->all();

        foreach ($rows as $row) {
            $bucketed[(string) $row->PropertyType][$row->period] = (int) $row->sales;
        }

        return [
            'labels' => array_values($labels),
            'benchmark_sales' => array_map(fn (string $type) => $bucketed[$type]['benchmark'], array_keys($labels)),
            'comparison_sales' => array_map(fn (string $type) => $bucketed[$type]['comparison'], array_keys($labels)),
            'change_percent' => array_map(
                fn (string $type) => $this->percentageChange($bucketed[$type]['benchmark'], $bucketed[$type]['comparison']),
                array_keys($labels),
            ),
        ];
    }

    /**
     * @param  array<string, array{0:Carbon, 1:Carbon}>  $periods
     * @return array{sql:string, bindings:array<int,string>}
     */
    private function periodCaseExpression(array $periods): array
    {
        $cases = [];
        $bindings = [];

        foreach ($periods as $label => [$start, $end]) {
            $cases[] = 'WHEN "Date" BETWEEN ? AND ? THEN ?';
            $bindings[] = $start->toDateTimeString();
            $bindings[] = $end->toDateTimeString();
            $bindings[] = $label;
        }

        return [
            'sql' => 'CASE '.implode(' ', $cases).' END as period',
            'bindings' => $bindings,
        ];
    }

    /**
     * @param  array<string, int>  $monthlySales
     * @return array<int, int>
     */
    private function quarterMonthlyValues(Carbon $quarterStart, array $monthlySales): array
    {
        return collect(range(0, 2))
            ->map(fn (int $monthOffset) => $monthlySales[$quarterStart->copy()->addMonths($monthOffset)->format('Y-m')] ?? 0)
            ->all();
    }

    private function landRegistryBaseQuery()
    {
        return DB::table('land_registry')
            ->where('PPDCategoryType', 'A')
            ->whereNotNull('Date')
            ->whereNotNull('Price')
            ->where('Price', '>', 0);
    }

    private function monthStartExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "strftime('%Y-%m-01', \"Date\")";
        }

        return "TO_CHAR(DATE_TRUNC('month', \"Date\"), 'YYYY-MM-01')";
    }

    private function medianPriceExpression(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return 'PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY "Price")';
        }

        return 'AVG("Price")';
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

    /**
     * @return array{label:string, description:string, tone:string}
     */
    private function marketMomentum(float $salesChangePercent, float $priceChangePercent): array
    {
        if ($salesChangePercent < -20) {
            return [
                'label' => 'Market slowdown',
                'description' => 'Transactions have fallen sharply compared with the previous quarter, pointing to a broad market slowdown and weaker buyer activity.',
                'tone' => 'red',
            ];
        }

        if ($salesChangePercent < -10 && $priceChangePercent <= 1) {
            return [
                'label' => 'Demand weakening',
                'description' => 'Transactions have fallen sharply compared with the previous quarter while median prices remain broadly stable, indicating weakening demand rather than price correction.',
                'tone' => 'red',
            ];
        }

        if ($salesChangePercent > 10 && $priceChangePercent > 3) {
            return [
                'label' => 'Market strengthening',
                'description' => 'Transactions are rising alongside median prices, suggesting demand is strengthening across the market rather than being driven by isolated pricing moves.',
                'tone' => 'green',
            ];
        }

        return [
            'label' => 'Stable market',
            'description' => 'Transaction activity and median prices are moving within a relatively narrow range, indicating a market that is broadly stable quarter on quarter.',
            'tone' => 'amber',
        ];
    }
}
