<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UltraLondonController extends Controller
{
    // Ultra Prime Central London – Home
    public function home()
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

        // Ultra Prime districts from lookup table
        $districts = DB::table('prime_postcodes')
            ->where('category', 'Ultra Prime')
            ->pluck('postcode')
            ->unique()
            ->values();

        // Notes per postcode (Ultra Prime)
        $notesByPostcode = DB::table('prime_postcodes')
            ->where('category', 'Ultra Prime')
            ->pluck('notes', 'postcode');

        // Include an aggregate "ALL" bucket (treat all UPCL postcodes as one area)
        $allDistricts = collect(['ALL'])->merge($districts);

        $charts = [];
        $ttl = now()->addDays(45);

        foreach ($allDistricts as $district) {
            // Separate cache namespaces for ALL vs per-district
            $keyBase = $district === 'ALL' ? 'upcl:v7:catA:ALL:' : 'upcl:v7:catA:'.$district.':';

            if ($district === 'ALL') {
                // ===== Aggregate across ALL Ultra Prime outward codes (prefix join) =====
                // Base: land_registry joined to DISTINCT set of UPCL postcodes by prefix
                $baseAll = DB::table('land_registry as lr')
                    ->join(DB::raw("(SELECT DISTINCT postcode FROM prime_postcodes WHERE category = 'Ultra Prime') as pp"),
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

                // 90th percentile (decile threshold) per year
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

                // Top 5% average per year
                $top5 = Cache::remember($keyBase.'top5', $ttl, function () use ($baseAll, $yearColumnAll, $priceColumnAll, $yearColumn, $priceColumn) {
                    $ranked = (clone $baseAll)
                        ->selectRaw("{$yearColumnAll}, {$priceColumnAll}, ROW_NUMBER() OVER (PARTITION BY {$yearColumnAll} ORDER BY {$priceColumnAll} DESC) as rn, COUNT(*) OVER (PARTITION BY {$yearColumnAll}) as cnt");

                    return DB::query()
                        ->fromSub($ranked, 'ranked')
                        ->selectRaw("{$yearColumn} as year, ROUND(AVG({$priceColumn})) as top5_avg")
                        ->whereRaw('rn <= ((cnt + 19) / 20)')
                        ->groupBy('year')
                        ->orderBy('year')
                        ->get();
                });

                // Top sale per year
                $topSalePerYear = Cache::remember($keyBase.'topSalePerYear', $ttl, function () use ($baseAll, $yearColumnAll, $priceColumnAll) {
                    return (clone $baseAll)
                        ->selectRaw("{$yearColumnAll} as year, MAX({$priceColumnAll}) as top_sale")
                        ->groupBy(DB::raw($yearColumnAll))
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // Top 3 sales per year (for tooltip)
                $top3PerYear = Cache::remember($keyBase.'top3PerYear', $ttl, function () use ($baseAll, $yearColumnAll, $dateColumnAll, $postcodeColumnAll, $priceColumnAll) {
                    $ranked = (clone $baseAll)
                        ->selectRaw("{$yearColumnAll} as year, {$dateColumnAll} as Date, {$postcodeColumnAll} as Postcode, {$priceColumnAll} as Price, ROW_NUMBER() OVER (PARTITION BY {$yearColumnAll} ORDER BY {$priceColumnAll} DESC) as rn");

                    return DB::query()
                        ->fromSub($ranked, 'r')
                        ->select('year', 'Date', 'Postcode', 'Price', 'rn')
                        ->where('rn', '<=', 3)
                        ->orderBy('year')
                        ->orderBy('rn')
                        ->get();
                });

            } else {
                // ===== Per-district (existing logic) =====
                // Median price by year (YearDate)
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

                // Property types by year (for stacked bar)
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

                // Prime indicator – 90th percentile (approx via top decile threshold)
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

                // Prime indicator – Top 5% average (uses window functions)
                $top5 = Cache::remember($keyBase.'top5', $ttl, function () use ($district) {
                    $ranked = DB::table('land_registry')
                        ->selectRaw('YearDate, Price, ROW_NUMBER() OVER (PARTITION BY YearDate ORDER BY Price DESC) as rn, COUNT(*) OVER (PARTITION BY YearDate) as cnt')
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0);

                    return DB::query()
                        ->fromSub($ranked, 'ranked')
                        ->selectRaw('YearDate as year, ROUND(AVG(Price)) as top5_avg')
                        ->whereRaw('rn <= ((cnt + 19) / 20)')
                        ->groupBy('year')
                        ->orderBy('year')
                        ->get();
                });

                // Spike detector – Top sale per year (scatter ready)
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

                // Spike detector – Top 3 sales per year (for tooltip/context tables)
                $ranked = DB::table('land_registry')
                    ->selectRaw('YearDate as year, Date, Postcode, Price, ROW_NUMBER() OVER (PARTITION BY YearDate ORDER BY Price DESC) as rn')
                    ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                    ->where('PPDCategoryType', 'A')
                    ->whereNotNull('Price')
                    ->where('Price', '>', 0);

                $top3PerYear = Cache::remember($keyBase.'top3PerYear', $ttl, function () use ($ranked) {
                    return DB::query()
                        ->fromSub($ranked, 'r')
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

        return view('prime.home', [
            'pageTitle' => 'Ultra Prime Central London',
            'districts' => $allDistricts,
            'charts' => $charts,
            'notes' => $notesByPostcode,
            'heroDescription' => 'Analysis of London\'s most prestigious postcodes. <span class="font-semibold">Category A sales only</span>. This represents the widely accepted postcodes in the Ultra Prime areas. Some will refine that down further to specific streets or neighbourhoods, but for this site it\'s suitable for a broad overview.',
            'lastWarmCacheKeys' => [
                'upcl:v7:catA:last_warm',
                'upcl:v6:catA:last_warm',
                'upcl:v5:catA:last_warm',
            ],
            'allDistrictLabel' => 'All Ultra Prime',
            'emptyDistrictMessage' => 'No Ultra Prime districts found.',
            'allDistrictOverviewTitle' => 'All Ultra Prime – Overview',
            'allDistrictOverviewDescription' => 'This section aggregates <strong>all Ultra Prime London postcodes</strong> into a single area for year-by-year analysis.',
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
