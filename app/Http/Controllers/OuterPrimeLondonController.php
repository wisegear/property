<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OuterPrimeLondonController extends Controller
{
    /**
     * Show the Outer Prime London dashboard.
     * Mirrors PrimeLondonController but uses category 'Outer Prime London'.
     */
    public function home(Request $request)
    {
        $yearColumn = $this->quotedColumn('YearDate');
        $priceColumn = $this->quotedColumn('Price');
        $yearColumnAll = $this->quotedColumn('YearDate', 'lr');
        $priceColumnAll = $this->quotedColumn('Price', 'lr');
        $propertyTypeColumnAll = $this->quotedColumn('PropertyType', 'lr');
        $newBuildColumnAll = $this->quotedColumn('NewBuild', 'lr');
        $durationColumnAll = $this->quotedColumn('Duration', 'lr');
        $dateColumnAll = $this->quotedColumn('Date', 'lr');
        $postcodeColumnAll = $this->quotedColumn('Postcode', 'lr');

        // Page title
        $pageTitle = 'Outer Prime Central London';

        // Fetch Outer Prime postcode districts
        $districts = DB::table('prime_postcodes')
            ->where('category', 'Outer Prime London')
            ->orderBy('postcode')
            ->pluck('postcode')
            ->unique()
            ->values();

        // Include an aggregate "ALL" bucket to show charts across all Outer Prime postcodes
        $allDistricts = collect(['ALL'])->merge($districts);

        // If no districts, render a minimal view
        if ($districts->isEmpty()) {
            return view('prime.home', [
                'pageTitle' => $pageTitle,
                'districts' => collect(),
                'charts' => [],
                'notes' => [],
                'heroDescription' => 'Analysis of London\'s prestigious postcodes in Outer London. <span class="font-semibold">Category A sales only</span>. This represents the widely accepted postcodes in the Outer Prime areas. Some will refine that down further to specific streets or neighbourhoods, but for this site it\'s suitable for a broad overview.',
                'lastWarmCacheKeys' => [
                    'outerprime:v4:catA:last_warm',
                    'outerprime:v3:catA:last_warm',
                    'outerprime:v2:catA:last_warm',
                    'outerprime:v1:catA:last_warm',
                ],
                'allDistrictLabel' => 'All Outer Prime',
                'emptyDistrictMessage' => 'No Outer Prime districts found.',
                'allDistrictOverviewTitle' => 'All Outer Prime – Overview',
                'allDistrictOverviewDescription' => 'This section aggregates <strong>all Outer Prime London postcodes</strong> into a single area for year-by-year analysis.',
            ]);
        }

        // TTL for cached datasets (45 days)
        $ttl = 60 * 60 * 24 * 45;

        // Build charts data per district
        $charts = [];

        foreach ($allDistricts as $district) {
            // Key base (namespace): v3 ensures median-based data does not reuse average caches.
            $keyBase = $district === 'ALL' ? 'outerprime:v4:catA:ALL:' : 'outerprime:v4:catA:'.$district.':';

            if ($district === 'ALL') {
                // ===== Aggregate across ALL Outer Prime London outward codes (prefix join) =====
                $baseAll = DB::table('land_registry as lr')
                    ->join(DB::raw("(SELECT DISTINCT postcode FROM prime_postcodes WHERE category = 'Outer Prime London') as pp"),
                        DB::raw($this->normalizedPostcodeExpression('lr')), 'LIKE', DB::raw("(pp.postcode || '%')"))
                    ->where('lr.PPDCategoryType', 'A');

                // Median price by year
                $avgPrice = Cache::remember($keyBase.'avgPrice', $ttl, function () use ($baseAll, $yearColumnAll, $priceColumnAll) {
                    return (clone $baseAll)
                        ->selectRaw("{$yearColumnAll} as year, ROUND(".$this->medianPriceExpression($priceColumnAll).') as avg_price')
                        ->groupBy(DB::raw($yearColumnAll))
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // Sales count by year
                $sales = Cache::remember($keyBase.'sales', $ttl, function () use ($baseAll, $yearColumnAll) {
                    return (clone $baseAll)
                        ->selectRaw("{$yearColumnAll} as year, COUNT(*) as sales")
                        ->groupBy(DB::raw($yearColumnAll))
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // Property types by year
                $propertyTypes = Cache::remember($keyBase.'propertyTypes', $ttl, function () use ($baseAll, $yearColumnAll, $propertyTypeColumnAll) {
                    return (clone $baseAll)
                        ->selectRaw("{$yearColumnAll} as year, {$propertyTypeColumnAll} as type, COUNT(*) as count")
                        ->groupBy(DB::raw($yearColumnAll), 'type')
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // Median price by property type (D/S/T/F) per year
                $avgPriceByType = Cache::remember($keyBase.'avgPriceByType', $ttl, function () use ($baseAll, $yearColumnAll, $propertyTypeColumnAll, $priceColumnAll) {
                    return (clone $baseAll)
                        ->selectRaw("{$yearColumnAll} as year, SUBSTR({$propertyTypeColumnAll}, 1, 1) as type, ROUND(".$this->medianPriceExpression($priceColumnAll).') as avg_price')
                        ->whereNotNull('lr.PropertyType')
                        ->whereRaw("SUBSTR({$propertyTypeColumnAll}, 1, 1) IN ('D','S','T','F')")
                        ->groupBy(DB::raw($yearColumnAll), 'type')
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // New build vs existing (% of sales) per year
                $newBuildPct = Cache::remember($keyBase.'newBuildPct', $ttl, function () use ($baseAll, $yearColumnAll, $newBuildColumnAll) {
                    return (clone $baseAll)
                        ->selectRaw(
                            "{$yearColumnAll} as year, ".
                            "ROUND(100 * SUM(CASE WHEN {$newBuildColumnAll} = 'Y' THEN 1 ELSE 0 END) / COUNT(*), 1) as new_pct, ".
                            "ROUND(100 * SUM(CASE WHEN {$newBuildColumnAll} = 'N' THEN 1 ELSE 0 END) / COUNT(*), 1) as existing_pct"
                        )
                        ->whereNotNull('lr.NewBuild')
                        ->whereIn('lr.NewBuild', ['Y', 'N'])
                        ->groupBy(DB::raw($yearColumnAll))
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // Freehold vs Leasehold (% of sales) per year (Duration: F/L)
                $tenurePct = Cache::remember($keyBase.'tenurePct', $ttl, function () use ($baseAll, $yearColumnAll, $durationColumnAll) {
                    return (clone $baseAll)
                        ->selectRaw(
                            "{$yearColumnAll} as year, ".
                            "ROUND(100 * SUM(CASE WHEN {$durationColumnAll} = 'F' THEN 1 ELSE 0 END) / COUNT(*), 1) as free_pct, ".
                            "ROUND(100 * SUM(CASE WHEN {$durationColumnAll} = 'L' THEN 1 ELSE 0 END) / COUNT(*), 1) as lease_pct"
                        )
                        ->whereNotNull('lr.Duration')
                        ->whereIn('lr.Duration', ['F', 'L'])
                        ->groupBy(DB::raw($yearColumnAll))
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // 90th percentile (decile threshold) per year via window function
                $p90 = Cache::remember($keyBase.'p90', $ttl, function () use ($baseAll, $yearColumnAll, $priceColumnAll, $yearColumn, $priceColumn) {
                    $deciles = (clone $baseAll)
                        ->selectRaw("{$yearColumnAll}, {$priceColumnAll}, NTILE(10) OVER (PARTITION BY {$yearColumnAll} ORDER BY {$priceColumnAll}) as decile");

                    return DB::query()
                        ->fromSub($deciles, 't')
                        ->selectRaw("{$yearColumn} as year, MIN({$priceColumn}) as p90")
                        ->where('decile', 10)
                        ->groupBy('year')
                        ->orderBy('year')
                        ->get();
                });

                // Top 5% average per year via window ranking
                $top5 = Cache::remember($keyBase.'top5', $ttl, function () use ($baseAll, $yearColumnAll, $priceColumnAll, $yearColumn, $priceColumn) {
                    $rankedTop5 = (clone $baseAll)
                        ->selectRaw("{$yearColumnAll}, {$priceColumnAll}, ROW_NUMBER() OVER (PARTITION BY {$yearColumnAll} ORDER BY {$priceColumnAll} DESC) as rn, COUNT(*) OVER (PARTITION BY {$yearColumnAll}) as cnt");

                    return DB::query()
                        ->fromSub($rankedTop5, 'ranked')
                        ->selectRaw("{$yearColumn} as year, ROUND(AVG({$priceColumn})) as top5_avg")
                        ->whereRaw('rn <= ((cnt + 19) / 20)')
                        ->groupBy('year')
                        ->orderBy('year')
                        ->get();
                });

                // Top sale per year (for spike marker)
                $topSalePerYear = Cache::remember($keyBase.'topSalePerYear', $ttl, function () use ($baseAll, $yearColumnAll, $priceColumnAll) {
                    return (clone $baseAll)
                        ->selectRaw("{$yearColumnAll} as year, MAX({$priceColumnAll}) as top_sale")
                        ->groupBy(DB::raw($yearColumnAll))
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // Top 3 sales per year (for tooltip/context)
                $top3PerYear = Cache::remember($keyBase.'top3PerYear', $ttl, function () use ($baseAll, $yearColumnAll, $dateColumnAll, $postcodeColumnAll, $priceColumnAll) {
                    $rankedTop3 = (clone $baseAll)
                        ->selectRaw("{$yearColumnAll} as year, {$dateColumnAll} as Date, {$postcodeColumnAll} as Postcode, {$priceColumnAll} as Price, ROW_NUMBER() OVER (PARTITION BY {$yearColumnAll} ORDER BY {$priceColumnAll} DESC) as rn");

                    return DB::query()
                        ->fromSub($rankedTop3, 'r')
                        ->select('year', 'Date', 'Postcode', 'Price', 'rn')
                        ->where('rn', '<=', 3)
                        ->orderBy('year')
                        ->orderBy('rn')
                        ->get();
                });
            } else {
                // ===== Per-district (existing logic) =====
                // Median price by year
                $avgPrice = Cache::remember($keyBase.'avgPrice', $ttl, function () use ($district) {
                    return DB::table('land_registry')
                        ->selectRaw('YearDate as year, ROUND('.$this->medianPriceExpression('Price').') as avg_price')
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->groupBy('YearDate')
                        ->orderBy('YearDate')
                        ->get();
                });

                // Sales count by year
                $sales = Cache::remember($keyBase.'sales', $ttl, function () use ($district) {
                    return DB::table('land_registry')
                        ->selectRaw('YearDate as year, COUNT(*) as sales')
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->groupBy('YearDate')
                        ->orderBy('YearDate')
                        ->get();
                });

                // Property types by year
                $propertyTypes = Cache::remember($keyBase.'propertyTypes', $ttl, function () use ($district) {
                    return DB::table('land_registry')
                        ->selectRaw('YearDate as year, PropertyType as type, COUNT(*) as count')
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->groupBy('YearDate', 'type')
                        ->orderBy('YearDate')
                        ->get();
                });

                // Median price by property type (D/S/T/F) per year
                $avgPriceByType = Cache::remember($keyBase.'avgPriceByType', $ttl, function () use ($district) {
                    return DB::table('land_registry')
                        ->selectRaw('YearDate as year, SUBSTR(PropertyType, 1, 1) as type, ROUND('.$this->medianPriceExpression('Price').') as avg_price')
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('PropertyType')
                        ->whereRaw("SUBSTR(PropertyType, 1, 1) IN ('D','S','T','F')")
                        ->groupBy('YearDate', 'type')
                        ->orderBy('YearDate')
                        ->get();
                });

                // New build vs existing (% of sales) per year
                $newBuildPct = Cache::remember($keyBase.'newBuildPct', $ttl, function () use ($district) {
                    return DB::table('land_registry')
                        ->selectRaw(
                            'YearDate as year, '.
                            "ROUND(100 * SUM(CASE WHEN NewBuild = 'Y' THEN 1 ELSE 0 END) / COUNT(*), 1) as new_pct, ".
                            "ROUND(100 * SUM(CASE WHEN NewBuild = 'N' THEN 1 ELSE 0 END) / COUNT(*), 1) as existing_pct"
                        )
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('NewBuild')
                        ->whereIn('NewBuild', ['Y', 'N'])
                        ->groupBy('YearDate')
                        ->orderBy('YearDate')
                        ->get();
                });

                // Freehold vs Leasehold (% of sales) per year (Duration: F/L)
                $tenurePct = Cache::remember($keyBase.'tenurePct', $ttl, function () use ($district) {
                    return DB::table('land_registry')
                        ->selectRaw(
                            'YearDate as year, '.
                            "ROUND(100 * SUM(CASE WHEN Duration = 'F' THEN 1 ELSE 0 END) / COUNT(*), 1) as free_pct, ".
                            "ROUND(100 * SUM(CASE WHEN Duration = 'L' THEN 1 ELSE 0 END) / COUNT(*), 1) as lease_pct"
                        )
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('Duration')
                        ->whereIn('Duration', ['F', 'L'])
                        ->groupBy('YearDate')
                        ->orderBy('YearDate')
                        ->get();
                });

                // 90th percentile (threshold) per year via window function
                $p90 = Cache::remember($keyBase.'p90', $ttl, function () use ($district) {
                    $deciles = DB::table('land_registry')
                        ->selectRaw('YearDate, Price, NTILE(10) OVER (PARTITION BY YearDate ORDER BY Price) as decile')
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0);

                    return DB::query()
                        ->fromSub($deciles, 't')
                        ->selectRaw('YearDate as year, MIN(Price) as p90')
                        ->where('decile', 10)
                        ->groupBy('year')
                        ->orderBy('year')
                        ->get();
                });

                // Top 5% average per year via window ranking
                $top5 = Cache::remember($keyBase.'top5', $ttl, function () use ($district) {
                    $rankedTop5 = DB::table('land_registry')
                        ->selectRaw('YearDate, Price, ROW_NUMBER() OVER (PARTITION BY YearDate ORDER BY Price DESC) as rn, COUNT(*) OVER (PARTITION BY YearDate) as cnt')
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0);

                    return DB::query()
                        ->fromSub($rankedTop5, 'ranked')
                        ->selectRaw('YearDate as year, ROUND(AVG(Price)) as top5_avg')
                        ->whereRaw('rn <= ((cnt + 19) / 20)')
                        ->groupBy('year')
                        ->orderBy('year')
                        ->get();
                });

                // Top sale per year (for spike marker)
                $topSalePerYear = Cache::remember($keyBase.'topSalePerYear', $ttl, function () use ($district) {
                    return DB::table('land_registry')
                        ->selectRaw('YearDate as year, MAX(Price) as top_sale')
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0)
                        ->groupBy('YearDate')
                        ->orderBy('YearDate')
                        ->get();
                });

                // Top 3 sales per year (for tooltip/context)
                $top3PerYear = Cache::remember($keyBase.'top3PerYear', $ttl, function () use ($district) {
                    $rankedTop3 = DB::table('land_registry')
                        ->selectRaw('YearDate as year, Date, Postcode, Price, ROW_NUMBER() OVER (PARTITION BY YearDate ORDER BY Price DESC) as rn')
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0);

                    return DB::query()
                        ->fromSub($rankedTop3, 'r')
                        ->select('year', 'Date', 'Postcode', 'Price', 'rn')
                        ->where('rn', '<=', 3)
                        ->orderBy('year')
                        ->orderBy('rn')
                        ->get();
                });
            }

            $charts[$district] = [
                'avgPrice' => $avgPrice,
                'sales' => $sales,
                'propertyTypes' => $propertyTypes,
                'avgPriceByType' => $avgPriceByType,
                'newBuildPct' => $newBuildPct,
                'tenurePct' => $tenurePct,
                'p90' => $p90,
                'top5' => $top5,
                'topSalePerYear' => $topSalePerYear,
                'top3PerYear' => $top3PerYear,
            ];
        }

        // Notes per district from the prime_postcodes table
        $notes = DB::table('prime_postcodes')
            ->where('category', 'Outer Prime London')
            ->pluck('notes', 'postcode')
            ->toArray();

        // Reuse the same view as Prime controller for consistency
        return view('prime.home', [
            'pageTitle' => $pageTitle,
            'districts' => $allDistricts,
            'charts' => $charts,
            'notes' => $notes,
            'heroDescription' => 'Analysis of London\'s prestigious postcodes in Outer London. <span class="font-semibold">Category A sales only</span>. This represents the widely accepted postcodes in the Outer Prime areas. Some will refine that down further to specific streets or neighbourhoods, but for this site it\'s suitable for a broad overview.',
            'lastWarmCacheKeys' => [
                'outerprime:v4:catA:last_warm',
                'outerprime:v3:catA:last_warm',
                'outerprime:v2:catA:last_warm',
                'outerprime:v1:catA:last_warm',
            ],
            'allDistrictLabel' => 'All Outer Prime',
            'emptyDistrictMessage' => 'No Outer Prime districts found.',
            'allDistrictOverviewTitle' => 'All Outer Prime – Overview',
            'allDistrictOverviewDescription' => 'This section aggregates <strong>all Outer Prime London postcodes</strong> into a single area for year-by-year analysis.',
        ]);
    }

    private function medianPriceExpression(string $columnExpression = 'Price'): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return "PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY {$columnExpression})";
        }

        return "AVG({$columnExpression})";
    }

    private function quotedColumn(string $column, ?string $table = null): string
    {
        $prefix = $table ? $table.'.' : '';

        if (DB::connection()->getDriverName() === 'pgsql') {
            return $prefix.'"'.$column.'"';
        }

        return $prefix.$column;
    }

    private function normalizedPostcodeExpression(?string $table = null): string
    {
        return 'REPLACE('.$this->quotedColumn('Postcode', $table).", ' ', '')";
    }
}
