<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PrimeLondonController extends Controller
{
    // Prime Central London – Home
    public function home()
    {
        $yearColumn = $this->quotedColumn('YearDate');
        $priceColumn = $this->quotedColumn('Price');
        $propertyTypeColumn = $this->quotedColumn('PropertyType');
        $newBuildColumn = $this->quotedColumn('NewBuild');
        $durationColumn = $this->quotedColumn('Duration');
        $dateColumn = $this->quotedColumn('Date');
        $postcodeColumn = $this->quotedColumn('Postcode');
        $normalizedPostcode = $this->normalizedPostcodeExpression();
        $yearColumnAll = $this->quotedColumn('YearDate', 'land_registry');
        $priceColumnAll = $this->quotedColumn('Price', 'land_registry');
        $propertyTypeColumnAll = $this->quotedColumn('PropertyType', 'land_registry');
        $newBuildColumnAll = $this->quotedColumn('NewBuild', 'land_registry');
        $durationColumnAll = $this->quotedColumn('Duration', 'land_registry');
        $dateColumnAll = $this->quotedColumn('Date', 'land_registry');
        $postcodeColumnAll = $this->quotedColumn('Postcode', 'land_registry');
        $normalizedPostcodeAll = $this->normalizedPostcodeExpression('land_registry');

        // Prime districts from lookup table
        $districts = DB::table('prime_postcodes')
            ->where('category', 'Prime Central')
            ->pluck('postcode')
            ->unique()
            ->values();

        // Notes per postcode (for display in the blade)
        $notesByPostcode = DB::table('prime_postcodes')
            ->where('category', 'Prime Central')
            ->pluck('notes', 'postcode');

        // Include an aggregate "ALL" bucket to show charts across all PCL postcodes
        $allDistricts = collect(['ALL'])->merge($districts);

        $charts = [];
        $ttl = now()->addDays(45);

        foreach ($allDistricts as $district) {
            // Build a cache key base (ALL uses a dedicated namespace)
            $keyBase = $district === 'ALL' ? 'pcl:v5:catA:ALL:' : 'pcl:v5:catA:'.$district.':';

            if ($district === 'ALL') {
                // Treat ALL PCL as one area by joining to prime_postcodes (avoids IN/prefix mismatches)
                $applyAllPcl = function ($q) {
                    // Join to DISTINCT list of PCL outward codes and match by prefix
                    $q->join(DB::raw("(
                        SELECT DISTINCT postcode
                        FROM prime_postcodes
                        WHERE category = 'Prime Central'
                    ) as pp"),
                        function ($join) {
                            $join->on(DB::raw($this->normalizedPostcodeExpression('land_registry')), 'LIKE', DB::raw("(pp.postcode || '%')"));
                        });
                };

                // ***** Aggregate across ALL Prime Central districts *****
                // Median price by year (YearDate)
                $avgPrice = Cache::remember($keyBase.'avgPrice', $ttl, function () use ($applyAllPcl, $yearColumnAll, $priceColumnAll) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearColumnAll} as year, ROUND(".$this->medianPriceExpression($priceColumnAll).') as avg_price')
                        ->where('PPDCategoryType', 'A')
                        ->when(true, $applyAllPcl)
                        ->groupBy(DB::raw($yearColumnAll))
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // Sales count by year
                $sales = Cache::remember($keyBase.'sales', $ttl, function () use ($applyAllPcl, $yearColumnAll) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearColumnAll} as year, COUNT(*) as sales")
                        ->where('PPDCategoryType', 'A')
                        ->when(true, $applyAllPcl)
                        ->groupBy(DB::raw($yearColumnAll))
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // Property types by year (for stacked bar)
                $propertyTypes = Cache::remember($keyBase.'propertyTypes', $ttl, function () use ($applyAllPcl, $yearColumnAll, $propertyTypeColumnAll) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearColumnAll} as year, {$propertyTypeColumnAll} as type, COUNT(*) as count")
                        ->where('PPDCategoryType', 'A')
                        ->when(true, $applyAllPcl)
                        ->groupBy(DB::raw($yearColumnAll), 'type')
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // Median price by property type by year (D/S/T/F)
                $avgPriceByType = Cache::remember($keyBase.'avgPriceByType', $ttl, function () use ($applyAllPcl, $yearColumnAll, $propertyTypeColumnAll, $priceColumnAll) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearColumnAll} as year, SUBSTR({$propertyTypeColumnAll}, 1, 1) as type, ROUND(".$this->medianPriceExpression($priceColumnAll).') as avg_price')
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('PropertyType')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0)
                        ->whereRaw("SUBSTR({$propertyTypeColumnAll}, 1, 1) IN ('D','S','T','F')")
                        ->when(true, $applyAllPcl)
                        ->groupBy(DB::raw($yearColumnAll), 'type')
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // New build vs existing (% of sales) per year
                $newBuildPct = Cache::remember($keyBase.'newBuildPct', $ttl, function () use ($applyAllPcl, $yearColumnAll, $newBuildColumnAll) {
                    return DB::table('land_registry')
                        ->selectRaw(
                            "{$yearColumnAll} as year, ".
                            "ROUND(100 * SUM(CASE WHEN {$newBuildColumnAll} = 'Y' THEN 1 ELSE 0 END) / COUNT(*), 1) as new_pct, ".
                            "ROUND(100 * SUM(CASE WHEN {$newBuildColumnAll} = 'N' THEN 1 ELSE 0 END) / COUNT(*), 1) as existing_pct"
                        )
                        ->where('PPDCategoryType', 'A')
                        ->when(true, $applyAllPcl)
                        ->whereNotNull('NewBuild')
                        ->whereIn('NewBuild', ['Y', 'N'])
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0)
                        ->groupBy(DB::raw($yearColumnAll))
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // Freehold vs Leasehold (% of sales) per year (Duration: F/L)
                $tenurePct = Cache::remember($keyBase.'tenurePct', $ttl, function () use ($applyAllPcl, $yearColumnAll, $durationColumnAll) {
                    return DB::table('land_registry')
                        ->selectRaw(
                            "{$yearColumnAll} as year, ".
                            "ROUND(100 * SUM(CASE WHEN {$durationColumnAll} = 'F' THEN 1 ELSE 0 END) / COUNT(*), 1) as free_pct, ".
                            "ROUND(100 * SUM(CASE WHEN {$durationColumnAll} = 'L' THEN 1 ELSE 0 END) / COUNT(*), 1) as lease_pct"
                        )
                        ->where('PPDCategoryType', 'A')
                        ->when(true, $applyAllPcl)
                        ->whereNotNull('Duration')
                        ->whereIn('Duration', ['F', 'L'])
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0)
                        ->groupBy(DB::raw($yearColumnAll))
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // 90th percentile (decile threshold) per year via window function
                $p90 = Cache::remember($keyBase.'p90', $ttl, function () use ($applyAllPcl, $yearColumnAll, $priceColumnAll, $yearColumn, $priceColumn) {
                    $deciles = DB::table('land_registry')
                        ->selectRaw("{$yearColumnAll}, {$priceColumnAll}, NTILE(10) OVER (PARTITION BY {$yearColumnAll} ORDER BY {$priceColumnAll}) as decile")
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0)
                        ->when(true, $applyAllPcl);

                    return DB::query()
                        ->fromSub($deciles, 't')
                        ->selectRaw("{$yearColumn} as year, MIN({$priceColumn}) as p90")
                        ->where('decile', 10)
                        ->groupBy('year')
                        ->orderBy('year')
                        ->get();
                });

                // Top 5% average per year via window ranking
                $top5 = Cache::remember($keyBase.'top5', $ttl, function () use ($applyAllPcl, $yearColumnAll, $priceColumnAll, $yearColumn, $priceColumn) {
                    $ranked = DB::table('land_registry')
                        ->selectRaw("{$yearColumnAll}, {$priceColumnAll}, ROW_NUMBER() OVER (PARTITION BY {$yearColumnAll} ORDER BY {$priceColumnAll} DESC) as rn, COUNT(*) OVER (PARTITION BY {$yearColumnAll}) as cnt")
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0)
                        ->when(true, $applyAllPcl);

                    return DB::query()
                        ->fromSub($ranked, 'ranked')
                        ->selectRaw("{$yearColumn} as year, ROUND(AVG({$priceColumn})) as top5_avg")
                        ->whereRaw('rn <= ((cnt + 19) / 20)')
                        ->groupBy('year')
                        ->orderBy('year')
                        ->get();
                });

                // Top sale per year (for scatter)
                $topSalePerYear = Cache::remember($keyBase.'topSalePerYear', $ttl, function () use ($applyAllPcl, $yearColumnAll, $priceColumnAll) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearColumnAll} as year, MAX({$priceColumnAll}) as top_sale")
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0)
                        ->when(true, $applyAllPcl)
                        ->groupBy(DB::raw($yearColumnAll))
                        ->orderBy(DB::raw($yearColumnAll))
                        ->get();
                });

                // Top 3 sales per year (for tooltip)
                $top3PerYear = Cache::remember($keyBase.'top3PerYear', $ttl, function () use ($applyAllPcl, $yearColumnAll, $dateColumnAll, $postcodeColumnAll, $priceColumnAll) {
                    $ranked = DB::table('land_registry')
                        ->selectRaw("{$yearColumnAll} as year, {$dateColumnAll} as Date, {$postcodeColumnAll} as Postcode, {$priceColumnAll} as Price, ROW_NUMBER() OVER (PARTITION BY {$yearColumnAll} ORDER BY {$priceColumnAll} DESC) as rn")
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0)
                        ->when(true, $applyAllPcl);

                    return DB::query()
                        ->fromSub($ranked, 'r')
                        ->select('year', 'Date', 'Postcode', 'Price', 'rn')
                        ->where('rn', '<=', 3)
                        ->orderBy('year')
                        ->orderBy('rn')
                        ->get();
                });

            } else {
                // ***** Per-district (existing logic) *****
                // Median price by year (YearDate)
                $avgPrice = Cache::remember($keyBase.'avgPrice', $ttl, function () use ($district, $yearColumn, $priceColumn, $normalizedPostcode) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearColumn} as year, ROUND(".$this->medianPriceExpression($priceColumn).') as avg_price')
                        ->whereRaw($normalizedPostcode." LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->groupBy('YearDate')
                        ->orderBy('YearDate')
                        ->get();
                });

                // Sales count by year
                $sales = Cache::remember($keyBase.'sales', $ttl, function () use ($district, $yearColumn, $normalizedPostcode) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearColumn} as year, COUNT(*) as sales")
                        ->whereRaw($normalizedPostcode." LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->groupBy('YearDate')
                        ->orderBy('YearDate')
                        ->get();
                });

                // Property types by year (for stacked bar)
                $propertyTypes = Cache::remember($keyBase.'propertyTypes', $ttl, function () use ($district, $yearColumn, $propertyTypeColumn, $normalizedPostcode) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearColumn} as year, {$propertyTypeColumn} as type, COUNT(*) as count")
                        ->whereRaw($normalizedPostcode." LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->groupBy('YearDate', 'type')
                        ->orderBy('YearDate')
                        ->get();
                });

                // Median price by property type by year (D/S/T/F)
                $avgPriceByType = Cache::remember($keyBase.'avgPriceByType', $ttl, function () use ($district) {
                    return DB::table('land_registry')
                        ->selectRaw('YearDate as year, SUBSTR(PropertyType, 1, 1) as type, ROUND('.$this->medianPriceExpression('Price').') as avg_price')
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('PropertyType')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0)
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
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0)
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
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0)
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
                $top3PerYear = Cache::remember($keyBase.'top3PerYear', $ttl, function () use ($district) {
                    $ranked = DB::table('land_registry')
                        ->selectRaw('YearDate as year, Date, Postcode, Price, ROW_NUMBER() OVER (PARTITION BY YearDate ORDER BY Price DESC) as rn')
                        ->whereRaw("REPLACE(Postcode, ' ', '') LIKE (? || '%')", [$district])
                        ->where('PPDCategoryType', 'A')
                        ->whereNotNull('Price')
                        ->where('Price', '>', 0);

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
            'pageTitle' => 'Prime Central London',
            'districts' => $allDistricts,
            'charts' => $charts,
            'notes' => $notesByPostcode,
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
