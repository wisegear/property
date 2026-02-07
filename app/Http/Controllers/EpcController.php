<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EpcController extends Controller
{
    /**
     * EPC Dashboard
     *
     * Shows high‑level stats and a few quick charts/tables backed by simple,
     * index‑friendly queries. Reads from epc_certificates (E&W) or epc_certificates_scotland.
     */
    public function home()
    {
        $nation = request()->query('nation', 'ew'); // 'ew' | 'scotland'
        $driver = DB::connection()->getDriverName();

        // Nation-specific config to avoid duplicated query blocks
        $cfg = ($nation === 'scotland')
            ? [
                'table' => 'epc_certificates_scotland',
                'dateExpr' => $this->scotlandDateExpr($driver),
                'yearExpr' => $this->scotlandYearExpr($driver),
                'dateCol' => 'LODGEMENT_DATE',
                'currentCol' => 'CURRENT_ENERGY_RATING',
                'potentialCol' => 'POTENTIAL_ENERGY_RATING',
                'roomsCol' => 'NUMBER_HABITABLE_ROOMS',
                'ageCol' => 'CONSTRUCTION_AGE_BAND',
                'since' => Carbon::create(2015, 1, 1),
            ]
            : [
                'table' => 'epc_certificates',
                'dateExpr' => 'lodgement_date',
                'yearExpr' => $this->ewYearExpr($driver),
                'dateCol' => 'lodgement_date',
                'currentCol' => 'current_energy_rating',
                'potentialCol' => 'potential_energy_rating',
                'roomsCol' => 'number_habitable_rooms',
                'ageCol' => 'construction_age_band',
                'since' => Carbon::create(2008, 1, 1),
            ];

        $today = Carbon::today();
        $ttl = 60 * 60 * 24 * 45; // 45 days
        $ck = fn (string $k) => "epc:{$nation}:{$k}"; // cache key helper
        $ratings = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

        // 1) Totals & recency
        $stats = Cache::remember($ck('stats'), $ttl, function () use ($cfg, $today) {
            // Latest date from dataset
            $maxDate = DB::table($cfg['table'])
                ->selectRaw("MAX({$cfg['dateExpr']}) as d")
                ->value('d');

            $last30FromLatest = $maxDate ? Carbon::parse($maxDate)->copy()->subDays(30) : $today->copy()->subDays(30);

            $last30Count = $maxDate
                ? (int) DB::table($cfg['table'])
                    ->whereBetween(DB::raw($cfg['dateExpr']), [$last30FromLatest, $maxDate])
                    ->count()
                : 0;

            $last365Count = (int) DB::table($cfg['table'])
                ->whereBetween(DB::raw($cfg['dateExpr']), [$today->copy()->subDays(365), $today])
                ->count();

            return [
                'total' => (int) DB::table($cfg['table'])->count(),
                'latest_lodgement' => $maxDate,
                'last30_count' => $last30Count,
                'last365_count' => $last365Count,
            ];
        });

        // 2) Certificates by year
        $byYear = Cache::remember($ck('byYear'), $ttl, function () use ($cfg) {
            return DB::table($cfg['table'])
                ->selectRaw("{$cfg['yearExpr']} as yr, COUNT(*) as cnt")
                ->whereRaw("{$cfg['dateExpr']} IS NOT NULL")
                ->whereRaw("{$cfg['dateExpr']} >= ?", [$cfg['since']])
                ->groupBy('yr')
                ->orderBy('yr', 'asc')
                ->get();
        });

        // 3) Actual energy ratings by year (A–G only)
        $ratingByYear = Cache::remember($ck('ratingByYear'), $ttl, function () use ($cfg, $ratings) {
            return DB::table($cfg['table'])
                ->selectRaw("{$cfg['yearExpr']} as yr, {$cfg['currentCol']} as rating, COUNT(*) as cnt")
                ->whereRaw("{$cfg['dateExpr']} IS NOT NULL")
                ->whereRaw("{$cfg['dateExpr']} >= ?", [$cfg['since']])
                ->whereIn($cfg['currentCol'], $ratings)
                ->groupBy('yr', 'rating')
                ->orderBy('yr', 'asc')
                ->orderByRaw($this->ratingOrderExpression('rating'))
                ->get();
        });

        // 4) Potential energy ratings by year (A–G only)
        $potentialByYear = Cache::remember($ck('potentialByYear'), $ttl, function () use ($cfg, $ratings) {
            return DB::table($cfg['table'])
                ->selectRaw("{$cfg['yearExpr']} as yr, {$cfg['potentialCol']} as rating, COUNT(*) as cnt")
                ->whereRaw("{$cfg['dateExpr']} IS NOT NULL")
                ->whereRaw("{$cfg['dateExpr']} >= ?", [$cfg['since']])
                ->whereIn($cfg['potentialCol'], $ratings)
                ->groupBy('yr', 'rating')
                ->orderBy('yr', 'asc')
                ->orderByRaw($this->ratingOrderExpression('rating'))
                ->get();
        });

        // 6) Tenure by year: normalise variants into 3 buckets
        // EPC tenure values can vary (e.g. "Rented (private)" vs "Rental (private)").
        $tenureLabels = ['Owner-occupied', 'Rented (private)', 'Rented (social)'];

        $tenureByYear = Cache::remember($ck('tenureByYear'), $ttl, function () use ($cfg) {
            // Normalise common variants into our 3 labels. Anything else is ignored.
            $tenureCase = "CASE\n"
                ."  WHEN tenure IN ('Owner-occupied','Owner occupied','Owner Occupied','Owner-Occupied') THEN 'Owner-occupied'\n"
                ."  WHEN tenure IN ('Rented (private)','Rental (private)','Private rented','Private Rented','Rented - private','Rental - private') THEN 'Rented (private)'\n"
                ."  WHEN tenure IN ('Rented (social)','Rental (social)','Social rented','Social Rented','Rented - social','Rental - social') THEN 'Rented (social)'\n"
                ."  ELSE NULL\n"
                .'END';

            return DB::table($cfg['table'])
                ->selectRaw("{$cfg['yearExpr']} as yr, {$tenureCase} as tenure, COUNT(*) as cnt")
                ->whereRaw("{$cfg['dateExpr']} IS NOT NULL")
                ->whereRaw("{$cfg['dateExpr']} >= ?", [$cfg['since']])
                ->whereNotNull('tenure')
                ->groupBy('yr', 'tenure')
                ->orderBy('yr', 'asc')
                ->orderByRaw($this->tenureOrderExpression('tenure'))
                ->get();
        });

        // 5) Distribution of current ratings (optional for Scotland too)
        $ratingDist = Cache::remember($ck('ratingDist'), $ttl, function () use ($cfg) {
            return DB::table($cfg['table'])
                ->selectRaw("
                    CASE
                        WHEN {$cfg['currentCol']} IN ('A','B','C','D','E','F','G') THEN {$cfg['currentCol']}
                        WHEN {$cfg['currentCol']} IS NULL THEN 'Unknown'
                        ELSE 'Other'
                    END as rating,
                    COUNT(*) as cnt
                ")
                ->groupBy('rating')
                ->orderByRaw($this->ratingOrderExpression('rating', true))
                ->get();
        });

        return view('epc.home', [
            'stats' => $stats,
            'byYear' => $byYear,
            'ratingByYear' => $ratingByYear,
            'potentialByYear' => $potentialByYear,
            'tenureByYear' => $tenureByYear,
            'ratingDist' => $ratingDist ?? collect(),
            'nation' => $nation,
        ]);
    }

    private function ewYearExpr(string $driver): string
    {
        if ($driver === 'pgsql') {
            return 'EXTRACT(YEAR FROM "lodgement_date")::int';
        }

        return 'CAST(strftime(\'%Y\', "lodgement_date") AS INTEGER)';
    }

    private function scotlandDateExpr(string $driver): string
    {
        if ($driver === 'pgsql') {
            return 'CAST("LODGEMENT_DATE" AS date)';
        }

        return 'date("LODGEMENT_DATE")';
    }

    private function scotlandYearExpr(string $driver): string
    {
        if ($driver === 'pgsql') {
            return 'EXTRACT(YEAR FROM CAST("LODGEMENT_DATE" AS date))::int';
        }

        return 'CAST(strftime(\'%Y\', "LODGEMENT_DATE") AS INTEGER)';
    }

    private function ratingOrderExpression(string $column, bool $includeTail = false): string
    {
        $case = "CASE {$column}
            WHEN 'A' THEN 1
            WHEN 'B' THEN 2
            WHEN 'C' THEN 3
            WHEN 'D' THEN 4
            WHEN 'E' THEN 5
            WHEN 'F' THEN 6
            WHEN 'G' THEN 7";

        if ($includeTail) {
            $case .= "
            WHEN 'Other' THEN 8
            WHEN 'Unknown' THEN 9";
        }

        return $case.'
            ELSE 99
        END';
    }

    private function tenureOrderExpression(string $column): string
    {
        return "CASE {$column}
            WHEN 'Owner-occupied' THEN 1
            WHEN 'Rented (private)' THEN 2
            WHEN 'Rented (social)' THEN 3
            ELSE 99
        END";
    }

    private function resolveColumn(string $table, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    private function wrapColumn(string $column, ?string $table = null): string
    {
        $grammar = DB::connection()->getQueryGrammar();

        return $grammar->wrap($table ? "{$table}.{$column}" : $column);
    }

    // Search for EPC certificates by postcode (exact match)
    public function search(Request $request)
    {
        $epcTable = 'epc_certificates';
        $epcPostcodeColumn = $this->resolveColumn($epcTable, ['POSTCODE', 'postcode']);
        $epcLmkColumn = $this->resolveColumn($epcTable, ['LMK_KEY', 'lmk_key']);
        $epcAddressColumn = $this->resolveColumn($epcTable, ['ADDRESS', 'address']);
        $epcLodgementColumn = $this->resolveColumn($epcTable, ['LODGEMENT_DATE', 'lodgement_date']);
        $epcCurrentRatingColumn = $this->resolveColumn($epcTable, ['CURRENT_ENERGY_RATING', 'current_energy_rating']);
        $epcPotentialRatingColumn = $this->resolveColumn($epcTable, ['POTENTIAL_ENERGY_RATING', 'potential_energy_rating']);
        $epcPropertyTypeColumn = $this->resolveColumn($epcTable, ['PROPERTY_TYPE', 'property_type']);
        $epcFloorAreaColumn = $this->resolveColumn($epcTable, ['TOTAL_FLOOR_AREA', 'total_floor_area']);
        $epcLocalAuthorityColumn = $this->resolveColumn($epcTable, ['LOCAL_AUTHORITY_LABEL', 'local_authority_label']);

        // If no postcode provided, just render the form
        $postcodeInput = (string) $request->query('postcode', '');
        if (trim($postcodeInput) === '') {
            return view('epc.search');
        }

        // Validate: require a plausible UK postcode
        $request->validate([
            'postcode' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z]{1,2}\\d[A-Za-z\\d]?\\s*\\d[A-Za-z]{2}$/'],
        ], [
            'postcode.regex' => 'Please enter a full UK postcode (e.g. W11 3TH).',
        ]);

        // Normalise to canonical form: uppercase and single space before last 3 chars
        $postcode = $this->normalisePostcode($postcodeInput);

        // Sorting (whitelist fields to avoid SQL injection)
        $allowedSorts = [
            'lodgement_date' => $epcLodgementColumn,
            'address' => $epcAddressColumn,
            'current_energy_rating' => $epcCurrentRatingColumn,
            'potential_energy_rating' => $epcPotentialRatingColumn,
            'property_type' => $epcPropertyTypeColumn,
            'total_floor_area' => $epcFloorAreaColumn,
        ];
        $sort = $request->query('sort', 'lodgement_date');
        $dir = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortCol = $allowedSorts[$sort] ?? $epcLodgementColumn;

        // Query by postcode only with dynamic sorting
        $query = DB::table($epcTable)
            ->selectRaw($this->wrapColumn($epcLmkColumn).' as lmk_key')
            ->selectRaw($this->wrapColumn($epcAddressColumn).' as address')
            ->selectRaw($this->wrapColumn($epcPostcodeColumn).' as postcode')
            ->selectRaw($this->wrapColumn($epcLodgementColumn).' as lodgement_date')
            ->selectRaw($this->wrapColumn($epcCurrentRatingColumn).' as current_energy_rating')
            ->selectRaw($this->wrapColumn($epcPotentialRatingColumn).' as potential_energy_rating')
            ->selectRaw($this->wrapColumn($epcPropertyTypeColumn).' as property_type')
            ->selectRaw($this->wrapColumn($epcFloorAreaColumn).' as total_floor_area')
            ->selectRaw($this->wrapColumn($epcLocalAuthorityColumn).' as local_authority_label')
            ->where($epcPostcodeColumn, $postcode)
            ->orderBy($sortCol, $dir);

        // Secondary tiebreaker to keep results stable
        if ($sortCol !== $epcLodgementColumn) {
            $query->orderBy($epcLodgementColumn, 'desc');
        }

        $results = $query->paginate(50)->withQueryString();

        return view('epc.search', compact('results'));
    }

    /**
     * Map points for EPC certificates (E&W) joined to ONSUD by UPRN.
     */
    public function points(Request $request)
    {
        $epcTable = 'epc_certificates';
        $onsudTable = 'onsud';

        $epcUprnColumn = $this->resolveColumn($epcTable, ['UPRN', 'uprn']);
        $epcLodgementColumn = $this->resolveColumn($epcTable, ['LODGEMENT_DATE', 'lodgement_date']);
        $epcLmkColumn = $this->resolveColumn($epcTable, ['LMK_KEY', 'lmk_key']);
        $epcAddressColumn = $this->resolveColumn($epcTable, ['ADDRESS', 'address']);
        $epcPostcodeColumn = $this->resolveColumn($epcTable, ['POSTCODE', 'postcode']);
        $epcRatingColumn = $this->resolveColumn($epcTable, ['CURRENT_ENERGY_RATING', 'current_energy_rating']);

        $onsudUprnColumn = $this->resolveColumn($onsudTable, ['UPRN', 'uprn']);
        $onsudEastingColumn = $this->resolveColumn($onsudTable, ['GRIDGB1E', 'gridgb1e']);
        $onsudNorthingColumn = $this->resolveColumn($onsudTable, ['GRIDGB1N', 'gridgb1n']);
        $onsudCountryColumn = $this->resolveColumn($onsudTable, ['ctry25cd', 'CTRY25CD']);

        $zoom = (int) $request->query('zoom', 6);
        $limit = (int) $request->query('limit', 5000);

        $limit = max(1000, min($limit, 15000));

        if ($zoom < 12) {
            return response()->json([
                'status' => 'zoom',
                'message' => 'Zoom in to load EPC points.',
            ], 202);
        }

        $eMin = (int) $request->query('e_min');
        $eMax = (int) $request->query('e_max');
        $nMin = (int) $request->query('n_min');
        $nMax = (int) $request->query('n_max');

        if ($eMin === 0 || $eMax === 0 || $nMin === 0 || $nMax === 0) {
            return response()->json(['points' => []]);
        }

        $uprnRows = DB::table($onsudTable)
            ->select($onsudUprnColumn)
            ->whereNotNull($onsudEastingColumn)
            ->whereNotNull($onsudNorthingColumn)
            ->whereBetween($onsudEastingColumn, [$eMin, $eMax])
            ->whereBetween($onsudNorthingColumn, [$nMin, $nMax])
            ->whereIn($onsudCountryColumn, ['E92000001', 'W92000004'])
            ->whereNotNull($onsudUprnColumn)
            ->where($onsudUprnColumn, '!=', '')
            ->limit($limit + 1)
            ->get();

        $truncated = $uprnRows->count() > $limit;
        if ($truncated) {
            $uprnRows = $uprnRows->take($limit);
        }

        $uprns = $uprnRows->pluck($onsudUprnColumn)->filter()->values()->all();
        if (empty($uprns)) {
            return response()->json([
                'points' => [],
                'truncated' => false,
            ]);
        }

        $epcUprnWrapped = $this->wrapColumn($epcUprnColumn);
        $epcLodgementWrapped = $this->wrapColumn($epcLodgementColumn);
        $latestByUprn = DB::table($epcTable)
            ->selectRaw("{$epcUprnWrapped} as uprn")
            ->selectRaw("MAX({$epcLodgementWrapped}) as max_date")
            ->whereIn($epcUprnColumn, $uprns)
            ->groupBy('uprn');

        $rows = DB::table($epcTable.' as e')
            ->joinSub($latestByUprn, 'latest', function ($join) use ($epcUprnColumn, $epcLodgementColumn) {
                $join->on(DB::raw($this->wrapColumn($epcUprnColumn, 'e')), '=', 'latest.uprn')
                    ->on(DB::raw($this->wrapColumn($epcLodgementColumn, 'e')), '=', 'latest.max_date');
            })
            ->whereIn(DB::raw($this->wrapColumn($epcUprnColumn, 'e')), $uprns)
            ->selectRaw($this->wrapColumn($epcUprnColumn, 'e').' as uprn')
            ->selectRaw($this->wrapColumn($epcLmkColumn, 'e').' as lmk_key')
            ->selectRaw($this->wrapColumn($epcAddressColumn, 'e').' as address')
            ->selectRaw($this->wrapColumn($epcPostcodeColumn, 'e').' as postcode')
            ->selectRaw($this->wrapColumn($epcLodgementColumn, 'e').' as lodgement_date')
            ->selectRaw($this->wrapColumn($epcRatingColumn, 'e').' as rating')
            ->limit($limit + 1)
            ->get();

        if ($rows->count() > $limit) {
            $truncated = true;
            $rows = $rows->take($limit);
        }

        $epcUprns = $rows->pluck('uprn')->filter()->unique()->values()->all();
        $coords = DB::table($onsudTable)
            ->whereIn($onsudUprnColumn, $epcUprns)
            ->selectRaw($this->wrapColumn($onsudUprnColumn).' as uprn')
            ->selectRaw($this->wrapColumn($onsudEastingColumn).' as easting')
            ->selectRaw($this->wrapColumn($onsudNorthingColumn).' as northing')
            ->get()
            ->keyBy('uprn');

        $points = $rows->map(function ($row) use ($coords) {
            $uprn = (string) $row->uprn;
            if (! isset($coords[$uprn])) {
                return null;
            }
            $coord = $coords[$uprn];
            $lmk = (string) $row->lmk_key;

            return [
                'easting' => $coord->easting !== null ? (int) $coord->easting : null,
                'northing' => $coord->northing !== null ? (int) $coord->northing : null,
                'lmk_key' => $lmk,
                'address' => (string) $row->address,
                'postcode' => (string) $row->postcode,
                'lodgement_date' => (string) $row->lodgement_date,
                'rating' => (string) $row->rating,
                'url' => route('epc.show', ['lmk' => $lmk], false),
            ];
        })->filter()->values();

        return response()->json([
            'points' => $points,
            'truncated' => $truncated,
        ]);
    }

    /**
     * Map points for Scotland EPC certificates (joined to ONSUD by OSG_REFERENCE_NUMBER/UPRN).
     */
    public function pointsScotland(Request $request)
    {
        $epcTable = 'epc_certificates_scotland';
        $onsudTable = 'onsud';

        $scotUprnColumn = $this->resolveColumn($epcTable, ['OSG_REFERENCE_NUMBER', 'osg_reference_number']);
        $scotLodgementColumn = $this->resolveColumn($epcTable, ['LODGEMENT_DATE', 'lodgement_date']);
        $scotRrnColumn = $this->resolveColumn($epcTable, ['REPORT_REFERENCE_NUMBER', 'report_reference_number']);
        $scotAddress1Column = $this->resolveColumn($epcTable, ['ADDRESS1', 'address1']);
        $scotAddress2Column = $this->resolveColumn($epcTable, ['ADDRESS2', 'address2']);
        $scotAddress3Column = $this->resolveColumn($epcTable, ['ADDRESS3', 'address3']);
        $scotPostcodeColumn = $this->resolveColumn($epcTable, ['POSTCODE', 'postcode']);
        $scotRatingColumn = $this->resolveColumn($epcTable, ['CURRENT_ENERGY_RATING', 'current_energy_rating']);

        $onsudUprnColumn = $this->resolveColumn($onsudTable, ['UPRN', 'uprn']);
        $onsudEastingColumn = $this->resolveColumn($onsudTable, ['GRIDGB1E', 'gridgb1e']);
        $onsudNorthingColumn = $this->resolveColumn($onsudTable, ['GRIDGB1N', 'gridgb1n']);
        $onsudCountryColumn = $this->resolveColumn($onsudTable, ['ctry25cd', 'CTRY25CD']);

        $zoom = (int) $request->query('zoom', 6);
        $limit = (int) $request->query('limit', 5000);

        $limit = max(1000, min($limit, 15000));

        if ($zoom < 12) {
            return response()->json([
                'status' => 'zoom',
                'message' => 'Zoom in to load EPC points.',
            ], 202);
        }

        $eMin = (int) $request->query('e_min');
        $eMax = (int) $request->query('e_max');
        $nMin = (int) $request->query('n_min');
        $nMax = (int) $request->query('n_max');

        if ($eMin === 0 || $eMax === 0 || $nMin === 0 || $nMax === 0) {
            return response()->json(['points' => []]);
        }

        $uprnRows = DB::table($onsudTable)
            ->select($onsudUprnColumn)
            ->whereNotNull($onsudEastingColumn)
            ->whereNotNull($onsudNorthingColumn)
            ->whereBetween($onsudEastingColumn, [$eMin, $eMax])
            ->whereBetween($onsudNorthingColumn, [$nMin, $nMax])
            ->whereIn($onsudCountryColumn, ['S92000003'])
            ->whereNotNull($onsudUprnColumn)
            ->where($onsudUprnColumn, '!=', '')
            ->limit($limit + 1)
            ->get();

        $truncated = $uprnRows->count() > $limit;
        if ($truncated) {
            $uprnRows = $uprnRows->take($limit);
        }

        $uprns = $uprnRows->pluck($onsudUprnColumn)->filter()->values()->all();
        if (empty($uprns)) {
            return response()->json([
                'points' => [],
                'truncated' => false,
            ]);
        }

        $scotUprnWrapped = $this->wrapColumn($scotUprnColumn);
        $scotLodgementWrapped = $this->wrapColumn($scotLodgementColumn);
        $latestByUprn = DB::table($epcTable)
            ->selectRaw("{$scotUprnWrapped} as uprn")
            ->selectRaw("MAX({$scotLodgementWrapped}) as max_date")
            ->whereIn($scotUprnColumn, $uprns)
            ->groupBy('uprn');

        $rows = DB::table($epcTable.' as e')
            ->joinSub($latestByUprn, 'latest', function ($join) use ($scotUprnColumn, $scotLodgementColumn) {
                $join->on(DB::raw($this->wrapColumn($scotUprnColumn, 'e')), '=', 'latest.uprn')
                    ->on(DB::raw($this->wrapColumn($scotLodgementColumn, 'e')), '=', 'latest.max_date');
            })
            ->whereIn(DB::raw($this->wrapColumn($scotUprnColumn, 'e')), $uprns)
            ->selectRaw($this->wrapColumn($scotUprnColumn, 'e').' as uprn')
            ->selectRaw($this->wrapColumn($scotRrnColumn, 'e').' as rrn')
            ->selectRaw($this->wrapColumn($scotAddress1Column, 'e').' as address1')
            ->selectRaw($this->wrapColumn($scotAddress2Column, 'e').' as address2')
            ->selectRaw($this->wrapColumn($scotAddress3Column, 'e').' as address3')
            ->selectRaw($this->wrapColumn($scotPostcodeColumn, 'e').' as postcode')
            ->selectRaw($this->wrapColumn($scotLodgementColumn, 'e').' as lodgement_date')
            ->selectRaw($this->wrapColumn($scotRatingColumn, 'e').' as rating')
            ->limit($limit + 1)
            ->get();

        if ($rows->count() > $limit) {
            $truncated = true;
            $rows = $rows->take($limit);
        }

        $epcUprns = $rows->pluck('uprn')->filter()->unique()->values()->all();
        $coords = DB::table($onsudTable)
            ->whereIn($onsudUprnColumn, $epcUprns)
            ->selectRaw($this->wrapColumn($onsudUprnColumn).' as uprn')
            ->selectRaw($this->wrapColumn($onsudEastingColumn).' as easting')
            ->selectRaw($this->wrapColumn($onsudNorthingColumn).' as northing')
            ->get()
            ->keyBy('uprn');

        $points = $rows->map(function ($row) use ($coords) {
            $uprn = (string) $row->uprn;
            if (! isset($coords[$uprn])) {
                return null;
            }
            $coord = $coords[$uprn];
            $rrn = (string) $row->rrn;
            $address = trim(implode(', ', array_filter([
                $row->address1 ?? null,
                $row->address2 ?? null,
                $row->address3 ?? null,
            ])));

            return [
                'easting' => $coord->easting !== null ? (int) $coord->easting : null,
                'northing' => $coord->northing !== null ? (int) $coord->northing : null,
                'rrn' => $rrn,
                'address' => $address,
                'postcode' => (string) $row->postcode,
                'lodgement_date' => (string) $row->lodgement_date,
                'rating' => (string) $row->rating,
                'url' => $rrn !== '' ? route('epc.scotland.show', ['rrn' => $rrn], false) : null,
            ];
        })->filter()->values();

        return response()->json([
            'points' => $points,
            'truncated' => $truncated,
        ]);
    }

    // Scotland: Search EPC certificates by postcode (exact match)
    public function searchScotland(Request $request)
    {
        $scotTable = 'epc_certificates_scotland';
        $scotPostcodeColumn = $this->resolveColumn($scotTable, ['POSTCODE', 'postcode']);
        $scotRrnColumn = $this->resolveColumn($scotTable, ['REPORT_REFERENCE_NUMBER', 'report_reference_number']);
        $scotBuildingColumn = $this->resolveColumn($scotTable, ['BUILDING_REFERENCE_NUMBER', 'building_reference_number']);
        $scotAddress1Column = $this->resolveColumn($scotTable, ['ADDRESS1', 'address1']);
        $scotAddress2Column = $this->resolveColumn($scotTable, ['ADDRESS2', 'address2']);
        $scotAddress3Column = $this->resolveColumn($scotTable, ['ADDRESS3', 'address3']);
        $scotLodgementColumn = $this->resolveColumn($scotTable, ['LODGEMENT_DATE', 'lodgement_date']);
        $scotCurrentRatingColumn = $this->resolveColumn($scotTable, ['CURRENT_ENERGY_RATING', 'current_energy_rating']);
        $scotPotentialRatingColumn = $this->resolveColumn($scotTable, ['POTENTIAL_ENERGY_RATING', 'potential_energy_rating']);
        $scotPropertyTypeColumn = $this->resolveColumn($scotTable, ['PROPERTY_TYPE', 'property_type']);
        $scotFloorAreaColumn = $this->resolveColumn($scotTable, ['TOTAL_FLOOR_AREA', 'total_floor_area']);
        $scotLocalAuthorityColumn = $this->resolveColumn($scotTable, ['LOCAL_AUTHORITY_LABEL', 'local_authority_label']);

        // If no postcode provided, just render the Scotland form
        $postcodeInput = (string) $request->query('postcode', '');
        if (trim($postcodeInput) === '') {
            return view('epc.search_scotland');
        }

        // Validate: require a plausible full UK postcode (same rule as E&W)
        $request->validate([
            'postcode' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z]{1,2}\\d[A-Za-z\\d]?\\s*\\d[A-Za-z]{2}$/'],
        ], [
            'postcode.regex' => 'Please enter a full UK postcode (e.g. G12 8QQ).',
        ]);

        // Normalise to canonical form: uppercase and single space before last 3 chars
        $postcode = $this->normalisePostcode($postcodeInput);

        // Sorting (whitelist fields)
        $allowedSorts = [
            'lodgement_date' => $scotLodgementColumn,
            'address' => 'address',
            'current_energy_rating' => $scotCurrentRatingColumn,
            'potential_energy_rating' => $scotPotentialRatingColumn,
            'property_type' => $scotPropertyTypeColumn,
            'total_floor_area' => $scotFloorAreaColumn,
        ];
        $sort = $request->query('sort', 'lodgement_date');
        $dir = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortCol = $allowedSorts[$sort] ?? $scotLodgementColumn;

        // Build address expression that works whether Scotland data has a single `address` column
        $addressExpr = DB::raw(sprintf(
            "NULLIF(TRIM(CONCAT_WS(', ',
                NULLIF(%s, ''),
                NULLIF(%s, ''),
                NULLIF(%s, '')
            )), '') as address",
            $this->wrapColumn($scotAddress1Column),
            $this->wrapColumn($scotAddress2Column),
            $this->wrapColumn($scotAddress3Column)
        ));

        $resultsQuery = DB::table($scotTable)
            ->select([
                DB::raw($this->wrapColumn($scotRrnColumn).' as report_reference_number'),
                DB::raw($this->wrapColumn($scotBuildingColumn).' as building_reference_number'),
                DB::raw($this->wrapColumn($scotPostcodeColumn).' as postcode'),
                DB::raw($this->wrapColumn($scotLodgementColumn).' as lodgement_date'),
                DB::raw($this->wrapColumn($scotCurrentRatingColumn).' as current_energy_rating'),
                DB::raw($this->wrapColumn($scotPotentialRatingColumn).' as potential_energy_rating'),
                DB::raw($this->wrapColumn($scotPropertyTypeColumn).' as property_type'),
                DB::raw($this->wrapColumn($scotFloorAreaColumn).' as total_floor_area'),
                DB::raw($this->wrapColumn($scotLocalAuthorityColumn).' as local_authority_label'),
                $addressExpr,
            ])
            ->where($scotPostcodeColumn, $postcode)
            ->orderBy($sortCol, $dir);

        if ($sortCol !== $scotLodgementColumn) {
            $resultsQuery->orderBy($scotLodgementColumn, 'desc');
        }

        $results = $resultsQuery->paginate(50)->withQueryString();

        return view('epc.search_scotland', compact('results'));
    }

    /**
     * Show a single EPC report by LMK/Building Reference.
     *
     * Scotland uses BUILDING_REFERENCE_NUMBER, while England & Wales use lmk_key.
     * We attempt Scotland first, then fall back to E&W. We pass the full row
     * (all columns) through to the view so we can decide later what to surface.
     */
    public function show(Request $request, string $lmk)
    {
        $encoded = $request->query('r');
        $incomingReturn = $request->query('return');

        $decoded = null;
        if ($encoded) {
            $decoded = base64_decode($encoded, true) ?: null;
        }

        // Prefer decoded `r`, then plain `return` param
        $backUrlParam = $decoded ?: $incomingReturn;
        $fallbackScot = route('epc.search_scotland');
        $fallbackEW = route('epc.search');

        // --- Try Scotland first
        $scotTable = 'epc_certificates_scotland';
        $scotBuildingColumn = $this->resolveColumn($scotTable, ['BUILDING_REFERENCE_NUMBER', 'building_reference_number']);
        $scot = DB::table($scotTable)
            ->where($scotBuildingColumn, $lmk)
            ->first();

        if ($scot) {
            // Build a readable address similar to searchScotland()
            $address = trim(implode(', ', array_filter([
                $scot->ADDRESS1 ?? null,
                $scot->ADDRESS2 ?? null,
                $scot->ADDRESS3 ?? null,
            ])));

            $record = (array) $scot;
            $record['address_display'] = $address;
            $record['nation'] = 'scotland';

            return view('epc.show', [
                'nation' => 'scotland',
                'lmk' => $lmk,
                'record' => $record,   // full row as associative array
                'columns' => array_keys($record),
                'backUrl' => $backUrlParam ?: $fallbackScot,
            ]);
        }

        // --- Fall back to England & Wales
        $ewTable = 'epc_certificates';
        $ewLmkColumn = $this->resolveColumn($ewTable, ['LMK_KEY', 'lmk_key']);
        $ewBuildingColumn = $this->resolveColumn($ewTable, ['BUILDING_REFERENCE_NUMBER', 'building_reference_number']);
        $ew = DB::table($ewTable)
            ->where($ewLmkColumn, $lmk)
            ->first();

        if (! $ew && $ewBuildingColumn !== null) {
            $ew = DB::table($ewTable)
                ->where($ewBuildingColumn, $lmk)
                ->first();
        }

        if ($ew) {
            $record = (array) $ew;
            // Keep a consistent extra field for display if needed
            if (! array_key_exists('address_display', $record)) {
                $record['address_display'] = $record['address'] ?? null;
            }
            $record['nation'] = 'ew';

            return view('epc.show', [
                'nation' => 'ew',
                'lmk' => $lmk,
                'record' => $record,   // full row as associative array
                'columns' => array_keys($record),
                'backUrl' => $backUrlParam ?: $fallbackEW,
            ]);
        }

        // Not found in either dataset
        abort(404);
    }

    /**
     * Normalise a UK postcode to uppercase with a single space before the final 3 characters.
     */
    protected function normalisePostcode(string $pc): string
    {
        $pc = strtoupper(preg_replace('/\s+/', '', $pc));
        if (strlen($pc) >= 5) {
            return substr($pc, 0, -3).' '.substr($pc, -3);
        }

        return $pc;
    }

    /**
     * Show a single Scotland EPC report by REPORT_REFERENCE_NUMBER.
     */
    public function showScotland(Request $request, string $rrn)
    {
        $encoded = $request->query('r');
        $decoded = $encoded ? base64_decode($encoded, true) : null;

        $backUrl = $decoded ?: route('epc.search_scotland');

        $scotTable = 'epc_certificates_scotland';
        $scotRrnColumn = $this->resolveColumn($scotTable, ['REPORT_REFERENCE_NUMBER', 'report_reference_number']);
        $scot = DB::table($scotTable)
            ->where($scotRrnColumn, $rrn)
            ->first();

        abort_if(! $scot, 404);

        // Build a readable address
        $address = trim(implode(', ', array_filter([
            $scot->ADDRESS1 ?? null,
            $scot->ADDRESS2 ?? null,
            $scot->ADDRESS3 ?? null,
        ])));

        $record = (array) $scot;
        $record['address_display'] = $address;
        $record['nation'] = 'scotland';

        return view('epc.show', [
            'nation' => 'scotland',
            'lmk' => $rrn, // reuse slot; Scotland uses RRN
            'record' => $record,
            'columns' => array_keys($record),
            'backUrl' => $backUrl,
        ]);
    }
}
