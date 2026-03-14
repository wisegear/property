<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsRollingPrimeDashboardData;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OuterPrimeLondonController extends Controller
{
    use BuildsRollingPrimeDashboardData;

    private const CATEGORY = 'Outer Prime London';

    private const CACHE_PREFIX = 'outerprime:home:rolling';

    public function home(Request $request)
    {
        $latestMonth = $this->latestMonth();
        $ttl = now()->addDays(45);
        $cachePrefix = self::CACHE_PREFIX.':'.$latestMonth->format('Ym');

        $districts = DB::table('prime_postcodes')
            ->where('category', self::CATEGORY)
            ->pluck('postcode')
            ->map(fn ($postcode) => strtoupper(trim((string) $postcode)))
            ->filter()
            ->unique()
            ->values();

        $notesByPostcode = DB::table('prime_postcodes')
            ->where('category', self::CATEGORY)
            ->pluck('notes', 'postcode');

        $allDistricts = collect(['ALL'])->merge($districts)->values();
        $charts = [];

        foreach ($allDistricts as $district) {
            $districtKey = $cachePrefix.':'.$district;
            $charts[$district] = $this->districtCharts((string) $district, $districtKey, $ttl, $latestMonth);
        }

        $lastWarmCacheKey = $cachePrefix.':last_warm';

        return view('outerprime.home', [
            'pageTitle' => 'Outer Prime London',
            'districts' => $allDistricts,
            'charts' => $charts,
            'notes' => $notesByPostcode,
            'heroDescription' => 'Analysis of London\'s prestigious postcodes in Outer London. <span class="font-semibold">Category A sales only</span>. This represents the widely accepted postcodes in the Outer Prime areas. Some will refine that down further to specific streets or neighbourhoods, but for this site it\'s suitable for a broad overview.',
            'lastWarmCacheKeys' => [$lastWarmCacheKey],
            'allDistrictLabel' => 'All Outer Prime',
            'emptyDistrictMessage' => 'No Outer Prime districts found.',
            'allDistrictOverviewTitle' => 'All Outer Prime - Overview',
            'allDistrictOverviewDescription' => 'This section aggregates <strong>all Outer Prime London postcodes</strong> into a single area for year-by-year analysis.',
            'latestMonth' => $latestMonth,
            'rollingRangeLabel' => $this->rollingRangeLabel($latestMonth),
            'snapshot' => $this->snapshotFromCharts($charts['ALL'] ?? []),
        ]);
    }

    private function baseAllDistrictsQuery(): Builder
    {
        return DB::table('land_registry')
            ->join(DB::raw("(SELECT DISTINCT postcode FROM prime_postcodes WHERE category = '".self::CATEGORY."') as pp"), function ($join) {
                $join->on(DB::raw($this->normalizedPostcodeExpression()), 'LIKE', DB::raw("(pp.postcode || '%')"));
            })
            ->where('PPDCategoryType', 'A');
    }

    private function baseDistrictQuery(string $district): Builder
    {
        return DB::table('land_registry')
            ->whereRaw($this->normalizedPostcodeExpression()." LIKE (? || '%')", [$district])
            ->where('PPDCategoryType', 'A');
    }

    private function districtCharts(string $district, string $districtKey, $ttl, $latestMonth): array
    {
        $metrics = [
            'avgPrice',
            'sales',
            'propertyTypes',
            'avgPriceByType',
            'newBuildPct',
            'tenurePct',
            'p90',
            'top5',
            'topSalePerYear',
            'top3PerYear',
        ];

        $cacheKeys = collect($metrics)
            ->mapWithKeys(fn ($metric) => [$metric => $districtKey.':'.$metric]);

        if ($cacheKeys->every(fn ($key) => Cache::has($key))) {
            return $cacheKeys->map(fn ($key) => Cache::get($key))->all();
        }

        $baseQuery = $district === 'ALL'
            ? $this->baseAllDistrictsQuery()
            : $this->baseDistrictQuery($district);

        $endMonths = $this->rollingEndMonths($baseQuery, $latestMonth);

        return [
            'avgPrice' => Cache::remember($districtKey.':avgPrice', $ttl, fn () => $this->buildRollingMedianSeries($baseQuery, $endMonths)),
            'sales' => Cache::remember($districtKey.':sales', $ttl, fn () => $this->buildRollingSalesSeries($baseQuery, $endMonths)),
            'propertyTypes' => Cache::remember($districtKey.':propertyTypes', $ttl, fn () => $this->buildRollingPropertyTypeSeries($baseQuery, $endMonths)),
            'avgPriceByType' => Cache::remember($districtKey.':avgPriceByType', $ttl, fn () => $this->buildRollingAvgPriceByTypeSeries($baseQuery, $endMonths)),
            'newBuildPct' => Cache::remember($districtKey.':newBuildPct', $ttl, fn () => $this->buildRollingNewBuildSeries($baseQuery, $endMonths)),
            'tenurePct' => Cache::remember($districtKey.':tenurePct', $ttl, fn () => $this->buildRollingTenureSeries($baseQuery, $endMonths)),
            'p90' => Cache::remember($districtKey.':p90', $ttl, fn () => $this->buildRollingP90Series($baseQuery, $endMonths)),
            'top5' => Cache::remember($districtKey.':top5', $ttl, fn () => $this->buildRollingTop5Series($baseQuery, $endMonths)),
            'topSalePerYear' => Cache::remember($districtKey.':topSalePerYear', $ttl, fn () => $this->buildRollingTopSaleSeries($baseQuery, $endMonths)),
            'top3PerYear' => Cache::remember($districtKey.':top3PerYear', $ttl, fn () => $this->buildRollingTop3Series($baseQuery, $endMonths)),
        ];
    }

    private function rollingRangeLabel($latestMonth): string
    {
        $startMonth = $this->earliestMonth() ?? $latestMonth;

        return '12-month rolling data • '.$startMonth->format('M Y').' - '.$latestMonth->format('M Y');
    }

    private function medianPriceExpression(string $columnExpression = 'Price'): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return "PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY {$columnExpression})";
        }

        return "AVG({$columnExpression})";
    }

    private function quotedColumn(string $column): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return '"'.$column.'"';
        }

        return $column;
    }

    private function normalizedPostcodeExpression(): string
    {
        return 'REPLACE('.$this->quotedColumn('Postcode').", ' ', '')";
    }
}
