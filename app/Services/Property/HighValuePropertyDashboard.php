<?php

namespace App\Services\Property;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HighValuePropertyDashboard
{
    private const CATEGORY = 'A';

    /** @return array<string, mixed> */
    public function cachedDataFor(Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();

        return Cache::remember('property:high-value:v2:'.$month->format('Ym'), now()->addDay(), fn (): array => $this->build($month));
    }

    public function latestMonth(): Carbon
    {
        $latestDate = DB::table('land_registry')->where('PPDCategoryType', self::CATEGORY)->max('Date');

        return $latestDate === null ? now()->startOfMonth() : Carbon::parse($latestDate)->startOfMonth();
    }

    public function isAvailable(Carbon $month): bool
    {
        $month = $month->copy()->startOfMonth();

        return $month->lessThanOrEqualTo($this->latestMonth()) && $this->monthQuery($month)->exists();
    }

    /** @return array<int, Carbon> */
    public function availableMonthsForYear(int $year): array
    {
        $expression = DB::connection()->getDriverName() === 'pgsql' ? 'EXTRACT(MONTH FROM "Date")' : 'CAST(strftime(\'%m\', "Date") AS INTEGER)';

        return DB::table('land_registry')->where('PPDCategoryType', self::CATEGORY)
            ->whereBetween('Date', [Carbon::create($year)->startOfYear(), Carbon::create($year)->endOfYear()])
            ->selectRaw($expression.' as month_number')->groupByRaw($expression)->orderByRaw($expression)
            ->pluck('month_number')->map(fn (mixed $value): Carbon => Carbon::create($year, (int) $value, 1))->all();
    }

    /** @return array<string, mixed> */
    public function build(Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $market = $this->monthQuery($month);
        $threshold = $this->percentile(clone $market, 0.9);
        $highValue = (clone $market)->where('Price', '>=', $threshold ?? PHP_INT_MAX);
        $marketValue = (int) (clone $market)->sum('Price');
        $segmentValue = (int) (clone $highValue)->sum('Price');
        $segmentSales = (clone $highValue)->count();
        $districts = $this->districts(clone $market, clone $highValue);

        return [
            'month' => $month,
            'threshold' => $threshold,
            'headline' => [
                'sales' => $segmentSales,
                'median_price' => $this->median(clone $highValue),
                'highest_sale' => (int) ((clone $highValue)->max('Price') ?? 0),
                'total_value' => $segmentValue,
                'value_share' => $marketValue > 0 ? round(($segmentValue / $marketValue) * 100, 1) : 0.0,
            ],
            'comparison' => $this->comparison($month, $threshold),
            'thresholdTrend' => $this->thresholdTrend($month),
            'mapPoints' => $this->mapPoints(clone $highValue, $districts),
            'topSales' => $this->topSales(clone $highValue),
            'propertyTypes' => $this->composition(clone $highValue, 'PropertyType', ['Detached' => 'D', 'Semi-detached' => 'S', 'Terraced' => 'T', 'Flat / maisonette' => 'F', 'Other' => 'O'], $segmentSales),
            'tenure' => $this->composition(clone $highValue, 'Duration', ['Freehold' => 'F', 'Leasehold' => 'L'], $segmentSales),
            'mostSalesHotspots' => $districts->sortByDesc('high_value_sales')->take(10)->values()->all(),
            'highestConcentrationHotspots' => $districts->filter(fn (array $row): bool => $row['all_sales'] >= 3)->sortByDesc('concentration')->take(10)->values()->all(),
            'millionMarket' => $this->millionMarket(clone $market),
            'observations' => $this->observations($month, $threshold, $segmentValue, $marketValue, clone $highValue, clone $market),
            'recordSales' => $this->recordSales(),
        ];
    }

    private function monthQuery(Carbon $month): Builder
    {
        return DB::table('land_registry')->where('PPDCategoryType', self::CATEGORY)->where('Price', '>', 0)
            ->whereBetween('Date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
    }

    private function percentile(Builder $query, float $percentile): ?int
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $value = $query->selectRaw('PERCENTILE_CONT(?) WITHIN GROUP (ORDER BY "Price") as value', [$percentile])->value('value');

            return $value === null ? null : (int) round((float) $value);
        }

        $count = (clone $query)->count();

        return $count === 0 ? null : (int) $query->orderBy('Price')->offset((int) ceil($percentile * $count) - 1)->value('Price');
    }

    private function median(Builder $query): ?int
    {
        return $this->percentile($query, 0.5);
    }

    /** @return array<string, mixed> */
    private function comparison(Carbon $month, ?int $threshold): array
    {
        $previous = $month->copy()->subMonth();
        $yearAgo = $month->copy()->subYear();

        return [
            'previous_label' => $previous->format('F'),
            'year_label' => $yearAgo->format('F Y'),
            'previous_change' => $this->percentageChange($threshold, $this->percentile($this->monthQuery($previous), 0.9)),
            'year_change' => $this->percentageChange($threshold, $this->percentile($this->monthQuery($yearAgo), 0.9)),
        ];
    }

    private function percentageChange(?int $current, ?int $comparison): ?float
    {
        return $current === null || $comparison === null || $comparison === 0 ? null : round((($current - $comparison) / $comparison) * 100, 1);
    }

    /** @return array<int, array{label: string, value: int}> */
    private function thresholdTrend(Carbon $month): array
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return DB::table('land_registry')
                ->where('PPDCategoryType', self::CATEGORY)
                ->where('Price', '>', 0)
                ->whereBetween('Date', [$month->copy()->subMonths(23)->startOfMonth(), $month->copy()->endOfMonth()])
                ->selectRaw('DATE_TRUNC(\'month\', "Date") as month, ROUND(PERCENTILE_CONT(0.9) WITHIN GROUP (ORDER BY "Price")) as value')
                ->groupByRaw('DATE_TRUNC(\'month\', "Date")')
                ->orderByRaw('DATE_TRUNC(\'month\', "Date")')
                ->get()
                ->map(fn (object $row): array => ['label' => Carbon::parse($row->month)->format('M y'), 'value' => (int) $row->value])
                ->all();
        }

        return collect(range(23, 0))->map(fn (int $offset): Carbon => $month->copy()->subMonths($offset))
            ->map(function (Carbon $trendMonth): ?array {
                $value = $this->percentile($this->monthQuery($trendMonth), 0.9);

                return $value === null ? null : ['label' => $trendMonth->format('M y'), 'value' => $value];
            })->filter()->values()->all();
    }

    /** @return Collection<int, array<string, int|float|string|null>> */
    private function districts(Builder $market, Builder $highValue): Collection
    {
        $all = $market->whereNotNull('District')->where('District', '!=', '')->selectRaw('"District" as district, COUNT(*) as all_sales')
            ->groupBy('District')->pluck('all_sales', 'district');

        $medianExpression = DB::connection()->getDriverName() === 'pgsql'
            ? 'ROUND(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY "Price"))'
            : 'ROUND(AVG("Price"))';

        return $highValue->whereNotNull('District')->where('District', '!=', '')
            ->selectRaw('"District" as district, COUNT(*) as high_value_sales, SUM("Price") as total_value, '.$medianExpression.' as median_price')->groupBy('District')->get()
            ->map(function (object $row) use ($all): array {
                $allSales = (int) $all->get($row->district, 0);

                return [
                    'district' => (string) $row->district,
                    'high_value_sales' => (int) $row->high_value_sales,
                    'all_sales' => $allSales,
                    'median_price' => $row->median_price === null ? null : (int) $row->median_price,
                    'total_value' => (int) $row->total_value,
                    'concentration' => $allSales > 0 ? round(((int) $row->high_value_sales / $allSales) * 100, 1) : 0.0,
                ];
            });
    }

    /** @param Collection<int, array<string, mixed>> $districts
     * @return array<int, array<string, mixed>>
     */
    private function mapPoints(Builder $highValue, Collection $districts): array
    {
        if (! Schema::hasTable('onspd_v2')) {
            return [];
        }

        $coordinates = $highValue->join('onspd_v2 as o', DB::raw("REPLACE(o.pcds, ' ', '')"), '=', DB::raw("REPLACE(land_registry.\"Postcode\", ' ', '')"))
            ->whereNotNull('o.lat')->whereNotNull('o.long')->selectRaw('"District" as district, AVG(o.lat) as lat, AVG(o.long) as lng')
            ->groupBy('District')->get()->keyBy('district');

        return $districts->map(function (array $district) use ($coordinates): ?array {
            $point = $coordinates->get($district['district']);

            return $point === null ? null : [...$district, 'lat' => (float) $point->lat, 'lng' => (float) $point->lng];
        })->filter()->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function topSales(Builder $query): array
    {
        return $query->orderByDesc('Price')->limit(10)->get(['Price', 'Date', 'Postcode', 'PAON', 'SAON', 'Street', 'TownCity', 'District', 'PropertyType', 'Duration'])
            ->map(fn (object $sale): array => [
                'price' => (int) $sale->Price, 'date' => Carbon::parse($sale->Date)->format('j M Y'), 'postcode' => (string) $sale->Postcode,
                'address' => collect([$sale->PAON, $sale->SAON, $sale->Street])->filter()->implode(', '),
                'area' => collect([$sale->TownCity, $sale->District])->filter()->unique()->implode(', '),
                'property_type' => (string) $sale->PropertyType, 'tenure' => (string) $sale->Duration, 'property_slug' => $this->propertySlug($sale),
            ])->all();
    }

    /** @param array<string, string> $values
     * @return array<int, array<string, mixed>>
     */
    private function composition(Builder $query, string $column, array $values, int $total): array
    {
        return collect($values)->map(function (string $code, string $label) use ($query, $column, $total): array {
            $group = (clone $query)->where($column, $code);
            $count = (clone $group)->count();

            return ['label' => $label, 'count' => $count, 'share' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0, 'median_price' => $this->median($group)];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function millionMarket(Builder $market): array
    {
        $counts = collect([1000000, 2000000, 5000000, 10000000])->mapWithKeys(fn (int $threshold): array => [(string) $threshold => (clone $market)->where('Price', '>=', $threshold)->count()])->all();
        $million = (clone $market)->where('Price', '>=', 1000000);
        $millionCount = (int) $counts['1000000'];
        $londonCount = (clone $million)->whereRaw('UPPER("County") = ?', ['GREATER LONDON'])->count();
        $outsideLondon = (clone $million)->whereRaw('UPPER("County") <> ?', ['GREATER LONDON'])->orderByDesc('Price')->first(['Price', 'TownCity', 'District']);
        $topArea = (clone $million)->whereNotNull('District')->selectRaw('"District" as district, COUNT(*) as sales')->groupBy('District')->orderByDesc('sales')->first();

        return [
            'counts' => $counts,
            'london_share' => $millionCount > 0 ? round(($londonCount / $millionCount) * 100, 1) : 0.0,
            'outside_london' => $outsideLondon === null ? null : ['price' => (int) $outsideLondon->Price, 'area' => (string) ($outsideLondon->TownCity ?: $outsideLondon->District)],
            'top_area' => $topArea === null ? null : ['district' => (string) $topArea->district, 'sales' => (int) $topArea->sales],
        ];
    }

    /** @return array<int, string> */
    private function observations(Carbon $month, ?int $threshold, int $segmentValue, int $marketValue, Builder $highValue, Builder $market): array
    {
        $top = (clone $highValue)->orderByDesc('Price')->first(['Price', 'TownCity', 'District']);
        $change = $this->percentageChange($threshold, $this->percentile($this->monthQuery($month->copy()->subMonth()), 0.9));
        $million = (clone $market)->where('Price', '>=', 1000000);
        $millionCount = (clone $million)->count();
        $londonCount = (clone $million)->whereRaw('UPPER("County") = ?', ['GREATER LONDON'])->count();
        $facts = [];

        if ($top !== null) {
            $facts[] = 'The most expensive recorded sale was £'.number_format((int) $top->Price).' in '.($top->TownCity ?: $top->District).'.';
        }
        if ($threshold !== null && $change !== null) {
            $facts[] = 'The top-10% threshold was £'.number_format($threshold).', '.number_format(abs($change), 1).'% '.($change >= 0 ? 'higher' : 'lower').' than '.$month->copy()->subMonth()->format('F').'.';
        }
        if ($millionCount > 0) {
            $facts[] = 'London accounted for '.number_format(($londonCount / $millionCount) * 100, 1).'% of £1m+ transactions.';
        }
        if ($marketValue > 0) {
            $facts[] = 'The top 10% represented '.number_format(($segmentValue / $marketValue) * 100, 1).'% of total residential transaction value.';
        }

        return $facts;
    }

    /** @return array<int, array<string, mixed>> */
    private function recordSales(): array
    {
        return DB::table('land_registry')->where('PPDCategoryType', self::CATEGORY)->where('Price', '>', 0)->orderByDesc('Price')->limit(3)
            ->get(['Price', 'Date', 'Postcode', 'PAON', 'SAON', 'Street', 'TownCity'])->map(fn (object $sale): array => [
                'price' => (int) $sale->Price, 'date' => Carbon::parse($sale->Date)->format('j M Y'),
                'address' => collect([$sale->PAON, $sale->SAON, $sale->Street])->filter()->implode(', '), 'area' => (string) $sale->TownCity,
                'property_slug' => $this->propertySlug($sale),
            ])->all();
    }

    private function propertySlug(object $sale): string
    {
        return collect([$sale->Postcode, $sale->PAON, $sale->Street, $sale->SAON ?? null])->filter(fn (mixed $part): bool => trim((string) $part) !== '')
            ->map(fn (mixed $part): string => trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $part))) ?? '', '-'))->implode('-');
    }
}
