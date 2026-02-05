<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NewOldController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $yearExpr = $this->yearExpression();
        $areaCodeColumn = $this->wrapColumn('AreaCode');
        $regionNameColumn = $this->wrapColumn('RegionName');
        $newSalesColumn = $this->wrapColumn('NewSalesVolume');
        $oldSalesColumn = $this->wrapColumn('OldSalesVolume');
        $countryExpression = $this->countryExpression($areaCodeColumn);
        $includeAggregates = (bool) $request->boolean('include_aggregates', false);
        $yearParam = $request->input('year');

        $availableYears = DB::table('hpi_monthly')
            ->selectRaw("DISTINCT {$yearExpr} as year")
            ->orderBy('year', 'desc')
            ->limit(20)
            ->pluck('year')
            ->map(fn ($year) => (string) $year)
            ->toArray();

        $latestYear = $availableYears[0] ?? null;
        $snapshotYear = $yearParam ?: $latestYear;

        $base = DB::table('hpi_monthly')
            ->whereRaw("{$yearExpr} = ?", [$snapshotYear]);

        if (! $includeAggregates) {
            $base->whereRaw("SUBSTR({$areaCodeColumn}, 1, 1) <> 'K'");
        }

        $countryRows = (clone $base)
            ->selectRaw("{$countryExpression} as country_name")
            ->selectRaw("COALESCE(SUM({$newSalesColumn}), 0) as new_vol")
            ->selectRaw("COALESCE(SUM({$oldSalesColumn}), 0) as old_vol")
            ->groupBy(DB::raw($countryExpression))
            ->orderBy('country_name')
            ->get();

        $countries = $countryRows->map(function ($row) {
            $newVolume = (int) $row->new_vol;
            $oldVolume = (int) $row->old_vol;
            $totalVolume = $newVolume + $oldVolume;

            return [
                'country' => $row->country_name,
                'new_vol' => $newVolume,
                'old_vol' => $oldVolume,
                'new_share_pct' => $totalVolume > 0 ? round(100 * $newVolume / $totalVolume, 2) : 0,
                'old_share_pct' => $totalVolume > 0 ? round(100 * $oldVolume / $totalVolume, 2) : 0,
            ];
        })->values()->all();

        $sort = $request->input('sort', 'new_share_pct');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['region_name', 'new_vol', 'old_vol', 'total_vol', 'new_share_pct'], true)) {
            $sort = 'new_share_pct';
        }

        $regions = (clone $base)
            ->whereNotIn('AreaCode', ['E92000001', 'S92000003', 'W92000004', 'N92000002'])
            ->select('AreaCode as area_code', 'RegionName as region_name')
            ->selectRaw("COALESCE(SUM({$newSalesColumn}), 0) as new_vol")
            ->selectRaw("COALESCE(SUM({$oldSalesColumn}), 0) as old_vol")
            ->selectRaw("(COALESCE(SUM({$newSalesColumn}), 0) + COALESCE(SUM({$oldSalesColumn}), 0)) as total_vol")
            ->selectRaw("CASE WHEN (COALESCE(SUM({$newSalesColumn}), 0) + COALESCE(SUM({$oldSalesColumn}), 0)) = 0 THEN 0 ELSE ROUND(100 * COALESCE(SUM({$newSalesColumn}), 0) / (COALESCE(SUM({$newSalesColumn}), 0) + COALESCE(SUM({$oldSalesColumn}), 0)), 1) END as new_share_pct")
            ->groupBy('AreaCode', 'RegionName')
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        $trendRows = DB::table('hpi_monthly')
            ->when(! $includeAggregates, fn ($query) => $query->whereRaw("SUBSTR({$areaCodeColumn}, 1, 1) <> 'K'"))
            ->selectRaw("{$yearExpr} as year")
            ->selectRaw("SUM({$newSalesColumn}) as new_vol")
            ->selectRaw("SUM({$oldSalesColumn}) as old_vol")
            ->groupBy(DB::raw($yearExpr))
            ->orderBy(DB::raw($yearExpr), 'desc')
            ->limit(15)
            ->get()
            ->reverse()
            ->values();

        $trend = $trendRows->map(fn ($row) => [
            'date' => (string) $row->year,
            'new_vol' => (int) $row->new_vol,
            'old_vol' => (int) $row->old_vol,
        ])->all();

        $nationTrends = $this->buildNationTrends($includeAggregates, $yearExpr, $areaCodeColumn, $newSalesColumn, $oldSalesColumn);

        return view('new_old.index', [
            'snapshot_year' => $snapshotYear,
            'available_years' => $availableYears,
            'include_aggregates' => $includeAggregates,
            'countries' => $countries,
            'regions' => $regions,
            'trend' => $trend,
            'nation_trends' => $nationTrends,
        ]);
    }

    private function yearExpression(): string
    {
        $driver = DB::connection()->getDriverName();
        $dateColumn = $this->wrapColumn('Date');

        return match ($driver) {
            'sqlite' => "CAST(strftime('%Y', {$dateColumn}) AS INTEGER)",
            'pgsql' => "EXTRACT(YEAR FROM {$dateColumn})::int",
            default => "YEAR({$dateColumn})",
        };
    }

    private function wrapColumn(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }

    private function countryExpression(string $areaCodeColumn): string
    {
        return "CASE SUBSTR({$areaCodeColumn}, 1, 1)
            WHEN 'E' THEN 'England'
            WHEN 'W' THEN 'Wales'
            WHEN 'S' THEN 'Scotland'
            WHEN 'N' THEN 'Northern Ireland'
            WHEN 'K' THEN 'Aggregate'
            ELSE 'Other'
        END";
    }

    private function nationExpression(string $areaCodeColumn): string
    {
        return "CASE SUBSTR({$areaCodeColumn}, 1, 1)
            WHEN 'E' THEN 'England'
            WHEN 'S' THEN 'Scotland'
            WHEN 'W' THEN 'Wales'
            WHEN 'N' THEN 'Northern Ireland'
            ELSE NULL
        END";
    }

    private function buildNationTrends(
        bool $includeAggregates,
        string $yearExpression,
        string $areaCodeColumn,
        string $newSalesColumn,
        string $oldSalesColumn
    ): array {
        $nationExpression = $this->nationExpression($areaCodeColumn);

        $trendBase = DB::table('hpi_monthly')
            ->when(! $includeAggregates, fn ($query) => $query->whereRaw("SUBSTR({$areaCodeColumn}, 1, 1) <> 'K'"));

        $nationTrendRows = (clone $trendBase)
            ->selectRaw("{$nationExpression} as nation")
            ->selectRaw("{$yearExpression} as year")
            ->selectRaw("SUM({$newSalesColumn}) as new_vol")
            ->selectRaw("SUM({$oldSalesColumn}) as old_vol")
            ->whereRaw("SUBSTR({$areaCodeColumn}, 1, 1) IN ('E','S','W','N')")
            ->groupBy(DB::raw($nationExpression), DB::raw($yearExpression))
            ->orderBy(DB::raw($yearExpression), 'desc')
            ->limit(60)
            ->get();

        return collect(['England', 'Scotland', 'Wales', 'Northern Ireland'])->mapWithKeys(function ($nation) use ($nationTrendRows) {
            $rows = $nationTrendRows
                ->where('nation', $nation)
                ->sortBy('year')
                ->values()
                ->take(-15)
                ->values();

            return [
                $nation => $rows->map(fn ($row) => [
                    'date' => (string) $row->year,
                    'new_vol' => (int) $row->new_vol,
                    'old_vol' => (int) $row->old_vol,
                ])->all(),
            ];
        })->all();
    }
}
