<?php

namespace App\Http\Controllers;

use App\Models\Crime;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CrimeController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24 * 45;

    public function index(Request $request): View
    {
        $payload = $this->nationalPayload();
        $sort = $this->areaSortOption($request);
        $crimeTypeSort = $this->crimeTypeSortOption($request);

        return view('insights.crime.index', [
            ...$payload,
            'sort' => $sort,
            'crime_type_sort' => $crimeTypeSort,
            'areas' => $this->sortAreaSummaries($payload['areas'], $sort),
            'crime_types' => $this->sortCrimeTypes($payload['crime_types'], $crimeTypeSort),
        ]);
    }

    public function show(Request $request, string $area): View
    {
        $payload = $this->areaPayload($area);
        $breakdownSort = $this->areaBreakdownSortOption($request);

        abort_if($payload === null, 404);

        return view('insights.crime.show', [
            ...$payload,
            'breakdown_sort' => $breakdownSort,
            'crime_breakdown' => $this->sortAreaCrimeBreakdown($payload['crime_breakdown'], $breakdownSort),
        ]);
    }

    /**
     * @return array{
     *     latest_month:?string,
     *     latest_month_label:?string,
     *     summary:array{
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float,
     *         last_3m_total:int,
     *         prev_3m_total:int,
     *         last_3m_change:float
     *     },
     *     chart:array{
     *         labels:array<int,string>,
     *         current_year:array<int,int>,
     *         previous_year:array<int,int>
     *     },
     *     areas:array<int,array{
     *         area:string,
     *         slug:string,
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float,
     *         trend:string
     *     }>,
     *     type_breakdown:array<int,array{
     *         crime_type:string,
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float
     *     }>,
     *     crime_types:array<int,array{
     *         type:string,
     *         total_12m:int,
     *         total_prev_12m:int,
     *         yoy_change:float,
     *         share_pct:float,
     *         trend:string
     *     }>,
     *     headlines:array<int,string>
     * }
     */
    public function warmNationalCache(): array
    {
        return $this->nationalPayload(true);
    }

    /**
     * @return array{
     *     area:string,
     *     area_slug:string,
     *     latest_month:?string,
     *     latest_month_label:?string,
     *     summary:array{
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float,
     *         last_3m_total:int,
     *         prev_3m_total:int,
     *         last_3m_change:float
     *     },
     *     chart:array{
     *         labels:array<int,string>,
     *         current_year:array<int,int>,
     *         previous_year:array<int,int>
     *     },
     *     crime_breakdown:array<int,array{
     *         type:string,
     *         total_12m:int,
     *         total_prev_12m:int,
     *         yoy_change:float,
     *         share_pct:float,
     *         impact:int,
     *         trend:string,
     *         national_yoy:?float,
     *         is_largest:bool
     *     }>
     *     ,drivers:array{
     *         overall_yoy:float,
     *         increases:array<int,array{
     *             type:string,
     *             impact:int,
     *             yoy_change:float
     *         }>,
     *         decreases:array<int,array{
     *             type:string,
     *             impact:int,
     *             yoy_change:float
     *         }>
     *     }
     * }|null
     */
    public function warmAreaCache(string $area): ?array
    {
        return $this->areaPayload($area, true);
    }

    /**
     * @return array<int,array{area:string,slug:string,total_12m:int,prev_12m:int,pct_change:float,trend:string}>
     */
    public function warmAreaSummaries(): array
    {
        return $this->nationalPayload()['areas'];
    }

    /**
     * @return array{
     *     latest_month:?string,
     *     latest_month_label:?string,
     *     summary:array{
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float,
     *         last_3m_total:int,
     *         prev_3m_total:int,
     *         last_3m_change:float
     *     },
     *     chart:array{
     *         labels:array<int,string>,
     *         current_year:array<int,int>,
     *         previous_year:array<int,int>
     *     },
     *     areas:array<int,array{
     *         area:string,
     *         slug:string,
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float,
     *         trend:string
     *     }>,
     *     type_breakdown:array<int,array{
     *         crime_type:string,
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float
     *     }>,
     *     crime_types:array<int,array{
     *         type:string,
     *         total_12m:int,
     *         total_prev_12m:int,
     *         yoy_change:float,
     *         share_pct:float,
     *         trend:string
     *     }>,
     *     headlines:array<int,string>
     * }
     */
    private function nationalPayload(bool $forceRefresh = false): array
    {
        $cacheKey = 'insights:crime:national:v3';

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function (): array {
            $latestMonth = $this->latestCrimeMonth();

            if ($latestMonth === null) {
                return $this->emptyNationalPayload();
            }

            [$currentStart, $currentEnd, $previousStart, $last24Start] = $this->dateWindows($latestMonth);
            $summary = $this->periodSummary(null, $currentStart, $currentEnd, $previousStart);
            $chart = $this->monthlyTrend(null, $last24Start, $currentEnd);
            $areas = $this->regionalSummaries($currentStart, $currentEnd, $previousStart);
            $typeBreakdown = $this->crimeTypeBreakdown(null, $currentStart, $currentEnd, $previousStart);
            $crimeTypes = $this->nationalCrimeTypes($typeBreakdown, $summary['total_12m']);

            return [
                'latest_month' => $latestMonth->toDateString(),
                'latest_month_label' => $latestMonth->format('F Y'),
                'summary' => $summary,
                'chart' => $chart,
                'areas' => $areas,
                'type_breakdown' => $typeBreakdown,
                'crime_types' => $crimeTypes,
                'headlines' => $this->headlines($summary, $areas, $typeBreakdown),
            ];
        });
    }

    /**
     * @return array{
     *     area:string,
     *     area_slug:string,
     *     latest_month:?string,
     *     latest_month_label:?string,
     *     summary:array{
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float,
     *         last_3m_total:int,
     *         prev_3m_total:int,
     *         last_3m_change:float
     *     },
     *     chart:array{
     *         labels:array<int,string>,
     *         current_year:array<int,int>,
     *         previous_year:array<int,int>
     *     },
     *     type_breakdown:array<int,array{
     *         crime_type:string,
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float
     *     }>
     * }|null
     */
    private function areaPayload(string $area, bool $forceRefresh = false): ?array
    {
        $slug = Str::slug(urldecode($area));
        $cacheKey = 'insights:crime:area:v3:'.$slug;

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($slug): ?array {
            $latestMonth = $this->latestCrimeMonth();

            if ($latestMonth === null) {
                return null;
            }

            [$currentStart, $currentEnd, $previousStart, $last24Start] = $this->dateWindows($latestMonth);
            $areas = collect($this->regionalSummaries($currentStart, $currentEnd, $previousStart));
            $areaSummary = $areas->firstWhere('slug', $slug);

            if ($areaSummary === null) {
                return null;
            }

            $resolvedArea = (string) $areaSummary['area'];
            $summary = $this->periodSummary($resolvedArea, $currentStart, $currentEnd, $previousStart);
            $areaBreakdownBase = $this->crimeTypeBreakdown($resolvedArea, $currentStart, $currentEnd, $previousStart);
            $nationalByType = collect($this->nationalPayload()['crime_types'])->keyBy('type');
            $crimeBreakdown = $this->areaCrimeBreakdown($areaBreakdownBase, $summary['total_12m'], $nationalByType);

            return [
                'area' => $resolvedArea,
                'area_slug' => $slug,
                'latest_month' => $latestMonth->toDateString(),
                'latest_month_label' => $latestMonth->format('F Y'),
                'summary' => $summary,
                'chart' => $this->monthlyTrend($resolvedArea, $last24Start, $currentEnd),
                'crime_breakdown' => $crimeBreakdown,
                'drivers' => $this->driversPanel($summary['pct_change'], $crimeBreakdown),
            ];
        });
    }

    /**
     * @return array{
     *     total_12m:int,
     *     prev_12m:int,
     *     pct_change:float,
     *     last_3m_total:int,
     *     prev_3m_total:int,
     *     last_3m_change:float
     * }
     */
    private function periodSummary(?string $area, Carbon $currentStart, Carbon $currentEnd, Carbon $previousStart): array
    {
        $recentThreeMonthStart = $currentEnd->copy()->subMonths(2)->startOfMonth();
        $previousThreeMonthStart = $recentThreeMonthStart->copy()->subMonths(3);
        $previousThreeMonthEnd = $recentThreeMonthStart->copy()->subMonth()->startOfMonth();

        $row = $this->crimeQueryForArea($area)
            ->selectRaw(
                'SUM(CASE WHEN month >= ? AND month <= ? THEN 1 ELSE 0 END) as current_total',
                [$currentStart->toDateString(), $currentEnd->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN month >= ? AND month < ? THEN 1 ELSE 0 END) as previous_total',
                [$previousStart->toDateString(), $currentStart->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN month >= ? AND month <= ? THEN 1 ELSE 0 END) as recent_three_total',
                [$recentThreeMonthStart->toDateString(), $currentEnd->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN month >= ? AND month <= ? THEN 1 ELSE 0 END) as previous_three_total',
                [$previousThreeMonthStart->toDateString(), $previousThreeMonthEnd->toDateString()]
            )
            ->first();

        $currentTotal = (int) ($row->current_total ?? 0);
        $previousTotal = (int) ($row->previous_total ?? 0);
        $recentThreeTotal = (int) ($row->recent_three_total ?? 0);
        $previousThreeTotal = (int) ($row->previous_three_total ?? 0);

        return [
            'total_12m' => $currentTotal,
            'prev_12m' => $previousTotal,
            'pct_change' => $this->percentageChange($previousTotal, $currentTotal),
            'last_3m_total' => $recentThreeTotal,
            'prev_3m_total' => $previousThreeTotal,
            'last_3m_change' => $this->percentageChange($previousThreeTotal, $recentThreeTotal),
        ];
    }

    /**
     * @return array{
     *     labels:array<int,string>,
     *     monthly_totals:array<int,int>,
     *     rolling_12m:array<int,int|null>
     * }
     */
    private function monthlyTrend(?string $area, Carbon $start, Carbon $end): array
    {
        $rows = $this->crimeQueryForArea($area)
            ->whereDate('month', '>=', $start->toDateString())
            ->whereDate('month', '<=', $end->toDateString())
            ->selectRaw($this->monthExpression().' as month_start')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('month_start')
            ->orderBy('month_start')
            ->pluck('total', 'month_start');

        $labels = [];
        $currentYear = [];
        $previousYear = [];
        $currentPeriodStart = $end->copy()->subMonths(11)->startOfMonth();
        $cursor = $currentPeriodStart->copy();

        while ($cursor->lte($end)) {
            $monthKey = $cursor->toDateString();
            $previousMonthKey = $cursor->copy()->subYear()->toDateString();

            $labels[] = $cursor->format('M');
            $currentYear[] = (int) ($rows[$monthKey] ?? 0);
            $previousYear[] = (int) ($rows[$previousMonthKey] ?? 0);
            $cursor->addMonth();
        }

        return [
            'labels' => $labels,
            'current_year' => $currentYear,
            'previous_year' => $previousYear,
        ];
    }

    /**
     * @return array<int,array{area:string,slug:string,total_12m:int,prev_12m:int,pct_change:float,trend:string}>
     */
    private function regionalSummaries(Carbon $currentStart, Carbon $currentEnd, Carbon $previousStart): array
    {
        $rows = Crime::query()
            ->whereNotNull('month')
            ->whereDate('month', '>=', $previousStart->toDateString())
            ->whereDate('month', '<=', $currentEnd->toDateString())
            ->whereRaw($this->areaExpression().' IS NOT NULL')
            ->selectRaw($this->areaExpression().' as area_name')
            ->selectRaw(
                'SUM(CASE WHEN month >= ? AND month <= ? THEN 1 ELSE 0 END) as current_total',
                [$currentStart->toDateString(), $currentEnd->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN month >= ? AND month < ? THEN 1 ELSE 0 END) as previous_total',
                [$previousStart->toDateString(), $currentStart->toDateString()]
            )
            ->groupBy('area_name')
            ->get();

        return $rows
            ->map(function (object $row): array {
                $area = trim((string) $row->area_name);
                $currentTotal = (int) $row->current_total;
                $previousTotal = (int) $row->previous_total;
                $pctChange = $this->percentageChange($previousTotal, $currentTotal);

                return [
                    'area' => $area,
                    'slug' => Str::slug($area),
                    'total_12m' => $currentTotal,
                    'prev_12m' => $previousTotal,
                    'pct_change' => $pctChange,
                    'trend' => $this->trendDirection($pctChange),
                ];
            })
            ->filter(fn (array $row): bool => $row['total_12m'] > 0 || $row['prev_12m'] > 0)
            ->sortByDesc('total_12m')
            ->values()
            ->all();
    }

    /**
     * @return array<int,array{crime_type:string,total_12m:int,prev_12m:int,pct_change:float}>
     */
    private function crimeTypeBreakdown(?string $area, Carbon $currentStart, Carbon $currentEnd, Carbon $previousStart): array
    {
        return $this->crimeQueryForArea($area)
            ->whereDate('month', '>=', $previousStart->toDateString())
            ->whereDate('month', '<=', $currentEnd->toDateString())
            ->whereNotNull('crime_type')
            ->where('crime_type', '!=', '')
            ->select('crime_type')
            ->selectRaw(
                'SUM(CASE WHEN month >= ? AND month <= ? THEN 1 ELSE 0 END) as current_total',
                [$currentStart->toDateString(), $currentEnd->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN month >= ? AND month < ? THEN 1 ELSE 0 END) as previous_total',
                [$previousStart->toDateString(), $currentStart->toDateString()]
            )
            ->groupBy('crime_type')
            ->get()
            ->map(function (Crime $crime): array {
                $currentTotal = (int) $crime->current_total;
                $previousTotal = (int) $crime->previous_total;

                return [
                    'crime_type' => (string) $crime->crime_type,
                    'total_12m' => $currentTotal,
                    'prev_12m' => $previousTotal,
                    'pct_change' => $this->percentageChange($previousTotal, $currentTotal),
                ];
            })
            ->filter(fn (array $row): bool => $row['total_12m'] > 0 || $row['prev_12m'] > 0)
            ->sortBy([
                ['pct_change', 'desc'],
                ['total_12m', 'desc'],
                ['crime_type', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{area:string,slug:string,total_12m:int,prev_12m:int,pct_change:float,trend:string}>  $areas
     * @return array<int,array{area:string,slug:string,total_12m:int,prev_12m:int,pct_change:float,trend:string}>
     */
    private function sortAreaSummaries(array $areas, string $sort): array
    {
        $collection = collect($areas);

        return match ($sort) {
            'total_asc' => $collection->sortBy([['total_12m', 'asc'], ['area', 'asc']])->values()->all(),
            'change_asc' => $collection->sortBy([['pct_change', 'asc'], ['area', 'asc']])->values()->all(),
            'change_desc' => $collection->sortBy([['pct_change', 'desc'], ['area', 'asc']])->values()->all(),
            'area_asc' => $collection->sortBy([['area', 'asc']])->values()->all(),
            default => $collection->sortBy([['total_12m', 'desc'], ['area', 'asc']])->values()->all(),
        };
    }

    private function areaSortOption(Request $request): string
    {
        $sort = $request->string('sort')->value();

        return in_array($sort, ['total_desc', 'total_asc', 'change_desc', 'change_asc', 'area_asc'], true)
            ? $sort
            : 'total_desc';
    }

    private function crimeTypeSortOption(Request $request): string
    {
        $sort = $request->string('crime_type_sort')->value();

        return in_array($sort, ['total_desc', 'change_desc', 'share_desc'], true)
            ? $sort
            : 'total_desc';
    }

    private function areaBreakdownSortOption(Request $request): string
    {
        $sort = $request->string('breakdown_sort')->value();

        return in_array($sort, ['impact_desc', 'total_desc', 'yoy_desc', 'share_desc'], true)
            ? $sort
            : 'impact_desc';
    }

    /**
     * @param  array{total_12m:int,prev_12m:int,pct_change:float,last_3m_total:int,prev_3m_total:int,last_3m_change:float}  $summary
     * @param  array<int,array{area:string,slug:string,total_12m:int,prev_12m:int,pct_change:float,trend:string}>  $areas
     * @param  array<int,array{crime_type:string,total_12m:int,prev_12m:int,pct_change:float}>  $typeBreakdown
     * @return array<int,string>
     */
    private function headlines(array $summary, array $areas, array $typeBreakdown): array
    {
        $headlines = [];
        $direction = $summary['pct_change'] >= 0 ? 'up' : 'down';

        $headlines[] = 'Crime '.$direction.' '.number_format(abs($summary['pct_change']), 1).'% nationally over the latest 12 months.';

        if ($typeBreakdown !== []) {
            $fastestType = $typeBreakdown[0];
            $typeDirection = $fastestType['pct_change'] >= 0 ? 'increasing' : 'falling';
            $headlines[] = $fastestType['crime_type'].' is '.$typeDirection.' fastest at '.number_format(abs($fastestType['pct_change']), 1).'%.';
        }

        if ($areas !== []) {
            $largestArea = collect($areas)->sortByDesc('total_12m')->first();

            if ($largestArea !== null) {
                $headlines[] = $largestArea['area'].' recorded the highest 12-month total with '.number_format($largestArea['total_12m']).' crimes.';
            }
        }

        return $headlines;
    }

    /**
     * @param  array<int,array{crime_type:string,total_12m:int,prev_12m:int,pct_change:float}>  $typeBreakdown
     * @return array<int,array{type:string,total_12m:int,total_prev_12m:int,yoy_change:float,share_pct:float,trend:string}>
     */
    private function nationalCrimeTypes(array $typeBreakdown, int $totalCrime): array
    {
        return collect($typeBreakdown)
            ->map(function (array $crimeType) use ($totalCrime): array {
                $yoyChange = (float) $crimeType['pct_change'];

                return [
                    'type' => $crimeType['crime_type'],
                    'total_12m' => (int) $crimeType['total_12m'],
                    'total_prev_12m' => (int) $crimeType['prev_12m'],
                    'yoy_change' => $yoyChange,
                    'share_pct' => $totalCrime > 0 ? round(($crimeType['total_12m'] * 100) / $totalCrime, 1) : 0.0,
                    'trend' => $yoyChange > 1 ? 'Up' : ($yoyChange < -1 ? 'Down' : 'Flat'),
                ];
            })
            ->sortByDesc('total_12m')
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{type:string,total_12m:int,total_prev_12m:int,yoy_change:float,share_pct:float,trend:string}>  $crimeTypes
     * @return array<int,array{type:string,total_12m:int,total_prev_12m:int,yoy_change:float,share_pct:float,trend:string}>
     */
    private function sortCrimeTypes(array $crimeTypes, string $sort): array
    {
        $collection = collect($crimeTypes);

        return match ($sort) {
            'change_desc' => $collection->sortBy([['yoy_change', 'desc'], ['total_12m', 'desc'], ['type', 'asc']])->values()->all(),
            'share_desc' => $collection->sortBy([['share_pct', 'desc'], ['total_12m', 'desc'], ['type', 'asc']])->values()->all(),
            default => $collection->sortBy([['total_12m', 'desc'], ['type', 'asc']])->values()->all(),
        };
    }

    /**
     * @param  array<int,array{crime_type:string,total_12m:int,prev_12m:int,pct_change:float}>  $areaBreakdown
     * @param  \Illuminate\Support\Collection<string,array{type:string,total_12m:int,total_prev_12m:int,yoy_change:float,share_pct:float,trend:string}>  $nationalByType
     * @return array<int,array{type:string,total_12m:int,total_prev_12m:int,yoy_change:float,share_pct:float,impact:int,trend:string,national_yoy:?float,is_largest:bool}>
     */
    private function areaCrimeBreakdown(array $areaBreakdown, int $totalCrime, $nationalByType): array
    {
        $largestType = collect($areaBreakdown)->sortByDesc('total_12m')->first();
        $largestTypeName = $largestType['crime_type'] ?? null;

        return collect($areaBreakdown)
            ->map(function (array $crimeType) use ($totalCrime, $nationalByType, $largestTypeName): array {
                $yoyChange = (float) $crimeType['pct_change'];
                $impact = (int) $crimeType['total_12m'] - (int) $crimeType['prev_12m'];
                $type = (string) $crimeType['crime_type'];
                $nationalType = $nationalByType->get($type);

                return [
                    'type' => $type,
                    'total_12m' => (int) $crimeType['total_12m'],
                    'total_prev_12m' => (int) $crimeType['prev_12m'],
                    'yoy_change' => $yoyChange,
                    'share_pct' => $totalCrime > 0 ? round((((int) $crimeType['total_12m']) * 100) / $totalCrime, 1) : 0.0,
                    'impact' => $impact,
                    'trend' => $yoyChange > 1 ? 'Up' : ($yoyChange < -1 ? 'Down' : 'Flat'),
                    'national_yoy' => $nationalType['yoy_change'] ?? null,
                    'is_largest' => $type === $largestTypeName,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{type:string,total_12m:int,total_prev_12m:int,yoy_change:float,share_pct:float,impact:int,trend:string,national_yoy:?float,is_largest:bool}>  $crimeBreakdown
     * @return array{
     *     overall_yoy:float,
     *     increases:array<int,array{type:string,impact:int,yoy_change:float}>,
     *     decreases:array<int,array{type:string,impact:int,yoy_change:float}>
     * }
     */
    private function driversPanel(float $overallYoy, array $crimeBreakdown): array
    {
        $collection = collect($crimeBreakdown);

        return [
            'overall_yoy' => $overallYoy,
            'increases' => $collection
                ->filter(fn (array $row): bool => $row['impact'] > 0)
                ->sortByDesc('impact')
                ->take(3)
                ->map(fn (array $row): array => [
                    'type' => $row['type'],
                    'impact' => $row['impact'],
                    'yoy_change' => $row['yoy_change'],
                ])
                ->values()
                ->all(),
            'decreases' => $collection
                ->filter(fn (array $row): bool => $row['impact'] < 0)
                ->sortBy('impact')
                ->take(3)
                ->map(fn (array $row): array => [
                    'type' => $row['type'],
                    'impact' => $row['impact'],
                    'yoy_change' => $row['yoy_change'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int,array{type:string,total_12m:int,total_prev_12m:int,yoy_change:float,share_pct:float,impact:int,trend:string,national_yoy:?float,is_largest:bool}>  $crimeBreakdown
     * @return array<int,array{type:string,total_12m:int,total_prev_12m:int,yoy_change:float,share_pct:float,impact:int,trend:string,national_yoy:?float,is_largest:bool}>
     */
    private function sortAreaCrimeBreakdown(array $crimeBreakdown, string $sort): array
    {
        $collection = collect($crimeBreakdown);

        return match ($sort) {
            'total_desc' => $collection->sortBy([['total_12m', 'desc'], ['type', 'asc']])->values()->all(),
            'yoy_desc' => $collection->sortBy([['yoy_change', 'desc'], ['impact', 'desc'], ['type', 'asc']])->values()->all(),
            'share_desc' => $collection->sortBy([['share_pct', 'desc'], ['type', 'asc']])->values()->all(),
            default => $collection->sortBy([['impact', 'desc'], ['type', 'asc']])->values()->all(),
        };
    }

    private function latestCrimeMonth(): ?Carbon
    {
        $latestMonth = Crime::query()->max('month');

        return $latestMonth === null ? null : Carbon::parse((string) $latestMonth)->startOfMonth();
    }

    /**
     * @return array{0:Carbon,1:Carbon,2:Carbon,3:Carbon}
     */
    private function dateWindows(Carbon $latestMonth): array
    {
        $currentEnd = $latestMonth->copy()->startOfMonth();
        $currentStart = $currentEnd->copy()->subMonths(11);
        $previousStart = $currentStart->copy()->subMonths(12);
        $last24Start = $currentEnd->copy()->subMonths(23);

        return [$currentStart, $currentEnd, $previousStart, $last24Start];
    }

    private function percentageChange(int $baseline, int $current): float
    {
        if ($baseline === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $baseline) * 100) / $baseline, 1);
    }

    private function trendDirection(float $pctChange): string
    {
        if ($pctChange > 0) {
            return 'up';
        }

        if ($pctChange < 0) {
            return 'down';
        }

        return 'flat';
    }

    private function crimeQueryForArea(?string $area): \Illuminate\Database\Eloquent\Builder
    {
        return Crime::query()
            ->whereNotNull('month')
            ->when($area !== null, function ($query) use ($area): void {
                $query->whereRaw($this->areaExpression().' = ?', [$area]);
            });
    }

    private function areaExpression(): string
    {
        return "COALESCE(NULLIF(TRIM(falls_within), ''), NULLIF(TRIM(reported_by), ''), NULLIF(TRIM(lsoa_name), ''))";
    }

    private function monthExpression(): string
    {
        return match (config('database.default')) {
            'pgsql' => "DATE_TRUNC('month', month)::date",
            'sqlite' => "date(strftime('%Y-%m-01', month))",
            default => "DATE_FORMAT(month, '%Y-%m-01')",
        };
    }

    /**
     * @return array{
     *     latest_month:?string,
     *     latest_month_label:?string,
     *     summary:array{
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float,
     *         last_3m_total:int,
     *         prev_3m_total:int,
     *         last_3m_change:float
     *     },
     *     chart:array{
     *         labels:array<int,string>,
     *         current_year:array<int,int>,
     *         previous_year:array<int,int>
     *     },
     *     areas:array<int,array{
     *         area:string,
     *         slug:string,
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float,
     *         trend:string
     *     }>,
     *     type_breakdown:array<int,array{
     *         crime_type:string,
     *         total_12m:int,
     *         prev_12m:int,
     *         pct_change:float
     *     }>,
     *     crime_types:array<int,array{
     *         type:string,
     *         total_12m:int,
     *         total_prev_12m:int,
     *         yoy_change:float,
     *         share_pct:float,
     *         trend:string
     *     }>,
     *     headlines:array<int,string>
     * }
     */
    private function emptyNationalPayload(): array
    {
        return [
            'latest_month' => null,
            'latest_month_label' => null,
            'summary' => [
                'total_12m' => 0,
                'prev_12m' => 0,
                'pct_change' => 0.0,
                'last_3m_total' => 0,
                'prev_3m_total' => 0,
                'last_3m_change' => 0.0,
            ],
            'chart' => [
                'labels' => [],
                'current_year' => [],
                'previous_year' => [],
            ],
            'areas' => [],
            'type_breakdown' => [],
            'crime_types' => [],
            'headlines' => [],
        ];
    }
}
