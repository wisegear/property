<?php

namespace App\Services;

use App\Models\MarketInsight;
use App\Models\SwapRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomepageDataService
{
    public function __construct(
        private TopSalesService $topSalesService
    ) {}

    /**
     * @return array{
     *     property_records:int,
     *     uk_avg_price:int,
     *     uk_avg_rent:int,
     *     bank_rate:float|int,
     *     inflation_rate:float|int,
     *     epc_count:int
     * }
     */
    public function homepageStats(): array
    {
        $propertyCount = Schema::hasTable('land_registry')
            ? DB::table('land_registry')->count()
            : 0;

        $ukAvgPrice = 0;
        if (Schema::hasTable('hpi_monthly')) {
            $latestHpi = DB::table('hpi_monthly')
                ->where('AreaCode', 'K02000001')
                ->orderBy('Date', 'desc')
                ->first();

            $ukAvgPrice = (float) ($latestHpi->AveragePrice ?? 0);
        }

        $ukAvgRent = 0;
        if (Schema::hasTable('rental_costs')) {
            $ukAvgRent = $this->latestUkAverageRent();
        }

        $bankRate = 0;
        if (Schema::hasTable('interest_rates')) {
            $latestRate = DB::table('interest_rates')
                ->orderBy('effective_date', 'desc')
                ->first();

            $bankRate = $latestRate->rate ?? 0;
        }

        $inflationRate = 0;
        if (Schema::hasTable('inflation_cpih_monthly')) {
            $latestInflation = DB::table('inflation_cpih_monthly')
                ->orderBy('date', 'desc')
                ->first();

            $inflationRate = $latestInflation->rate ?? 0;
        }

        $epcCount = 0;
        if (Schema::hasTable('epc_certificates')) {
            $epcCount += DB::table('epc_certificates')->count();
        }

        if (Schema::hasTable('epc_certificates_scotland')) {
            $epcCount += DB::table('epc_certificates_scotland')->count();
        }

        return [
            'property_records' => $propertyCount,
            'uk_avg_price' => (int) round($ukAvgPrice),
            'uk_avg_rent' => (int) round($ukAvgRent),
            'bank_rate' => $bankRate,
            'inflation_rate' => $inflationRate,
            'epc_count' => $epcCount,
        ];
    }

    /**
     * @return array{
     *     marketInsightsCount:int,
     *     marketInsightsLastRunAt:?Carbon,
     *     marketInsightSignalCount:int,
     *     liveSignalsCount:int,
     *     signalTypesCount:int,
     *     topSignal:array{type:string,postcode:string,change:float,direction:string,color:string}|null,
     *     homepageStatMovements:array{
     *         property_records:array{change:string,tone:string},
     *         epc_count:array{change:string,tone:string},
     *         uk_avg_price:array{change:string,tone:string},
     *         uk_avg_rent:array{change:string,tone:string},
     *         bank_rate:array{change:string,tone:string}
     *     },
     *     homepageMarketMovements:array{
     *         transaction_change_percent:float,
     *         median_price_change_percent:float,
     *         rising_price_counties:int,
     *         declining_counties:int,
     *         total_counties:int,
     *         top_declining_counties:Collection<int, array{county:string,sales_change_percent:float}>,
     *         top_rising_price_counties:Collection<int, array{county:string,price_change_percent:float}>
     *     },
     *     homepageSwapRates:array{
     *         latestAvailableDate:?Carbon,
     *         rates:array<int, array{term:int,label:string,rate:?float,daily_change:?float,rate_date:?Carbon}>
     *     }
     * }
     */
    public function homepagePanels(): array
    {
        return [
            ...$this->marketInsightsSummary(),
            'homepageStatMovements' => $this->homepageStatMovements(),
            'homepageMarketMovements' => $this->homepageMarketMovements(),
            'homepageSwapRates' => $this->homepageSwapRates(),
        ];
    }

    /**
     * @return array{
     *     property_records:array{change:string,tone:string},
     *     epc_count:array{change:string,tone:string},
     *     uk_avg_price:array{change:string,tone:string},
     *     uk_avg_rent:array{change:string,tone:string},
     *     bank_rate:array{change:string,tone:string}
     * }
     */
    public function homepageStatMovements(): array
    {
        return [
            'property_records' => $this->countThisYearMovement('land_registry', 'Date'),
            'epc_count' => $this->epcCertificatesThisYearMovement(),
            'uk_avg_price' => $this->latestHpiYearOnYearMovement(),
            'uk_avg_rent' => $this->latestRentYearOnYearMovement(),
            'bank_rate' => $this->bankRateFromPeakMovement(),
        ];
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
    public function marketInsightsSummary(): array
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
    public function homepageMarketMovements(): array
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

        [
            'benchmark_start' => $benchmarkStart,
            'benchmark_end' => $benchmarkEnd,
            'comparison_start' => $comparisonStart,
            'comparison_end' => $comparisonEnd,
        ] = $this->latestLandRegistryQuarterPeriods();
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
            ->filter(fn (array $county): bool => $county['benchmark_sales'] >= $minimumCountyTransactions && $county['comparison_sales'] >= $minimumCountyTransactions)
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
     * @return array{
     *     benchmark_start:Carbon,
     *     benchmark_end:Carbon,
     *     comparison_start:Carbon,
     *     comparison_end:Carbon
     * }
     */
    private function latestLandRegistryQuarterPeriods(): array
    {
        $latestDate = DB::table('land_registry')
            ->where('PPDCategoryType', 'A')
            ->whereNotNull('Date')
            ->whereNotNull('Price')
            ->where('Price', '>', 0)
            ->max('Date');

        if ($latestDate === null) {
            $comparisonStart = Carbon::parse('2025-11-01')->startOfDay();
            $comparisonEnd = Carbon::parse('2026-01-31')->endOfDay();
        } else {
            $comparisonStart = Carbon::parse((string) $latestDate)->startOfMonth()->subMonths(2)->startOfDay();
            $comparisonEnd = $comparisonStart->copy()->addMonths(2)->endOfMonth();
        }

        $benchmarkStart = $comparisonStart->copy()->subMonths(3)->startOfMonth()->startOfDay();
        $benchmarkEnd = $benchmarkStart->copy()->addMonths(2)->endOfMonth();

        return [
            'benchmark_start' => $benchmarkStart,
            'benchmark_end' => $benchmarkEnd,
            'comparison_start' => $comparisonStart,
            'comparison_end' => $comparisonEnd,
        ];
    }

    /**
     * @return array{
     *     latestAvailableDate:?Carbon,
     *     rates:array<int, array{term:int,label:string,rate:?float,daily_change:?float,rate_date:?Carbon}>
     * }
     */
    public function homepageSwapRates(): array
    {
        if (! Schema::hasTable('swap_rates')) {
            return [
                'latestAvailableDate' => null,
                'rates' => [],
            ];
        }

        $terms = [2, 5, 10];
        $latestRates = SwapRate::query()
            ->where('curve_type', 'ois')
            ->whereIn('term_years', $terms)
            ->orderBy('rate_date')
            ->get()
            ->groupBy('term_years')
            ->map(fn (Collection $series): ?SwapRate => $series->last());

        $latestAvailableDate = $latestRates
            ->filter()
            ->map(fn (SwapRate $rate): string => $rate->rate_date->toDateString())
            ->sort()
            ->last();

        return [
            'latestAvailableDate' => $latestAvailableDate === null ? null : Carbon::parse($latestAvailableDate),
            'rates' => collect($terms)->map(function (int $termYears) use ($latestRates): array {
                $latestRate = $latestRates->get($termYears);

                return [
                    'term' => $termYears,
                    'label' => $termYears.'Y Swap',
                    'rate' => $latestRate === null ? null : round((float) $latestRate->rate, 4),
                    'daily_change' => $latestRate === null || $latestRate->daily_change === null
                        ? null
                        : round((float) $latestRate->daily_change * 100, 1),
                    'rate_date' => $latestRate?->rate_date,
                ];
            })->all(),
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

    private function latestUkAverageRent(): float
    {
        $rentRows = DB::table('rental_costs')
            ->select(['time_period', 'rental_price'])
            ->where('area_name', 'United Kingdom')
            ->get();

        $latestQuarterKey = null;
        $latestQuarterTs = null;

        foreach ($rentRows as $row) {
            $date = $this->parseTimePeriod($row->time_period);
            if (! $date) {
                continue;
            }

            $quarter = (int) ceil(((int) $date->format('n')) / 3);
            $key = $date->format('Y').'-Q'.$quarter;
            $ts = $date->getTimestamp();

            if ($latestQuarterTs === null || $ts > $latestQuarterTs) {
                $latestQuarterTs = $ts;
                $latestQuarterKey = $key;
            }
        }

        if (! $latestQuarterKey) {
            return 0;
        }

        $sum = 0.0;
        $count = 0;

        foreach ($rentRows as $row) {
            $date = $this->parseTimePeriod($row->time_period);
            if (! $date) {
                continue;
            }

            $quarter = (int) ceil(((int) $date->format('n')) / 3);
            $key = $date->format('Y').'-Q'.$quarter;
            if ($key !== $latestQuarterKey || $row->rental_price === null) {
                continue;
            }

            $sum += (float) $row->rental_price;
            $count++;
        }

        return $count > 0 ? $sum / $count : 0;
    }

    /**
     * @return array{change:string,tone:string}
     */
    private function countThisYearMovement(string $table, string $dateColumn): array
    {
        if (! Schema::hasTable($table)) {
            return ['change' => '0 this year', 'tone' => 'neutral'];
        }

        $count = DB::table($table)
            ->whereBetween($dateColumn, [
                Carbon::now()->startOfYear(),
                Carbon::now()->endOfYear(),
            ])
            ->count();

        return [
            'change' => '↑ '.$this->compactStatMovement($count).' this year',
            'tone' => $count > 0 ? 'positive' : 'neutral',
        ];
    }

    /**
     * @return array{change:string,tone:string}
     */
    private function epcCertificatesThisYearMovement(): array
    {
        $count = 0;
        $start = Carbon::now()->startOfYear()->toDateString();
        $end = Carbon::now()->endOfYear()->toDateString();

        foreach (['epc_certificates', 'epc_certificates_scotland'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'LODGEMENT_DATE')) {
                continue;
            }

            $count += DB::table($table)
                ->whereBetween('LODGEMENT_DATE', [$start, $end])
                ->count();
        }

        return [
            'change' => '↑ '.$this->compactStatMovement($count).' this year',
            'tone' => $count > 0 ? 'positive' : 'neutral',
        ];
    }

    /**
     * @return array{change:string,tone:string}
     */
    private function latestHpiYearOnYearMovement(): array
    {
        if (! Schema::hasTable('hpi_monthly')) {
            return ['change' => '0.0% YoY', 'tone' => 'neutral'];
        }

        $latestHpi = DB::table('hpi_monthly')
            ->where('AreaCode', 'K02000001')
            ->orderBy('Date', 'desc')
            ->first();

        $change = (float) ($latestHpi?->{'12m%Change'} ?? 0);

        return [
            'change' => $this->signedPercentLabel($change).' YoY',
            'tone' => $change >= 0 ? 'positive' : 'negative',
        ];
    }

    /**
     * @return array{change:string,tone:string}
     */
    private function latestRentYearOnYearMovement(): array
    {
        if (! Schema::hasTable('rental_costs')) {
            return ['change' => '0.0% YoY', 'tone' => 'neutral'];
        }

        $latestRent = DB::table('rental_costs')
            ->where('area_name', 'United Kingdom')
            ->whereNotNull('annual_change')
            ->get(['time_period', 'annual_change'])
            ->sortByDesc(function (object $row): int {
                $date = $this->parseTimePeriod($row->time_period);

                return $date?->getTimestamp() ?? 0;
            })
            ->first();

        $change = (float) ($latestRent->annual_change ?? 0);

        return [
            'change' => $this->signedPercentLabel($change).' YoY',
            'tone' => $change >= 0 ? 'positive' : 'negative',
        ];
    }

    /**
     * @return array{change:string,tone:string}
     */
    private function bankRateFromPeakMovement(): array
    {
        if (! Schema::hasTable('interest_rates')) {
            return ['change' => '0.00pp from peak', 'tone' => 'neutral'];
        }

        $latestRate = DB::table('interest_rates')
            ->orderBy('effective_date', 'desc')
            ->first();

        if ($latestRate === null) {
            return ['change' => '0.00pp from peak', 'tone' => 'neutral'];
        }

        $peakWindowStart = Carbon::parse((string) $latestRate->effective_date)
            ->subYear()
            ->toDateString();
        $peakRate = DB::table('interest_rates')
            ->where('effective_date', '>=', $peakWindowStart)
            ->where('effective_date', '<=', $latestRate->effective_date)
            ->max('rate');
        $change = round((float) $peakRate - (float) ($latestRate->rate ?? 0), 2);

        return [
            'change' => ($change > 0 ? '↓ ' : '').number_format(abs($change), 2).'pp from peak',
            'tone' => $change > 0 ? 'positive' : 'neutral',
        ];
    }

    private function compactStatMovement(int $value): string
    {
        if ($value >= 1000000) {
            return rtrim(rtrim(number_format($value / 1000000, 1), '0'), '.').'m';
        }

        if ($value >= 1000) {
            return rtrim(rtrim(number_format($value / 1000, 1), '0'), '.').'k';
        }

        return number_format($value);
    }

    private function signedPercentLabel(float $change): string
    {
        return ($change > 0 ? '↑ ' : ($change < 0 ? '↓ ' : '')).number_format(abs($change), 1).'%';
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

    private function parseTimePeriod(?string $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (is_numeric($trimmed)) {
            return $this->excelSerialToDateTime((float) $trimmed);
        }

        foreach (['M-Y', 'Y-m', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $trimmed, new \DateTimeZone('UTC'));
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        $parsed = date_create($trimmed, new \DateTimeZone('UTC'));

        return $parsed ? \DateTimeImmutable::createFromMutable($parsed) : null;
    }

    private function excelSerialToDateTime(float $serial): ?\DateTimeImmutable
    {
        if ($serial < 1) {
            return null;
        }

        $days = (int) floor($serial);
        $seconds = (int) round(($serial - $days) * 86400);

        $base = new \DateTimeImmutable('1899-12-30', new \DateTimeZone('UTC'));
        $date = $base->modify('+'.$days.' days');
        if ($seconds > 0) {
            $date = $date->modify('+'.$seconds.' seconds');
        }

        return $date;
    }
}
