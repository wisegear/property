<?php

namespace App\Http\Controllers;

use App\Models\Crime;
use App\Models\LandRegistry;
use App\Services\EpcMatcher;
use App\Services\FormAnalytics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PropertyController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24 * 45; // 45 days

    public function home(Request $request)
    {
        $window = $this->rollingWindow();
        $cachePrefix = $this->rollingCachePrefix($window['latest_month']);
        $latestMonth = $window['latest_month'];
        $rollingStart = $window['rolling_start'];
        $rollingEnd = $window['rolling_end'];
        $endMonths = $this->rollingEndMonths($window['latest_month']);

        $rollingMeta = Cache::remember("{$cachePrefix}:meta", self::CACHE_TTL, fn () => $this->serializeRollingWindow($window));

        $salesPayload = Cache::remember("{$cachePrefix}:sales", self::CACHE_TTL, fn () => $this->rollingPayload($window, $this->buildRollingSalesSeries($endMonths)));
        $salesByYear = $salesPayload['data'];

        $avgPricePayload = Cache::remember("{$cachePrefix}:avgPrice", self::CACHE_TTL, fn () => $this->rollingPayload($window, $this->buildRollingMedianSeries($endMonths)));
        $avgPriceByYear = $avgPricePayload['data'];

        // Monthly sales — last 24 months (England & Wales, Cat A)
        [$sales24Labels, $sales24Data] = Cache::remember(
            'dashboard:sales_last_24m:EW:catA:v2',
            self::CACHE_TTL,
            function () {
                // Seed a wider window so we can trim to the true last available month
                $seedMonths = 36;
                $seedStart = now()->startOfMonth()->subMonths($seedMonths - 1);
                $seedEnd = now()->startOfMonth();

                $raw = DB::table('land_registry')
                    ->selectRaw($this->monthStartExpression().' as month_start, COUNT(*) as sales')
                    ->where('PPDCategoryType', 'A')
                    ->whereDate('Date', '>=', $seedStart)
                    ->groupBy('month_start')
                    ->orderBy('month_start')
                    ->pluck('sales', 'month_start')
                    ->toArray();

                // Determine last month with data
                $keys = array_keys($raw);
                if (! empty($keys)) {
                    sort($keys); // ascending
                    $lastDataKey = end($keys); // e.g., '2025-08-01'
                    $seriesEnd = \Carbon\Carbon::createFromFormat('Y-m-d', $lastDataKey)->startOfMonth();
                } else {
                    // If nothing in window, use end of previous month
                    $seriesEnd = $seedEnd->copy()->subMonth();
                }

                // Build exactly 24 months ending at last available month
                $start = $seriesEnd->copy()->subMonths(23)->startOfMonth();

                $labels = [];
                $data = [];
                $cursor = $start->copy();
                while ($cursor->lte($seriesEnd)) {
                    $key = $cursor->format('Y-m-01');
                    $labels[] = $cursor->format('M Y');  // will be formatted to MM/YY in the tick callback
                    $data[] = (int) ($raw[$key] ?? 0);
                    $cursor->addMonth();
                }

                return [$labels, $data];
            }
        );

        $ewP90Payload = Cache::remember("{$cachePrefix}:p90", self::CACHE_TTL, fn () => $this->rollingPayload($window, $this->buildRollingP90Series($endMonths)));
        $ewP90 = $ewP90Payload['data'];

        $ewTop5Payload = Cache::remember("{$cachePrefix}:top5", self::CACHE_TTL, fn () => $this->rollingPayload($window, $this->buildRollingTop5Series($endMonths)));
        $ewTop5 = $ewTop5Payload['data'];

        $ewTopSalePayload = Cache::remember("{$cachePrefix}:topSale", self::CACHE_TTL, fn () => $this->rollingPayload($window, $this->buildRollingTopSaleSeries($endMonths)));
        $ewTopSalePerYear = $ewTopSalePayload['data'];

        $ewTop3Payload = Cache::remember("{$cachePrefix}:top3", self::CACHE_TTL, fn () => $this->rollingPayload($window, $this->buildRollingTop3Series($endMonths)));
        $ewTop3PerYear = $ewTop3Payload['data'];

        return view('property.home', compact(
            'salesByYear', 'avgPriceByYear', 'ewP90', 'ewTop5', 'ewTopSalePerYear', 'ewTop3PerYear',
            'sales24Labels', 'sales24Data', 'latestMonth', 'rollingStart', 'rollingEnd', 'rollingMeta'
        ));
    }

    public function search(Request $request)
    {
        // =========================================================
        // 1) INPUT: read and normalise the postcode from the querystring
        //    e.g. "WR53EU" or "WR5 3EU" → store as upper-case string
        // =========================================================
        $postcode = strtoupper(trim((string) $request->query('postcode', '')));

        // Helper: normalise a postcode to the standard spaced form used by ONSPD `pcds`.
        // Input may be "WR53EU" or "WR5 3EU" → output "WR5 3EU".
        $toPcds = function (string $pc) {
            $pc = strtoupper(trim($pc));
            $pc = preg_replace('/\s+/', '', $pc);
            if ($pc === '' || strlen($pc) < 5) {
                return null;
            }

            // Insert a space before the last 3 characters
            return substr($pc, 0, -3).' '.substr($pc, -3);
        };

        $coordsByPostcode = [];

        $results = null;

        if ($postcode !== '') {
            // -----------------------------------------------------
            // Validate basic UK postcode format (pragmatic regex)
            // This accepts with/without space; detailed edge-cases
            // are out of scope for speed/robustness here.
            // -----------------------------------------------------
            $request->validate([
                'postcode' => [
                    'required',
                    'regex:/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i',
                ],
            ]);
            FormAnalytics::record('property_search', [
                'postcode' => $postcode,
            ]);

            // -----------------------------------------------------
            // Sorting: whitelist of sortable columns exposed to UI
            // -----------------------------------------------------
            $sortableColumns = [
                'TransactionID',
                'Price',
                'Date',
                'PropertyType',
                'NewBuild',
                'Duration',
                'PAON',
                'SAON',
                'Street',
                'Locality',
                'TownCity',
                'District',
                'County',
                'PPDCategoryType',
            ];

            // Read desired sort field & direction; fall back safely
            $sort = $request->query('sort', 'Date');
            $dir = strtolower($request->query('dir', 'desc'));

            if (! in_array($sort, $sortableColumns)) {
                $sort = 'Date';
            }

            if (! in_array($dir, ['asc', 'desc'])) {
                $dir = 'desc';
            }

            // -----------------------------------------------------
            // QUERY: Postcode search
            //  - Only runs if a postcode is provided and valid
            //  - Returns a paginated, sortable result-set
            // -----------------------------------------------------
            $results = LandRegistry::query()
                ->select([
                    'TransactionID',
                    'Price',
                    'Date',
                    'PropertyType',
                    'NewBuild',
                    'Duration',
                    'PAON',
                    'SAON',
                    'Street',
                    'Locality',
                    'TownCity',
                    'District',
                    'County',
                    'Postcode',
                    'PPDCategoryType',
                ])
                ->where('Postcode', $postcode)
                ->whereIn('PPDCategoryType', ['A', 'B'])
                ->orderBy($sort, $dir)
                ->Paginate(15)
                ->appends(['postcode' => $postcode, 'sort' => $sort, 'dir' => $dir]); // keep query on pagination links

            // -----------------------------------------------------
            // ONSPD: Bulk fetch coordinates for the current page
            // (prevents per-row ONSPD lookups in the Blade).
            // -----------------------------------------------------
            $pagePostcodes = $results->getCollection()
                ->pluck('Postcode')
                ->filter()
                ->map(fn ($pc) => $toPcds((string) $pc))
                ->filter()
                ->unique()
                ->values();

            if ($pagePostcodes->isNotEmpty()) {
                // If multiple ONSPD rows exist historically, take the latest by `dointr`.
                // We do this by ordering desc and keeping the first seen per `pcds`.
                $rows = DB::table('onspd')
                    ->select(['pcds', 'lat', 'long', 'dointr'])
                    ->whereIn('pcds', $pagePostcodes)
                    ->orderBy('pcds')
                    ->orderByDesc('dointr')
                    ->get();

                foreach ($rows as $row) {
                    $pcds = (string) $row->pcds;
                    if (! isset($coordsByPostcode[$pcds])) {
                        $coordsByPostcode[$pcds] = [
                            'lat' => $row->lat !== null ? (float) $row->lat : null,
                            'lng' => $row->long !== null ? (float) $row->long : null,
                        ];
                    }
                }
            }
        }

        if ($results !== null) {
            $results->getCollection()->transform(function (LandRegistry $row) {
                $row->property_slug = $this->buildPropertySlug(
                    (string) ($row->Postcode ?? ''),
                    (string) ($row->PAON ?? ''),
                    (string) ($row->Street ?? ''),
                    $row->SAON !== null ? (string) $row->SAON : null
                );

                return $row;
            });
        }

        // =========================================================
        // 2) CACHED AGGREGATES FOR HOMEPAGE CHARTS
        //    These are cached for 1 day (86400s). When new monthly
        //    data is imported you can clear cache to refresh.
        // =========================================================

        // Total number of rows in land_registry
        $records = Cache::remember('land_registry_total_count', self::CACHE_TTL, function () {
            return LandRegistry::count();
        });

        // =========================================================
        // 3) RENDER: pass both search results (if any) and all
        //    cached aggregates for charts to the Blade view
        // =========================================================
        return view('property.search', compact('postcode', 'results', 'records', 'coordsByPostcode'))
            ->with(['sort' => $sort ?? 'Date', 'dir' => $dir ?? 'desc']);
    }

    public function heatmap()
    {
        $cacheKey = 'land_registry_heatmap:lsoa21:v2';

        if (! Cache::has($cacheKey)) {
            return response()->json([
                'status' => 'warming',
                'message' => 'Heatmap cache is warming. Run `php artisan property:heatmap-warm` to generate it.',
            ], 202);
        }

        $points = Cache::get($cacheKey, []);

        return response()->json($points);
    }

    public function points(Request $request)
    {
        $south = (float) $request->query('south');
        $west = (float) $request->query('west');
        $north = (float) $request->query('north');
        $east = (float) $request->query('east');
        $zoom = (int) $request->query('zoom', 6);
        $limit = (int) $request->query('limit', 5000);

        $limit = max(1000, min($limit, 15000));

        if ($zoom < 12) {
            return response()->json([
                'status' => 'zoom',
                'message' => 'Zoom in to load property points.',
            ], 202);
        }

        $bboxKey = sprintf('%.3f:%.3f:%.3f:%.3f:z%d:l%d', $south, $west, $north, $east, $zoom, $limit);

        $payload = Cache::remember('land_registry_points:'.$bboxKey, now()->addMinutes(10), function () use ($south, $west, $north, $east, $limit) {
            $rows = DB::table('onspd as o')
                ->join('land_registry as lr', 'lr.Postcode', '=', 'o.pcds')
                ->whereIn('lr.PPDCategoryType', ['A', 'B'])
                ->whereNotNull('o.lat')
                ->whereNotNull('o.long')
                ->whereBetween('o.lat', [$south, $north])
                ->whereBetween('o.long', [$west, $east])
                ->select([
                    'o.lat',
                    'o.long',
                    'lr.Postcode',
                    'lr.PAON',
                    'lr.SAON',
                    'lr.Street',
                    'lr.Price',
                    'lr.Date',
                    'lr.PPDCategoryType',
                ])
                ->limit($limit + 1)
                ->get();

            $truncated = $rows->count() > $limit;
            if ($truncated) {
                $rows = $rows->take($limit);
            }

            $points = $rows->map(function ($row) {
                $postcode = (string) ($row->Postcode ?? '');
                $paon = (string) ($row->PAON ?? '');
                $street = (string) ($row->Street ?? '');
                $saon = (string) ($row->SAON ?? '');

                return [
                    'lat' => (float) $row->lat,
                    'lng' => (float) $row->long,
                    'price' => $row->Price !== null ? (int) $row->Price : null,
                    'date' => $row->Date ? (string) $row->Date : null,
                    'address' => trim($paon.' '.$street),
                    'postcode' => $postcode,
                    'category' => (string) ($row->PPDCategoryType ?? ''),
                    'url' => route('property.show.slug', [
                        'slug' => $this->buildPropertySlug($postcode, $paon, $street, $saon),
                    ], false),
                ];
            })->values();

            return [
                'points' => $points,
                'truncated' => $truncated,
            ];
        });

        return response()->json($payload);
    }

    public function show(Request $request)
    {
        $postcodeInput = (string) $request->input('postcode', '');
        $paonInput = (string) $request->input('paon', '');
        $streetInput = (string) $request->input('street', '');
        $saonInput = $request->input('saon');
        $saonValue = $saonInput !== null ? (string) $saonInput : null;

        if (! $request->boolean('_from_slug')) {
            if (trim($postcodeInput) === '' || trim($paonInput) === '' || trim($streetInput) === '') {
                abort(404, 'Property not found');
            }

            $slug = $this->buildPropertySlug($postcodeInput, $paonInput, $streetInput, $saonValue);

            return redirect()->route('property.show.slug', ['slug' => $slug], 301);
        }

        // Full match on address parts
        $postcode = strtoupper(trim($postcodeInput));
        $paon = strtoupper(trim($paonInput));
        $street = strtoupper(trim($streetInput));
        $saon = $saonValue !== null && trim($saonValue) !== '' ? strtoupper(trim($saonValue)) : null;
        $slug = $this->buildPropertySlug($postcode, $paon, $street, $saon);

        $query = DB::table('land_registry')
            ->select('Date', 'Price', 'PropertyType', 'NewBuild', 'Duration', 'PAON', 'SAON', 'Street', 'Postcode', 'Locality', 'TownCity', 'District', 'County', 'PPDCategoryType')
            ->where('Postcode', $postcode)
            ->where('PAON', $paon)
            ->whereIn('PPDCategoryType', ['A', 'B']);

        // Treat empty Street as NULL-or-empty to maximise matches
        if (! empty($street)) {
            $query->where('Street', $street);
        } else {
            $query->where(function ($q) {
                $q->whereNull('Street')->orWhere('Street', '');
            });
        }

        if (! empty($saon)) {
            $query->where('SAON', $saon);
        } else {
            // treat empty string and NULL the same to maximize matches and use index
            $query->where(function ($q) {
                $q->whereNull('SAON')->orWhere('SAON', '');
            });
        }

        // Build a base cache key for this property
        $saonKey = $saon !== null && $saon !== '' ? $saon : 'NOSAON';
        $propertyCacheKeyBase = sprintf('property:%s:%s:%s:%s', $postcode, $paon, $street, $saonKey);

        $records = Cache::remember(
            $propertyCacheKeyBase.':records:v2:catAB',
            self::CACHE_TTL,
            function () use ($query) {
                return $query->orderBy('Date', 'desc')->limit(100)->get();
            }
        );

        if ($records->isEmpty()) {
            abort(404, 'Property not found');
        }

        // -----------------------------------------------------
        // ONSPD: Fetch centroid coordinates for this postcode
        // Use indexed `pcds` lookup (avoid REPLACE/UPPER scans).
        // -----------------------------------------------------
        $toPcds = function (?string $pc) {
            $pc = strtoupper(trim((string) $pc));
            $pc = preg_replace('/\s+/', '', $pc);
            if ($pc === '' || strlen($pc) < 5) {
                return null;
            }

            return substr($pc, 0, -3).' '.substr($pc, -3);
        };

        $pcds = $toPcds($postcode);
        $mapLat = null;
        $mapLong = null;

        if ($pcds) {
            $coordCacheKey = 'onspd:coords:pcds:'.$pcds;
            $coords = Cache::remember($coordCacheKey, now()->addDays(90), function () use ($pcds) {
                return DB::table('onspd')
                    ->select(['lat', 'long'])
                    ->where('pcds', $pcds)
                    ->first();
            });

            if ($coords) {
                $mapLat = $coords->lat !== null ? (float) $coords->lat : null;
                $mapLong = $coords->long !== null ? (float) $coords->long : null;
            }
        }

        $crimeData = collect();
        $crimeTrend = collect();
        $totalChange = 0.0;
        $topIncrease = null;
        $topDecrease = null;
        $crimeSummary = null;
        $crimeDirection = 'stable';
        $crimeTrendLabels = collect();
        $crimeTrendValues = collect();

        if ($mapLat !== null && $mapLong !== null) {
            $latestCrimeMonth = Crime::query()->max('month');

            if ($latestCrimeMonth !== null) {
                $currentWindowEnd = Carbon::parse((string) $latestCrimeMonth)->startOfMonth();
                $crimeWindowStart = $currentWindowEnd->copy()->subMonths(11);
                $previousWindowStart = $crimeWindowStart->copy()->subMonths(12);

                $crimeSummaryRows = Crime::query()
                    ->selectRaw('crime_type, COUNT(*) as total')
                    ->whereDate('month', '>=', $crimeWindowStart->toDateString())
                    ->whereBetween('latitude', [$mapLat - 0.005, $mapLat + 0.005])
                    ->whereBetween('longitude', [$mapLong - 0.005, $mapLong + 0.005])
                    ->whereNotNull('crime_type')
                    ->groupBy('crime_type')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get();

                $crimeTrendCounts = Crime::query()
                    ->selectRaw(
                        'crime_type,
                        SUM(CASE WHEN month >= ? THEN 1 ELSE 0 END) as current_total,
                        SUM(CASE WHEN month >= ? AND month < ? THEN 1 ELSE 0 END) as previous_total',
                        [
                            $crimeWindowStart->toDateString(),
                            $previousWindowStart->toDateString(),
                            $crimeWindowStart->toDateString(),
                        ]
                    )
                    ->whereDate('month', '>=', $previousWindowStart->toDateString())
                    ->whereDate('month', '<=', $currentWindowEnd->toDateString())
                    ->whereBetween('latitude', [$mapLat - 0.005, $mapLat + 0.005])
                    ->whereBetween('longitude', [$mapLong - 0.005, $mapLong + 0.005])
                    ->whereNotNull('crime_type')
                    ->groupBy('crime_type')
                    ->get()
                    ->map(function ($crime) {
                        $currentTotal = (int) $crime->current_total;
                        $previousTotal = (int) $crime->previous_total;
                        $crime->diff = $currentTotal - $previousTotal;
                        $crime->pct_change = $previousTotal > 0
                            ? round((($currentTotal - $previousTotal) * 100) / $previousTotal, 1)
                            : ($currentTotal > 0 ? 100.0 : 0.0);

                        return $crime;
                    })
                    ->values();

                $crimeTrendSeriesRows = Crime::query()
                    ->selectRaw('month, COUNT(*) as total')
                    ->whereDate('month', '>=', $previousWindowStart->toDateString())
                    ->whereDate('month', '<=', $currentWindowEnd->toDateString())
                    ->whereBetween('latitude', [$mapLat - 0.005, $mapLat + 0.005])
                    ->whereBetween('longitude', [$mapLong - 0.005, $mapLong + 0.005])
                    ->groupBy('month')
                    ->orderBy('month')
                    ->pluck('total', 'month');

                $trendLabels = [];
                $trendValues = [];
                $trendCursor = $previousWindowStart->copy();

                while ($trendCursor->lte($currentWindowEnd)) {
                    $monthKey = $trendCursor->toDateString();
                    $trendLabels[] = $trendCursor->format('M y');
                    $trendValues[] = (int) ($crimeTrendSeriesRows[$monthKey] ?? 0);
                    $trendCursor->addMonth();
                }

                $crimeTrendLabels = collect($trendLabels);
                $crimeTrendValues = collect($trendValues);

                $crimeTrendByType = $crimeTrendCounts->keyBy('crime_type');
                $crimeTotal = (int) $crimeSummaryRows->sum('total');

                $crimeData = $crimeSummaryRows->map(function ($crime) use ($crimeTotal, $crimeTrendByType) {
                    $crime->pct = $crimeTotal > 0
                        ? round(((int) $crime->total * 100) / $crimeTotal, 1)
                        : 0.0;
                    $crime->pct_change = (float) optional($crimeTrendByType->get($crime->crime_type))->pct_change;

                    return $crime;
                });

                $totalCurrent = (int) $crimeTrendCounts->sum('current_total');
                $totalPrevious = (int) $crimeTrendCounts->sum('previous_total');
                $totalChange = $totalPrevious > 0
                    ? round((($totalCurrent - $totalPrevious) * 100) / $totalPrevious, 1)
                    : ($totalCurrent > 0 ? 100.0 : 0.0);

                $crimeTrend = $crimeTrendCounts
                    ->map(function ($crime) use ($totalCurrent, $totalPrevious, $totalChange) {
                        $crime->total_current = $totalCurrent;
                        $crime->total_previous = $totalPrevious;
                        $crime->total_pct_change = $totalChange;

                        return $crime;
                    })
                    ->sortByDesc('pct_change')
                    ->values();

                if ($crimeTrend->isNotEmpty()) {
                    $topIncrease = $crimeTrend
                        ->filter(fn ($crime) => (float) $crime->pct_change > 0)
                        ->sortByDesc(fn ($crime) => (float) $crime->pct_change)
                        ->first();

                    if ($topIncrease === null) {
                        $topIncrease = $crimeTrend
                            ->sortByDesc(fn ($crime) => (float) $crime->pct_change)
                            ->first();
                    }

                    $topDecrease = $crimeTrend
                        ->filter(fn ($crime) => (float) $crime->pct_change < 0)
                        ->sortBy(fn ($crime) => (float) $crime->pct_change)
                        ->first();

                    if ($topDecrease === null) {
                        $topDecrease = $crimeTrend
                            ->sortBy(fn ($crime) => (float) $crime->pct_change)
                            ->first();
                    }
                }

                if ($totalChange > 10) {
                    $crimeDirection = 'rising';
                } elseif ($totalChange < -10) {
                    $crimeDirection = 'falling';
                }

                if ($topIncrease && $topIncrease->previous_total < 20) {
                    $topIncrease->pct_change_label = $topIncrease->pct_change.'% (low volume)';
                }

                $directionWord = $totalChange > 0 ? 'up' : 'down';

                $crimeSummary = "Crime is {$directionWord} ".abs($totalChange).'% over the past year';

                if ($topIncrease && $topDecrease) {
                    $crimeSummary .= ', driven by increases in '.strtolower($topIncrease->crime_type);
                    $crimeSummary .= ' and decreases in '.strtolower($topDecrease->crime_type).'.';
                } else {
                    $crimeSummary .= '.';
                }
            }
        }

        // -----------------------------------------------------
        // Deprivation (IMD) — resolve via ONSPD → LSOA (England/Wales)
        // -----------------------------------------------------
        $depr = null;
        $deprMsg = null;
        $lsoaLink = null;

        // Helper: check if a DB table exists
        $tableExists = function (string $table): bool {
            return Schema::hasTable($table);
        };

        // Helper: check if a column exists on a table
        $hasColumn = function (string $table, string $column): bool {
            return Schema::hasColumn($table, $column);
        };

        // Helper: fetch IMD row for an LSOA from whichever table/column exists
        $resolveImdForLsoa = function (string $lsoa) use ($tableExists, $hasColumn) {
            // Candidate tables (adjust if yours differs)
            $tables = [
                'imd2025',   // England (IoD/IMD 2025)
                'wimd2019',  // Wales (WIMD 2019)
            ];

            // Candidate key columns
            $keyCols = [
                // England IMD 2025
                'LSOA_Code_2021',

                // Wales WIMD 2019
                'LSOA_code',

                // Common variants
                'lsoa21cd', 'lsoa21', 'LSOA21CD',
                'lsoa11cd', 'lsoa11', 'LSOA11CD',
                'lsoa_code', 'lsoa', 'LSOA',
            ];

            // Candidate data columns (we'll also auto-detect below from information_schema)
            $rankCols = [
                // common
                'Index_of_Multiple_Deprivation_Rank',
                'rank', 'Rank', 'imd_rank', 'IMD_RANK', 'wimd_rank', 'WIMD_RANK',
                // likely overall fields
                'Overall_Rank', 'overall_rank', 'IMD_Rank', 'IMD_rank', 'WIMD_Rank', 'WIMD_rank',
                // versioned fields
                'IMD_Rank_2025', 'IMD_rank_2025', 'Rank_2025',
                'WIMD_Rank_2019', 'WIMD_rank_2019', 'Rank_2019',
            ];
            $decileCols = [
                'Index_of_Multiple_Deprivation_Decile',
                'decile', 'Decile', 'imd_decile', 'IMD_DECILE', 'wimd_decile', 'WIMD_DECILE',
                'Overall_Decile', 'overall_decile', 'IMD_Decile', 'IMD_decile', 'WIMD_Decile', 'WIMD_decile',
                'IMD_Decile_2025', 'IMD_decile_2025', 'Decile_2025',
                'WIMD_Decile_2019', 'WIMD_decile_2019', 'Decile_2019',
            ];
            $nameCols = ['LSOA_Name_2021', 'name', 'lsoa_name', 'lsoa21nm', 'LSOA21NM', 'lsoa11nm', 'LSOA11NM', 'LSOA_Name', 'LSOA_name'];

            foreach ($tables as $table) {
                if (! $tableExists($table)) {
                    continue;
                }

                // Cache column list for this table so we can auto-detect rank/decile/name columns
                $cols = Cache::remember('depr:cols:'.$table, now()->addDays(90), function () use ($table) {
                    try {
                        return array_map(
                            fn ($column) => (string) $column,
                            Schema::getColumnListing($table)
                        );
                    } catch (\Throwable $e) {
                        return [];
                    }
                });

                $pickCol = function (array $preferred) use ($cols) {
                    if (empty($cols)) {
                        return null;
                    }
                    // 1) exact match priority
                    foreach ($preferred as $p) {
                        if (in_array($p, $cols, true)) {
                            return $p;
                        }
                    }
                    // 2) case-insensitive exact match
                    $lc = array_map('strtolower', $cols);
                    foreach ($preferred as $p) {
                        $idx = array_search(strtolower($p), $lc, true);
                        if ($idx !== false) {
                            return $cols[$idx];
                        }
                    }
                    // 3) contains match (try to find an overall/imd/wimd rank/decile)
                    foreach ($preferred as $p) {
                        $needle = strtolower($p);
                        foreach ($cols as $c) {
                            if (str_contains(strtolower($c), $needle)) {
                                return $c;
                            }
                        }
                    }

                    return null;
                };

                // Prefer "overall" columns first if present, then fall back
                $autoRankCol = $pickCol([
                    'Index_of_Multiple_Deprivation_Rank',
                    'overall_rank', 'Overall_Rank',
                    'imd_rank', 'IMD_RANK',
                    'wimd_rank', 'WIMD_RANK',
                    'rank', 'Rank',
                ]);
                $autoDecileCol = $pickCol([
                    'Index_of_Multiple_Deprivation_Decile',
                    'overall_decile', 'Overall_Decile',
                    'imd_decile', 'IMD_DECILE',
                    'wimd_decile', 'WIMD_DECILE',
                    'decile', 'Decile',
                ]);
                $autoNameCol = $pickCol(['LSOA_Name_2021', 'lsoa_name', 'LSOA_Name', 'lsoa21nm', 'LSOA21NM', 'name']);

                // Prefer explicit mappings for known tables
                $forced = null;
                if ($table === 'imd2025') {
                    $forced = [
                        'key' => 'LSOA_Code_2021',
                        'name' => 'LSOA_Name_2021',
                        'rank' => 'Index_of_Multiple_Deprivation_Rank',
                        'decile' => 'Index_of_Multiple_Deprivation_Decile',
                    ];
                } elseif ($table === 'wimd2019') {
                    $forced = [
                        'key' => 'LSOA_code',
                        'name' => 'LSOA_name',
                        // WIMD dataset provided here includes an overall field `WIMD_2019`.
                        // We treat it as an overall rank and derive decile from rank/total.
                        'rank' => 'WIMD_2019',
                        'decile' => null,
                    ];
                }

                $keyCol = null;
                if ($forced && $hasColumn($table, $forced['key'])) {
                    $keyCol = $forced['key'];
                } else {
                    foreach ($keyCols as $c) {
                        if ($hasColumn($table, $c)) {
                            $keyCol = $c;
                            break;
                        }
                    }
                }

                if (! $keyCol) {
                    continue;
                }

                // Build a select list based on what actually exists
                $select = [$keyCol];

                // If we have explicit mappings, set them up first
                $rankCol = null;
                $decileCol = null;
                $nameCol = null;

                if ($forced) {
                    if (! empty($forced['rank']) && $hasColumn($table, $forced['rank'])) {
                        $rankCol = $forced['rank'];
                        $select[] = $rankCol;
                    }
                    if (! empty($forced['decile']) && $hasColumn($table, $forced['decile'])) {
                        $decileCol = $forced['decile'];
                        $select[] = $decileCol;
                    }
                    if (! empty($forced['name']) && $hasColumn($table, $forced['name'])) {
                        $nameCol = $forced['name'];
                        $select[] = $nameCol;
                    }
                }

                // If not forced, use auto-detection / fallbacks
                if (! $rankCol) {
                    $rankCol = $autoRankCol;
                    if (! $rankCol) {
                        foreach ($rankCols as $c) {
                            if ($hasColumn($table, $c)) {
                                $rankCol = $c;
                                break;
                            }
                        }
                    }
                    if ($rankCol) {
                        $select[] = $rankCol;
                    }
                }

                if (! $decileCol) {
                    $decileCol = $autoDecileCol;
                    if (! $decileCol) {
                        foreach ($decileCols as $c) {
                            if ($hasColumn($table, $c)) {
                                $decileCol = $c;
                                break;
                            }
                        }
                    }
                    if ($decileCol) {
                        $select[] = $decileCol;
                    }
                }

                if (! $nameCol) {
                    $nameCol = $autoNameCol;
                    if (! $nameCol) {
                        foreach ($nameCols as $c) {
                            if ($hasColumn($table, $c)) {
                                $nameCol = $c;
                                break;
                            }
                        }
                    }
                    if ($nameCol) {
                        $select[] = $nameCol;
                    }
                }

                $row = DB::table($table)
                    ->select($select)
                    ->where($keyCol, trim((string) $lsoa))
                    ->first();

                if ($row) {
                    // Total rows (for % calc) — cache it because COUNT(*) can be expensive
                    $total = Cache::remember('imd:total:'.$table, now()->addDays(90), function () use ($table) {
                        return (int) DB::table($table)->count();
                    });

                    $rank = $rankCol ? (int) ($row->{$rankCol} ?? 0) : 0;
                    $decile = $decileCol ? (int) ($row->{$decileCol} ?? 0) : 0;

                    // For Wales (wimd2019), decile is not present in this dataset — derive from rank/total
                    if ($table === 'wimd2019' && $decile === 0 && $rank > 0 && $total > 0) {
                        $decile = (int) max(1, min(10, ceil(($rank / $total) * 10)));
                    }

                    // If decile is missing but rank exists, derive decile from rank/total (fallback for other tables)
                    if ($decile === 0 && $rank > 0 && $total > 0) {
                        $decile = (int) max(1, min(10, ceil(($rank / $total) * 10)));
                    }

                    $pct = null;
                    if ($rank > 0 && $total > 0) {
                        $pct = round(($rank / $total) * 100, 1);
                    }

                    $name = $nameCol ? (string) ($row->{$nameCol} ?? '') : '';

                    return [
                        'table' => $table,
                        'rank' => $rank ?: null,
                        'decile' => $decile ?: null,
                        'name' => $name !== '' ? $name : null,
                        'total' => $total ?: null,
                        'pct' => $pct,
                    ];
                }
            }

            return null;
        };

        // Resolve the postcode to an LSOA via ONSPD.
        // Show deprivation if it looks like an English (E01...) or Welsh (W01...) LSOA code.
        if ($pcds) {
            $onspdRow = Cache::remember('onspd:row:pcds:'.$pcds, now()->addDays(90), function () use ($pcds) {
                return DB::table('onspd')->where('pcds', $pcds)->first();
            });

            if (! $onspdRow) {
                $deprMsg = 'Unable to resolve this postcode to ONSPD.';
            } else {
                $lsoa = $onspdRow->lsoa21 ?? $onspdRow->lsoa21cd ?? $onspdRow->LSOA21CD ?? null;
                if (! $lsoa) {
                    $lsoa = $onspdRow->lsoa11 ?? $onspdRow->lsoa11cd ?? $onspdRow->LSOA11CD ?? null;
                }

                // England LSOAs typically start with E01; Wales typically start with W01
                $lsoa = $lsoa ? trim((string) $lsoa) : null;
                $isEngland = $lsoa && str_starts_with($lsoa, 'E01');
                $isWales = $lsoa && str_starts_with($lsoa, 'W01');

                if (! $lsoa || (! $isEngland && ! $isWales)) {
                    $deprMsg = 'Unable to resolve this postcode to an English or Welsh LSOA.';
                } else {
                    $tableHint = $isEngland ? 'imd2025' : 'wimd2019';
                    $imd = Cache::remember('depr:lsoa:'.$tableHint.':'.$lsoa, now()->addDays(90), function () use ($resolveImdForLsoa, $lsoa) {
                        return $resolveImdForLsoa((string) $lsoa);
                    });

                    if (! $imd) {
                        $deprMsg = 'LSOA found, but no deprivation record could be located in the database.';
                    } else {
                        $depr = [
                            'lsoa21' => (string) $lsoa,
                            'name' => $imd['name'] ?? null,
                            'rank' => $imd['rank'] ?? null,
                            'decile' => $imd['decile'] ?? null,
                            'pct' => $imd['pct'] ?? null,
                            'total' => $imd['total'] ?? null,
                            // Reuse postcode centroid for map link
                            'lat' => $mapLat,
                            'long' => $mapLong,
                        ];

                        // Link to the deprivation detail page
                        if ($isEngland) {
                            $lsoaLink = route('deprivation.show', ['lsoa21cd' => (string) $lsoa]);
                        } elseif ($isWales) {
                            $lsoaLink = route('deprivation.wales.show', ['lsoa' => (string) $lsoa]);
                        } else {
                            $lsoaLink = null;
                        }
                    }
                }
            }
        } else {
            $deprMsg = 'Postcode missing; cannot resolve deprivation.';
        }

        // Build address (PAON, SAON, Street, Locality, Postcode, TownCity, District, County)
        $first = $records->first();

        // Determine property type from the most recent sale
        $propertyTypeCode = $first->PropertyType ?? null; // 'D','S','T','F','O'

        $propertyTypeMap = [
            'D' => 'Detached',
            'S' => 'Semi-Detached',
            'T' => 'Terraced',
            'F' => 'Flat',
            'O' => 'Other',
        ];

        $propertyTypeLabel = $propertyTypeMap[$propertyTypeCode] ?? 'property';

        // Normalised keys for cache lookups (trim to avoid trailing/leading space mismatches)
        $countyKey = trim((string) $first->County);
        $districtKey = trim((string) $first->District);
        $townKey = trim((string) $first->TownCity);
        $localityKey = trim((string) $first->Locality);
        $district = $first->District;
        $addressParts = [];
        $addressParts[] = trim($first->PAON);
        if (! empty(trim($first->SAON))) {
            $addressParts[] = trim($first->SAON);
        }
        $addressParts[] = trim($first->Street);
        if (! empty(trim($first->Locality))) {
            $addressParts[] = trim($first->Locality);
        }
        $addressParts[] = trim($first->Postcode);
        if (! empty(trim($first->TownCity))) {
            $addressParts[] = trim($first->TownCity);
        }
        if (! empty(trim($first->District))) {
            $addressParts[] = trim($first->District);
        }
        if (! empty(trim($first->County))) {
            $addressParts[] = trim($first->County);
        }
        $address = implode(', ', $addressParts);

        // EPC matching (postcode + fuzzy)
        $matcher = new EpcMatcher;
        $epcMatches = $matcher->findForProperty(
            $postcode,
            $paon,
            $saon,
            $street,
            now(), // reference date (could be first/last sale date if preferred)
            5
        );

        // --- Locality visibility gate to avoid unnecessary locality queries ---
        $locality = trim((string) $first->Locality);
        $town = trim((string) $first->TownCity);
        $districtName = trim((string) $first->District);
        $countyName = trim((string) $first->County);
        $norm = function ($v) {
            return strtolower(trim((string) $v));
        };
        $isSameCountyDistrict = ($norm($districtName) === $norm($countyName));
        $showLocalityCharts = ($locality !== '')
            && ($norm($locality) !== $norm($town))
            && ($norm($locality) !== $norm($districtName))
            && ($norm($locality) !== $norm($countyName));
        $yearExpr = $this->yearExpression();
        $medianExpr = $this->medianPriceExpression();

        // Fallback: Always define district datasets if county==district, even if hidden
        if ($isSameCountyDistrict) {
            $districtPriceHistory = collect();
            $districtSalesHistory = collect();
            $districtPropertyTypes = collect();
        }

        // Determine if town charts should be shown (town must be non-empty and distinct from District, County)
        $showTownCharts = ($town !== '')
            && ($norm($town) !== $norm($districtName))
            && ($norm($town) !== $norm($countyName));
        $showDistrictCharts = ($districtName !== '')
            && ($norm($districtName) !== $norm($countyName));

        $priceHistoryQuery = DB::table('land_registry')
            ->selectRaw("{$yearExpr} as year, ROUND({$medianExpr}) as avg_price")
            ->where('Postcode', $postcode)
            ->where('PAON', $paon)
            ->where('Street', $street);

        if (! empty($saon)) {
            $priceHistoryQuery->where('SAON', $saon);
        } else {
            $priceHistoryQuery->where(function ($q) {
                $q->whereNull('SAON')->orWhere('SAON', '');
            });
        }
        $priceHistoryQuery->where('PPDCategoryType', 'A');

        $priceHistory = Cache::remember(
            $propertyCacheKeyBase.':priceHistory:v4:catA',
            self::CACHE_TTL,
            function () use ($priceHistoryQuery, $yearExpr) {
                return $priceHistoryQuery->groupByRaw($yearExpr)->orderBy('year', 'asc')->get();
            }
        );

        $postcodePriceHistory = Cache::remember(
            'postcode:'.$postcode.':type:'.$propertyTypeCode.':priceHistory:v4:catA',
            self::CACHE_TTL,
            function () use ($postcode, $propertyTypeCode, $yearExpr, $medianExpr) {
                return DB::table('land_registry')
                    ->selectRaw("{$yearExpr} as year, ROUND({$medianExpr}) as avg_price")
                    ->where('Postcode', $postcode)
                    ->where('PropertyType', $propertyTypeCode)
                    ->where('PPDCategoryType', 'A')
                    ->groupByRaw($yearExpr)
                    ->orderBy('year', 'asc')
                    ->get();
            }
        );

        $postcodeSalesHistory = Cache::remember(
            'postcode:'.$postcode.':type:'.$propertyTypeCode.':salesHistory:v3:catA',
            self::CACHE_TTL,
            function () use ($postcode, $propertyTypeCode, $yearExpr) {
                return DB::table('land_registry')
                    ->selectRaw("{$yearExpr} as year, COUNT(*) as total_sales")
                    ->where('Postcode', $postcode)
                    ->where('PropertyType', $propertyTypeCode)
                    ->where('PPDCategoryType', 'A')
                    ->groupByRaw($yearExpr)
                    ->orderBy('year', 'asc')
                    ->get();
            }
        );

        $countyPriceHistory = Cache::remember(
            'county:priceHistory:v4:catA:'.$countyKey.':type:'.$propertyTypeCode,
            self::CACHE_TTL,
            function () use ($countyKey, $propertyTypeCode, $yearExpr, $medianExpr) {
                return DB::table('land_registry')
                    ->selectRaw("{$yearExpr} as year, ROUND({$medianExpr}) as avg_price")
                    ->where('County', $countyKey)
                    ->where('PropertyType', $propertyTypeCode)
                    ->where('PPDCategoryType', 'A')
                    ->groupByRaw($yearExpr)
                    ->orderBy('year', 'asc')
                    ->get();
            }
        );

        // Always assign districtPriceHistory
        if ($isSameCountyDistrict) {
            $districtPriceHistory = $countyPriceHistory;
        } else {
            $districtPriceHistory = Cache::remember(
                'district:priceHistory:v4:catA:'.$districtKey.':type:'.$propertyTypeCode,
                self::CACHE_TTL,
                function () use ($districtKey, $propertyTypeCode, $yearExpr, $medianExpr) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearExpr} as year, ROUND({$medianExpr}) as avg_price")
                        ->where('District', $districtKey)
                        ->where('PropertyType', $propertyTypeCode)
                        ->where('PPDCategoryType', 'A')
                        ->groupByRaw($yearExpr)
                        ->orderBy('year', 'asc')
                        ->get();
                }
            );
        }

        $countySalesHistory = Cache::remember(
            'county:salesHistory:v3:catA:'.$countyKey.':type:'.$propertyTypeCode,
            self::CACHE_TTL,
            function () use ($countyKey, $propertyTypeCode, $yearExpr) {
                return DB::table('land_registry')
                    ->selectRaw("{$yearExpr} as year, COUNT(*) as total_sales")
                    ->where('County', $countyKey)
                    ->where('PropertyType', $propertyTypeCode)
                    ->where('PPDCategoryType', 'A')
                    ->groupByRaw($yearExpr)
                    ->orderBy('year', 'asc')
                    ->get();
            }
        );

        // Always assign districtSalesHistory
        if ($isSameCountyDistrict) {
            $districtSalesHistory = $countySalesHistory;
        } else {
            $districtSalesHistory = Cache::remember(
                'district:salesHistory:v3:catA:'.$districtKey.':type:'.$propertyTypeCode,
                self::CACHE_TTL,
                function () use ($districtKey, $propertyTypeCode, $yearExpr) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearExpr} as year, COUNT(*) as total_sales")
                        ->where('District', $districtKey)
                        ->where('PropertyType', $propertyTypeCode)
                        ->where('PPDCategoryType', 'A')
                        ->groupByRaw($yearExpr)
                        ->orderBy('year', 'asc')
                        ->get();
                }
            );
        }

        $countyPropertyTypes = Cache::remember(
            'county:types:v2:catA:'.$countyKey,
            self::CACHE_TTL,
            function () use ($countyKey, $propertyTypeMap) {
                return DB::table('land_registry')
                    ->select('PropertyType', DB::raw('COUNT(*) as property_count'))
                    ->where('County', $countyKey)
                    ->where('PPDCategoryType', 'A')
                    ->groupBy('PropertyType')
                    ->orderByDesc('property_count')
                    ->get()
                    ->map(function ($row) use ($propertyTypeMap) {
                        return [
                            'label' => $propertyTypeMap[$row->PropertyType] ?? $row->PropertyType,
                            'value' => $row->property_count,
                        ];
                    });
            }
        );

        // Always assign districtPropertyTypes
        if ($isSameCountyDistrict) {
            $districtPropertyTypes = $countyPropertyTypes;
        } else {
            $districtPropertyTypes = Cache::remember(
                'district:types:v2:catA:'.$districtKey,
                self::CACHE_TTL,
                function () use ($districtKey, $propertyTypeMap) {
                    return DB::table('land_registry')
                        ->select('PropertyType', DB::raw('COUNT(*) as property_count'))
                        ->where('District', $districtKey)
                        ->where('PPDCategoryType', 'A')
                        ->groupBy('PropertyType')
                        ->orderByDesc('property_count')
                        ->get()
                        ->map(function ($row) use ($propertyTypeMap) {
                            return [
                                'label' => $propertyTypeMap[$row->PropertyType] ?? $row->PropertyType,
                                'value' => $row->property_count,
                            ];
                        });
                }
            );
        }

        // --- Town/City datasets (mirrors district/locality structures) ---
        if ($showTownCharts) {
            $townPriceHistory = Cache::remember(
                'town:priceHistory:v4:catA:'.$townKey.':type:'.$propertyTypeCode,
                self::CACHE_TTL,
                function () use ($townKey, $propertyTypeCode, $yearExpr, $medianExpr) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearExpr} as year, ROUND({$medianExpr}) as avg_price")
                        ->where('TownCity', $townKey)
                        ->where('PropertyType', $propertyTypeCode)
                        ->where('PPDCategoryType', 'A')
                        ->groupByRaw($yearExpr)
                        ->orderBy('year', 'asc')
                        ->get();
                }
            );

            $townSalesHistory = Cache::remember(
                'town:salesHistory:v3:catA:'.$townKey.':type:'.$propertyTypeCode,
                self::CACHE_TTL,
                function () use ($townKey, $propertyTypeCode, $yearExpr) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearExpr} as year, COUNT(*) as total_sales")
                        ->where('TownCity', $townKey)
                        ->where('PropertyType', $propertyTypeCode)
                        ->where('PPDCategoryType', 'A')
                        ->groupByRaw($yearExpr)
                        ->orderBy('year', 'asc')
                        ->get();
                }
            );

            $townPropertyTypes = Cache::remember(
                'town:types:v2:catA:'.$townKey,
                self::CACHE_TTL,
                function () use ($townKey, $propertyTypeMap) {
                    return DB::table('land_registry')
                        ->select('PropertyType', DB::raw('COUNT(*) as property_count'))
                        ->where('TownCity', $townKey)
                        ->where('PPDCategoryType', 'A')
                        ->groupBy('PropertyType')
                        ->orderByDesc('property_count')
                        ->get()
                        ->map(function ($row) use ($propertyTypeMap) {
                            return [
                                'label' => $propertyTypeMap[$row->PropertyType] ?? $row->PropertyType,
                                'value' => $row->property_count,
                            ];
                        });
                }
            );
        } else {
            // Always define town datasets, even if not shown
            $townPriceHistory = collect();
            $townSalesHistory = collect();
            $townPropertyTypes = collect();
        }

        // Locality datasets (only compute when locality is meaningful & distinct)
        if ($showLocalityCharts) {
            $localityPriceHistory = Cache::remember(
                'locality:priceHistory:v4:catA:'.$localityKey.':type:'.$propertyTypeCode,
                self::CACHE_TTL,
                function () use ($localityKey, $propertyTypeCode, $yearExpr, $medianExpr) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearExpr} as year, ROUND({$medianExpr}) as avg_price")
                        ->where('Locality', $localityKey)
                        ->where('PropertyType', $propertyTypeCode)
                        ->where('PPDCategoryType', 'A')
                        ->groupByRaw($yearExpr)
                        ->orderBy('year', 'asc')
                        ->get();
                }
            );

            $localitySalesHistory = Cache::remember(
                'locality:salesHistory:v3:catA:'.$localityKey.':type:'.$propertyTypeCode,
                self::CACHE_TTL,
                function () use ($localityKey, $propertyTypeCode, $yearExpr) {
                    return DB::table('land_registry')
                        ->selectRaw("{$yearExpr} as year, COUNT(*) as total_sales")
                        ->where('Locality', $localityKey)
                        ->where('PropertyType', $propertyTypeCode)
                        ->where('PPDCategoryType', 'A')
                        ->groupByRaw($yearExpr)
                        ->orderBy('year', 'asc')
                        ->get();
                }
            );

            $localityPropertyTypes = Cache::remember(
                'locality:types:v2:catA:'.$localityKey,
                self::CACHE_TTL,
                function () use ($localityKey, $propertyTypeMap) {
                    return DB::table('land_registry')
                        ->select('PropertyType', DB::raw('COUNT(*) as property_count'))
                        ->where('Locality', $localityKey)
                        ->where('PPDCategoryType', 'A')
                        ->groupBy('PropertyType')
                        ->orderByDesc('property_count')
                        ->get()
                        ->map(function ($row) use ($propertyTypeMap) {
                            return [
                                'label' => $propertyTypeMap[$row->PropertyType] ?? $row->PropertyType,
                                'value' => $row->property_count,
                            ];
                        });
                }
            );
        } else {
            // Always define locality datasets, even if not shown
            $localityPriceHistory = collect();
            $localitySalesHistory = collect();
            $localityPropertyTypes = collect();
        }

        $localityAreaLink = $showLocalityCharts ? $this->resolvePropertyAreaLink('locality', $locality) : null;
        $townAreaLink = $showTownCharts ? $this->resolvePropertyAreaLink('town', $town) : null;
        $districtAreaLink = $showDistrictCharts ? $this->resolvePropertyAreaLink('district', $districtName) : null;
        $countyAreaLink = ! empty($countyName) ? $this->resolvePropertyAreaLink('county', $countyName) : null;

        return view('property.show', [
            'results' => $records,
            'slug' => $slug,
            'address' => $address,
            'priceHistory' => $priceHistory,
            'postcodePriceHistory' => $postcodePriceHistory,
            'postcodeSalesHistory' => $postcodeSalesHistory,
            'countyPriceHistory' => $countyPriceHistory,
            'countySalesHistory' => $countySalesHistory,
            'countyPropertyTypes' => $countyPropertyTypes,
            'districtPriceHistory' => $districtPriceHistory,
            'districtSalesHistory' => $districtSalesHistory,
            'districtPropertyTypes' => $districtPropertyTypes,
            'townPriceHistory' => $townPriceHistory,
            'townSalesHistory' => $townSalesHistory,
            'townPropertyTypes' => $townPropertyTypes,
            'localityPriceHistory' => $localityPriceHistory,
            'localitySalesHistory' => $localitySalesHistory,
            'localityPropertyTypes' => $localityPropertyTypes,
            'epcMatches' => $epcMatches,
            'propertyTypeCode' => $propertyTypeCode,
            'propertyTypeLabel' => $propertyTypeLabel,
            'pcds' => $pcds,
            'mapLat' => $mapLat,
            'mapLong' => $mapLong,
            'crimeData' => $crimeData,
            'crimeTrend' => $crimeTrend,
            'totalChange' => $totalChange,
            'topIncrease' => $topIncrease,
            'topDecrease' => $topDecrease,
            'crimeSummary' => $crimeSummary,
            'crimeDirection' => $crimeDirection,
            'crimeTrendLabels' => $crimeTrendLabels,
            'crimeTrendValues' => $crimeTrendValues,
            'depr' => $depr,
            'deprMsg' => $deprMsg,
            'lsoaLink' => $lsoaLink,
            'localityAreaLink' => $localityAreaLink,
            'townAreaLink' => $townAreaLink,
            'districtAreaLink' => $districtAreaLink,
            'countyAreaLink' => $countyAreaLink,
        ]);

    }

    public function showBySlug(string $slug)
    {
        $resolved = $this->resolveAddressFromSlug($slug);

        if ($resolved === null) {
            abort(404, 'Property not found');
        }

        $request = Request::create('/property/show', 'GET', [
            'postcode' => $resolved['postcode'],
            'paon' => $resolved['paon'],
            'street' => $resolved['street'],
            'saon' => $resolved['saon'],
            '_from_slug' => 1,
        ]);

        return $this->show($request);
    }

    private function yearExpression(): string
    {
        if (Schema::hasColumn('land_registry', 'YearDate')) {
            return '"YearDate"';
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return 'CAST(strftime(\'%Y\', "Date") AS INTEGER)';
        }

        return 'EXTRACT(YEAR FROM "Date")::int';
    }

    /**
     * @return array{
     *     latest_month: Carbon,
     *     rolling_start: Carbon,
     *     rolling_end: Carbon,
     *     previous_start: Carbon,
     *     previous_end: Carbon
     * }
     */
    private function rollingWindow(): array
    {
        $latestDate = DB::table('land_registry')->max('Date');
        $latestMonth = $latestDate
            ? Carbon::parse($latestDate)->startOfMonth()
            : now()->startOfMonth();

        $rollingStart = $latestMonth->copy()->subMonths(11)->startOfMonth();
        $rollingEnd = $latestMonth->copy()->endOfMonth();

        return [
            'latest_month' => $latestMonth,
            'rolling_start' => $rollingStart,
            'rolling_end' => $rollingEnd,
            'previous_start' => $rollingStart->copy()->subYear(),
            'previous_end' => $rollingEnd->copy()->subYear(),
        ];
    }

    private function rollingCachePrefix(Carbon $latestMonth): string
    {
        return 'property:home:rolling:'.$latestMonth->format('Ym');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Carbon>
     */
    private function rollingEndMonths(Carbon $latestMonth): \Illuminate\Support\Collection
    {
        $earliestDate = DB::table('land_registry')->min('Date');

        if ($earliestDate === null) {
            return collect([$latestMonth->copy()]);
        }

        $earliestPossibleEnd = Carbon::parse($earliestDate)->startOfMonth()->addMonths(11);
        $firstEnd = $latestMonth->copy()->year($earliestPossibleEnd->year)->startOfMonth();

        if ($firstEnd->lt($earliestPossibleEnd)) {
            $firstEnd->addYear();
        }

        $endMonths = collect();
        $cursor = $firstEnd->copy();

        while ($cursor->lte($latestMonth)) {
            $endMonths->push($cursor->copy());
            $cursor->addYear();
        }

        return $endMonths->isNotEmpty() ? $endMonths : collect([$latestMonth->copy()]);
    }

    /**
     * @return array{year:int,start:Carbon,end:Carbon}
     */
    private function rollingRangeForEndMonth(Carbon $endMonth): array
    {
        return [
            'year' => $endMonth->year,
            'start' => $endMonth->copy()->subMonths(11)->startOfMonth(),
            'end' => $endMonth->copy()->endOfMonth(),
        ];
    }

    private function buildRollingSalesSeries(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $endMonths->map(function (Carbon $endMonth) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return (object) [
                'year' => $range['year'],
                'total' => DB::table('land_registry')
                    ->where('PPDCategoryType', 'A')
                    ->whereBetween('Date', [$range['start'], $range['end']])
                    ->count(),
            ];
        });
    }

    private function buildRollingMedianSeries(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        $medianExpr = $this->medianPriceExpression();

        return $endMonths->map(function (Carbon $endMonth) use ($medianExpr) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $avgPrice = DB::table('land_registry')
                ->where('PPDCategoryType', 'A')
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->selectRaw("ROUND({$medianExpr}) as avg_price")
                ->value('avg_price');

            return (object) [
                'year' => $range['year'],
                'avg_price' => $avgPrice !== null ? (int) $avgPrice : null,
            ];
        });
    }

    private function buildRollingP90Series(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $endMonths->map(function (Carbon $endMonth) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $sub = DB::table('land_registry')
                ->selectRaw('"Price", CUME_DIST() OVER (ORDER BY "Price") as cd')
                ->where('PPDCategoryType', 'A')
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereNotNull('Price')
                ->where('Price', '>', 0);

            $p90Price = DB::query()->fromSub($sub, 't')
                ->where('cd', '>=', 0.9)
                ->min('Price');

            return (object) [
                'year' => $range['year'],
                'p90_price' => $p90Price !== null ? (int) $p90Price : null,
            ];
        });
    }

    private function buildRollingTop5Series(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $endMonths->map(function (Carbon $endMonth) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $ranked = DB::table('land_registry')
                ->selectRaw('"Price", ROW_NUMBER() OVER (ORDER BY "Price" DESC) as rn, COUNT(*) OVER () as cnt')
                ->where('PPDCategoryType', 'A')
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereNotNull('Price')
                ->where('Price', '>', 0);

            $top5Average = DB::query()
                ->fromSub($ranked, 'r')
                ->selectRaw('ROUND(AVG("Price")) as top5_avg')
                ->whereColumn('rn', '<=', DB::raw('CEIL(0.05 * cnt)'))
                ->value('top5_avg');

            return (object) [
                'year' => $range['year'],
                'top5_avg' => $top5Average !== null ? (int) $top5Average : null,
            ];
        });
    }

    private function buildRollingTopSaleSeries(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $endMonths->map(function (Carbon $endMonth) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return (object) [
                'year' => $range['year'],
                'top_sale' => LandRegistry::query()
                    ->where('PPDCategoryType', 'A')
                    ->whereBetween('Date', [$range['start'], $range['end']])
                    ->whereNotNull('Price')
                    ->where('Price', '>', 0)
                    ->max('Price'),
            ];
        });
    }

    private function buildRollingTop3Series(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $endMonths->flatMap(function (Carbon $endMonth) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $rankedTop3 = DB::table('land_registry')
                ->selectRaw('"Date", "Postcode", "Price", ROW_NUMBER() OVER (ORDER BY "Price" DESC) as rn')
                ->where('PPDCategoryType', 'A')
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereNotNull('Price')
                ->where('Price', '>', 0);

            return DB::query()
                ->fromSub($rankedTop3, 'r')
                ->select('Date', 'Postcode', 'Price', 'rn')
                ->where('rn', '<=', 3)
                ->orderBy('rn')
                ->get()
                ->map(fn ($row) => (object) [
                    'year' => $range['year'],
                    'Date' => $row->Date,
                    'Postcode' => $row->Postcode,
                    'Price' => $row->Price,
                    'rn' => $row->rn,
                ]);
        })->values();
    }

    /**
     * @param  array{
     *     latest_month: Carbon,
     *     rolling_start: Carbon,
     *     rolling_end: Carbon,
     *     previous_start: Carbon,
     *     previous_end: Carbon
     * }  $window
     */
    private function serializeRollingWindow(array $window): array
    {
        return [
            'latest_month' => $window['latest_month']->toDateString(),
            'rolling_start' => $window['rolling_start']->toDateString(),
            'rolling_end' => $window['rolling_end']->toDateString(),
            'previous_start' => $window['previous_start']->toDateString(),
            'previous_end' => $window['previous_end']->toDateString(),
        ];
    }

    /**
     * @param  array{
     *     latest_month: Carbon,
     *     rolling_start: Carbon,
     *     rolling_end: Carbon,
     *     previous_start: Carbon,
     *     previous_end: Carbon
     * }  $window
     */
    private function rollingPayload(array $window, mixed $data): array
    {
        return [
            ...$this->serializeRollingWindow($window),
            'data' => $data,
        ];
    }

    private function rollingSalesTotal(Carbon $start, Carbon $end): int
    {
        return (int) DB::table('land_registry')
            ->where('PPDCategoryType', 'A')
            ->whereBetween('Date', [$start, $end])
            ->count();
    }

    private function rollingMedianPrice(Carbon $start, Carbon $end): ?int
    {
        $medianExpr = $this->medianPriceExpression();

        $value = DB::table('land_registry')
            ->where('PPDCategoryType', 'A')
            ->whereBetween('Date', [$start, $end])
            ->whereNotNull('Price')
            ->where('Price', '>', 0)
            ->selectRaw("ROUND({$medianExpr}) as avg_price")
            ->value('avg_price');

        return $value !== null ? (int) $value : null;
    }

    private function rollingP90Price(Carbon $start, Carbon $end): ?int
    {
        $sub = DB::table('land_registry')
            ->selectRaw('"Price", CUME_DIST() OVER (ORDER BY "Price") as cd')
            ->where('PPDCategoryType', 'A')
            ->whereBetween('Date', [$start, $end])
            ->whereNotNull('Price')
            ->where('Price', '>', 0);

        $value = DB::query()
            ->fromSub($sub, 't')
            ->where('cd', '>=', 0.9)
            ->min('Price');

        return $value !== null ? (int) $value : null;
    }

    private function rollingTop5Average(Carbon $start, Carbon $end): ?int
    {
        $ranked = DB::table('land_registry')
            ->selectRaw('"Price", ROW_NUMBER() OVER (ORDER BY "Price" DESC) as rn, COUNT(*) OVER () as cnt')
            ->where('PPDCategoryType', 'A')
            ->whereBetween('Date', [$start, $end])
            ->whereNotNull('Price')
            ->where('Price', '>', 0);

        $value = DB::query()
            ->fromSub($ranked, 'r')
            ->selectRaw('ROUND(AVG("Price")) as top5_avg')
            ->whereColumn('rn', '<=', DB::raw('CEIL(0.05 * cnt)'))
            ->value('top5_avg');

        return $value !== null ? (int) $value : null;
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
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return 'PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY "Price")';
        }

        return 'AVG("Price")';
    }

    private function buildPropertySlug(string $postcode, string $paon, string $street, ?string $saon = null): string
    {
        $parts = [
            $this->normalizeSlugPart($postcode),
            $this->normalizeSlugPart($paon),
            $this->normalizeSlugPart($street),
        ];

        if ($saon !== null && trim($saon) !== '') {
            $parts[] = $this->normalizeSlugPart($saon);
        }

        $parts = array_values(array_filter($parts, fn (string $part) => $part !== ''));

        return preg_replace('/-+/', '-', implode('-', $parts)) ?? '';
    }

    private function normalizeSlugPart(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(',', '', $normalized);
        $normalized = preg_replace('/\s+/', '-', $normalized) ?? '';
        $normalized = preg_replace('/-+/', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }

    private function resolveAddressFromSlug(string $slug): ?array
    {
        $normalizedSlug = $this->normalizeSlugPart($slug);
        $segments = array_values(array_filter(explode('-', $normalizedSlug), fn (string $segment) => $segment !== ''));

        if (count($segments) < 4) {
            return null;
        }

        $postcode = strtoupper($segments[0].' '.$segments[1]);
        $rows = DB::table('land_registry')
            ->select('Postcode', 'PAON', 'Street', 'SAON')
            ->where('Postcode', $postcode)
            ->whereIn('PPDCategoryType', ['A', 'B'])
            ->orderByDesc('Date')
            ->get();

        foreach ($rows as $row) {
            $candidateSlug = $this->buildPropertySlug(
                (string) ($row->Postcode ?? ''),
                (string) ($row->PAON ?? ''),
                (string) ($row->Street ?? ''),
                $row->SAON !== null ? (string) $row->SAON : null
            );

            if ($candidateSlug === $normalizedSlug) {
                return [
                    'postcode' => (string) ($row->Postcode ?? ''),
                    'paon' => (string) ($row->PAON ?? ''),
                    'street' => (string) ($row->Street ?? ''),
                    'saon' => $row->SAON !== null ? (string) $row->SAON : null,
                ];
            }
        }

        $paon = strtoupper((string) ($segments[2] ?? ''));
        $addressSegments = array_slice($segments, 3);
        $addressSegmentCount = count($addressSegments);

        if ($paon === '' || $addressSegmentCount === 0) {
            return null;
        }

        for ($streetLength = $addressSegmentCount; $streetLength >= 1; $streetLength--) {
            $street = strtoupper(implode(' ', array_slice($addressSegments, 0, $streetLength)));
            $saonParts = array_slice($addressSegments, $streetLength);
            $saon = count($saonParts) > 0 ? strtoupper(implode(' ', $saonParts)) : null;

            $query = DB::table('land_registry')
                ->select('Postcode', 'PAON', 'Street', 'SAON')
                ->where('Postcode', $postcode)
                ->where('PAON', $paon)
                ->where('Street', $street)
                ->whereIn('PPDCategoryType', ['A', 'B']);

            if ($saon !== null) {
                $query->where('SAON', $saon);
            } else {
                $query->where(function ($builder) {
                    $builder->whereNull('SAON')->orWhere('SAON', '');
                });
            }

            $match = $query->orderByDesc('Date')->first();

            if ($match) {
                return [
                    'postcode' => (string) ($match->Postcode ?? ''),
                    'paon' => (string) ($match->PAON ?? ''),
                    'street' => (string) ($match->Street ?? ''),
                    'saon' => $match->SAON !== null ? (string) $match->SAON : null,
                ];
            }
        }

        return null;
    }

    private function resolvePropertyAreaLink(string $type, string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $index = Cache::remember('property:area:index:v1', self::CACHE_TTL, function () {
            $jsonPath = public_path('data/property_districts.json');
            if (! file_exists($jsonPath)) {
                return [];
            }

            $areas = json_decode(file_get_contents($jsonPath), true) ?? [];
            $lookup = [];

            foreach ($areas as $area) {
                if (! is_array($area)) {
                    continue;
                }

                $areaType = strtolower((string) ($area['type'] ?? ''));
                $areaName = (string) ($area['name'] ?? $area['label'] ?? '');

                if ($areaType === '' || $areaName === '') {
                    continue;
                }

                $lookup[$areaType][strtolower($areaName)] = true;
            }

            return $lookup;
        });

        $normalizedType = strtolower($type);
        $normalizedName = strtolower($name);

        if (! isset($index[$normalizedType][$normalizedName])) {
            return null;
        }

        return route('property.area.show', [
            'type' => $normalizedType,
            'slug' => \Illuminate\Support\Str::slug($name),
        ], absolute: false);
    }
}
