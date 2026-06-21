<?php

namespace App\Services;

use App\Models\Crime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CrimeSummaryService
{
    private const CACHE_TTL = 60 * 60 * 24 * 45;

    private const CACHE_VERSION = 'v1';

    private const RADIUS_DELTA = 0.005;

    /**
     * High-traffic public pages must read this cached payload instead of
     * calculating crime summaries live from the raw crime table on every hit.
     *
     * @return array{
     *     crime_data:array<int, array<string, mixed>>,
     *     crime_trend:array<int, array<string, mixed>>,
     *     crime_summary:?string,
     *     crime_direction:string,
     *     crime_trend_labels:array<int, string>,
     *     crime_trend_values:array<int, int>,
     *     crime_total_change:float,
     *     crime_top_increase:?array<string, mixed>,
     *     crime_top_decrease:?array<string, mixed>
     * }
     */
    public function pointSummary(float $latitude, float $longitude): array
    {
        $latestCrimeMonth = $this->latestCrimeMonth();

        if ($latestCrimeMonth === null) {
            return $this->emptyPointSummary();
        }

        [$currentWindowEnd, $crimeWindowStart, $previousWindowStart] = $this->pointWindows($latestCrimeMonth);
        $cacheKey = self::pointCacheKey(
            $latitude,
            $longitude,
            $previousWindowStart,
            $currentWindowEnd
        );

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $latitude,
            $longitude,
            $currentWindowEnd,
            $crimeWindowStart,
            $previousWindowStart
        ): array {
            $trendCounts = $this->pointTrendCounts(
                $latitude,
                $longitude,
                $currentWindowEnd,
                $crimeWindowStart,
                $previousWindowStart
            );

            if ($trendCounts->isEmpty()) {
                return $this->emptyPointSummary();
            }

            $crimeTrendSeriesRows = Crime::query()
                ->whereDate('month', '>=', $previousWindowStart->toDateString())
                ->whereDate('month', '<=', $currentWindowEnd->toDateString())
                ->whereBetween('latitude', [$latitude - self::RADIUS_DELTA, $latitude + self::RADIUS_DELTA])
                ->whereBetween('longitude', [$longitude - self::RADIUS_DELTA, $longitude + self::RADIUS_DELTA])
                ->selectRaw('month, COUNT(*) as total')
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month');

            $crimeTrendLabels = [];
            $crimeTrendValues = [];
            $trendCursor = $previousWindowStart->copy();

            while ($trendCursor->lte($currentWindowEnd)) {
                $monthKey = $trendCursor->toDateString();
                $crimeTrendLabels[] = $trendCursor->format('M y');
                $crimeTrendValues[] = (int) ($crimeTrendSeriesRows[$monthKey] ?? 0);
                $trendCursor->addMonth();
            }

            $crimeTrendByType = $trendCounts->keyBy('crime_type');
            $crimeSummaryRows = $trendCounts
                ->filter(fn (array $crime): bool => (int) $crime['current_total'] > 0)
                ->sortByDesc('current_total')
                ->take(10)
                ->values();
            $crimeTotal = (int) $crimeSummaryRows->sum('current_total');
            $nationalCrimeTrendByType = $this->nationalCrimeTrendByType(
                $previousWindowStart,
                $crimeWindowStart,
                $currentWindowEnd
            );

            $crimeData = $crimeSummaryRows
                ->map(function (array $crime) use ($crimeTotal, $crimeTrendByType, $nationalCrimeTrendByType): array {
                    $crimeType = (string) $crime['crime_type'];
                    $trend = $crimeTrendByType->get($crimeType);

                    return [
                        'crime_type' => $crimeType,
                        'total' => (int) $crime['current_total'],
                        'pct' => $crimeTotal > 0 ? round(((int) $crime['current_total'] * 100) / $crimeTotal, 1) : 0.0,
                        'pct_change' => (float) ($trend['pct_change'] ?? 0.0),
                        'national_pct_change' => $nationalCrimeTrendByType->get($crimeType),
                    ];
                })
                ->values()
                ->all();

            $totalCurrent = (int) $trendCounts->sum('current_total');
            $totalPrevious = (int) $trendCounts->sum('previous_total');
            $totalChange = $this->percentageChange($totalPrevious, $totalCurrent);

            $crimeTrend = $trendCounts
                ->map(function (array $crime) use ($totalCurrent, $totalPrevious, $totalChange): array {
                    $crime['total_current'] = $totalCurrent;
                    $crime['total_previous'] = $totalPrevious;
                    $crime['total_pct_change'] = $totalChange;

                    return $crime;
                })
                ->sortByDesc('pct_change')
                ->values();

            $topIncrease = $crimeTrend
                ->first(fn (array $crime): bool => (float) $crime['pct_change'] > 0);

            if ($topIncrease === null) {
                $topIncrease = $crimeTrend->sortByDesc('pct_change')->first();
            }

            $topDecrease = $crimeTrend
                ->sortBy('pct_change')
                ->first(fn (array $crime): bool => (float) $crime['pct_change'] < 0);

            if ($topDecrease === null) {
                $topDecrease = $crimeTrend->sortBy('pct_change')->first();
            }

            $crimeDirection = 'stable';

            if ($totalChange > 10) {
                $crimeDirection = 'rising';
            } elseif ($totalChange < -10) {
                $crimeDirection = 'falling';
            }

            if ($topIncrease !== null && (int) $topIncrease['previous_total'] < 20) {
                $topIncrease['pct_change_label'] = $topIncrease['pct_change'].'% (low volume)';
            }

            $directionWord = $totalChange > 0 ? 'up' : 'down';
            $crimeSummary = 'Crime is '.$directionWord.' '.abs($totalChange).'% over the past year';

            if ($topIncrease !== null && $topDecrease !== null) {
                $crimeSummary .= ', driven by increases in '.strtolower((string) $topIncrease['crime_type']);
                $crimeSummary .= ' and offset by decreases in '.strtolower((string) $topDecrease['crime_type']).'.';
            } else {
                $crimeSummary .= '.';
            }

            return [
                'crime_data' => $crimeData,
                'crime_trend' => $crimeTrend->all(),
                'crime_summary' => $crimeSummary,
                'crime_direction' => $crimeDirection,
                'crime_trend_labels' => $crimeTrendLabels,
                'crime_trend_values' => $crimeTrendValues,
                'crime_total_change' => $totalChange,
                'crime_top_increase' => $topIncrease,
                'crime_top_decrease' => $topDecrease,
            ];
        });
    }

    /**
     * Cache the grouped `crime_type` period totals so area and national pages do
     * not repeatedly scan the raw crime table during normal public traffic.
     *
     * @return array<int, array{crime_type:string,total_12m:int,prev_12m:int,pct_change:float}>
     */
    public function areaCrimeTypeBreakdown(?string $area, Carbon $currentStart, Carbon $currentEnd, Carbon $previousStart): array
    {
        $cacheKey = $this->areaBreakdownCacheKey($area, $currentStart, $currentEnd, $previousStart);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $area,
            $currentStart,
            $currentEnd,
            $previousStart
        ): array {
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
        });
    }

    public static function pointCacheKey(
        float $latitude,
        float $longitude,
        Carbon $previousWindowStart,
        Carbon $currentWindowEnd
    ): string {
        return sprintf(
            'crime:summary:%s:point:%s:%s:%s:%s:%s',
            self::CACHE_VERSION,
            number_format($latitude, 6, '.', ''),
            number_format($longitude, 6, '.', ''),
            number_format(self::RADIUS_DELTA, 3, '.', ''),
            $previousWindowStart->toDateString(),
            $currentWindowEnd->toDateString()
        );
    }

    /**
     * @return array{0:Carbon,1:Carbon,2:Carbon}
     */
    private function pointWindows(Carbon $latestCrimeMonth): array
    {
        $currentWindowEnd = $latestCrimeMonth->copy()->startOfMonth();
        $crimeWindowStart = $currentWindowEnd->copy()->subMonths(11);
        $previousWindowStart = $crimeWindowStart->copy()->subMonths(12);

        return [$currentWindowEnd, $crimeWindowStart, $previousWindowStart];
    }

    private function latestCrimeMonth(): ?Carbon
    {
        $latestMonth = Cache::remember(
            sprintf('crime:summary:%s:latest-month', self::CACHE_VERSION),
            self::CACHE_TTL,
            fn (): ?string => Crime::query()->max('month')
        );

        return $latestMonth === null ? null : Carbon::parse($latestMonth)->startOfMonth();
    }

    /**
     * @return Collection<int, array{crime_type:string,current_total:int,previous_total:int,pct_change:float}>
     */
    private function pointTrendCounts(
        float $latitude,
        float $longitude,
        Carbon $currentWindowEnd,
        Carbon $crimeWindowStart,
        Carbon $previousWindowStart
    ): Collection {
        return Crime::query()
            ->select('crime_type')
            ->selectRaw(
                'SUM(CASE WHEN month >= ? THEN 1 ELSE 0 END) as current_total,
                SUM(CASE WHEN month >= ? AND month < ? THEN 1 ELSE 0 END) as previous_total',
                [
                    $crimeWindowStart->toDateString(),
                    $previousWindowStart->toDateString(),
                    $crimeWindowStart->toDateString(),
                ]
            )
            ->whereDate('month', '>=', $previousWindowStart->toDateString())
            ->whereDate('month', '<=', $currentWindowEnd->toDateString())
            ->whereBetween('latitude', [$latitude - self::RADIUS_DELTA, $latitude + self::RADIUS_DELTA])
            ->whereBetween('longitude', [$longitude - self::RADIUS_DELTA, $longitude + self::RADIUS_DELTA])
            ->whereNotNull('crime_type')
            ->where('crime_type', '!=', '')
            ->groupBy('crime_type')
            ->get()
            ->map(function (Crime $crime): array {
                $currentTotal = (int) $crime->current_total;
                $previousTotal = (int) $crime->previous_total;

                return [
                    'crime_type' => (string) $crime->crime_type,
                    'current_total' => $currentTotal,
                    'previous_total' => $previousTotal,
                    'pct_change' => $this->percentageChange($previousTotal, $currentTotal),
                ];
            })
            ->filter(fn (array $crime): bool => $crime['current_total'] > 0 || $crime['previous_total'] > 0)
            ->values();
    }

    /**
     * @return Collection<string, float>
     */
    private function nationalCrimeTrendByType(
        Carbon $previousWindowStart,
        Carbon $crimeWindowStart,
        Carbon $currentWindowEnd
    ): Collection {
        $cacheKey = sprintf(
            'crime:summary:%s:national-trend:%s:%s',
            self::CACHE_VERSION,
            $previousWindowStart->toDateString(),
            $currentWindowEnd->toDateString()
        );

        /** @var Collection<string, float> $trend */
        $trend = Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $previousWindowStart,
            $crimeWindowStart,
            $currentWindowEnd
        ): Collection {
            return Crime::query()
                ->select('crime_type')
                ->selectRaw(
                    'SUM(CASE WHEN month >= ? THEN 1 ELSE 0 END) as current_total,
                    SUM(CASE WHEN month >= ? AND month < ? THEN 1 ELSE 0 END) as previous_total',
                    [
                        $crimeWindowStart->toDateString(),
                        $previousWindowStart->toDateString(),
                        $crimeWindowStart->toDateString(),
                    ]
                )
                ->whereDate('month', '>=', $previousWindowStart->toDateString())
                ->whereDate('month', '<=', $currentWindowEnd->toDateString())
                ->whereNotNull('crime_type')
                ->where('crime_type', '!=', '')
                ->groupBy('crime_type')
                ->get()
                ->mapWithKeys(function (Crime $crime): array {
                    $currentTotal = (int) $crime->current_total;
                    $previousTotal = (int) $crime->previous_total;

                    return [
                        (string) $crime->crime_type => $this->percentageChange($previousTotal, $currentTotal),
                    ];
                });
        });

        return $trend;
    }

    private function percentageChange(int $baseline, int $current): float
    {
        if ($baseline === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $baseline) * 100) / $baseline, 1);
    }

    /**
     * @return array{
     *     crime_data:array<int, array<string, mixed>>,
     *     crime_trend:array<int, array<string, mixed>>,
     *     crime_summary:?string,
     *     crime_direction:string,
     *     crime_trend_labels:array<int, string>,
     *     crime_trend_values:array<int, int>,
     *     crime_total_change:float,
     *     crime_top_increase:?array<string, mixed>,
     *     crime_top_decrease:?array<string, mixed>
     * }
     */
    private function emptyPointSummary(): array
    {
        return [
            'crime_data' => [],
            'crime_trend' => [],
            'crime_summary' => null,
            'crime_direction' => 'stable',
            'crime_trend_labels' => [],
            'crime_trend_values' => [],
            'crime_total_change' => 0.0,
            'crime_top_increase' => null,
            'crime_top_decrease' => null,
        ];
    }

    private function areaBreakdownCacheKey(?string $area, Carbon $currentStart, Carbon $currentEnd, Carbon $previousStart): string
    {
        $areaKey = $area === null ? 'national' : Str::slug($area);

        return sprintf(
            'crime:summary:%s:area-breakdown:%s:%s:%s:%s',
            self::CACHE_VERSION,
            $areaKey,
            $previousStart->toDateString(),
            $currentStart->toDateString(),
            $currentEnd->toDateString()
        );
    }

    private function crimeQueryForArea(?string $area): Builder
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
}
