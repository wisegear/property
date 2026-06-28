<?php

namespace App\Http\Controllers;

use App\Models\InterestRate;
use App\Models\SwapRate;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SwapRateController extends Controller
{
    public function index(): View
    {
        $terms = [2, 5, 10];

        if (! Schema::hasTable('swap_rates')) {
            return view('insights.swap-rates', $this->emptyViewData());
        }

        $rates = SwapRate::query()
            ->where('curve_type', 'ois')
            ->whereIn('term_years', $terms)
            ->orderBy('rate_date')
            ->get();

        $latestRates = $rates
            ->groupBy('term_years')
            ->map(fn (Collection $series): ?SwapRate => $series->last())
            ->all();

        $termSnapshots = $this->buildTermSnapshots($rates, $terms);

        $rateRanges = $this->buildRateRanges($rates, $latestRates, $terms);

        $latestAvailableDate = collect($latestRates)
            ->filter()
            ->map(fn (SwapRate $rate): string => $rate->rate_date->toDateString())
            ->sort()
            ->last();

        $dates = $rates
            ->groupBy(fn (SwapRate $swapRate): string => $swapRate->rate_date->toDateString())
            ->sortKeys();

        $labels = $dates->keys()->all();

        $rateDatasets = [];

        foreach ($terms as $termYears) {
            $rateDatasets[] = [
                'label' => $termYears.'Y Swap',
                'term' => $termYears,
                'data' => array_map(function (string $date) use ($dates, $termYears): ?float {
                    $row = $dates[$date]->firstWhere('term_years', $termYears);

                    return $row === null ? null : round((float) $row->rate, 4);
                }, $labels),
            ];
        }

        $latestMovementSummary = $this->buildLatestMovementSummary($latestRates, $terms);
        $mortgageMarketSummary = $this->buildMortgageMarketSummary($termSnapshots);
        $latestMovementDetails = $this->buildLatestMovementDetails($termSnapshots, $latestAvailableDate);
        $currentRatesTable = $this->buildCurrentRatesTable($termSnapshots, $terms);
        $bankRateComparisonChart = $this->buildBankRateComparisonChart($dates, $labels);

        return view('insights.swap-rates', [
            'latestRates' => $latestRates,
            'termSnapshots' => $termSnapshots,
            'rateRanges' => $rateRanges,
            'latestAvailableDate' => $latestAvailableDate === null ? null : Carbon::parse($latestAvailableDate),
            'mortgageMarketSummary' => $mortgageMarketSummary,
            'latestMovementSummary' => $latestMovementSummary,
            'latestMovementDetails' => $latestMovementDetails,
            'currentRatesTable' => $currentRatesTable,
            'rateChart' => [
                'labels' => $labels,
                'datasets' => $rateDatasets,
            ],
            'bankRateComparisonChart' => $bankRateComparisonChart,
        ]);
    }

    /**
     * @return array{
     *     latestRates: array<int, SwapRate|null>,
     *     termSnapshots: array<int, array<string, mixed>>,
     *     rateRanges: array<int, array{low: ?float, high: ?float}>,
     *     latestAvailableDate: ?Carbon,
     *     mortgageMarketSummary: ?array<string, mixed>,
     *     latestMovementSummary: ?array{text: string, direction: string},
     *     latestMovementDetails: ?array<string, mixed>,
     *     currentRatesTable: array<int, array<string, mixed>>,
     *     rateChart: array{labels: array<int, string>, datasets: array<int, array<string, mixed>>},
     *     bankRateComparisonChart: ?array<string, mixed>
     * }
     */
    private function emptyViewData(): array
    {
        return [
            'latestRates' => [],
            'termSnapshots' => [],
            'rateRanges' => [],
            'latestAvailableDate' => null,
            'mortgageMarketSummary' => null,
            'latestMovementSummary' => null,
            'latestMovementDetails' => null,
            'currentRatesTable' => [],
            'rateChart' => ['labels' => [], 'datasets' => []],
            'bankRateComparisonChart' => null,
        ];
    }

    /**
     * @param  EloquentCollection<int, SwapRate>  $rates
     * @param  array<int, SwapRate|null>  $latestRates
     * @param  array<int>  $terms
     * @return array<int, array{low: ?float, high: ?float}>
     */
    private function buildRateRanges(EloquentCollection $rates, array $latestRates, array $terms): array
    {
        $ratesByTerm = $rates->groupBy('term_years');
        $ranges = [];

        foreach ($terms as $termYears) {
            $latestRate = $latestRates[$termYears] ?? null;

            if ($latestRate === null) {
                $ranges[$termYears] = ['low' => null, 'high' => null];

                continue;
            }

            $startDate = $latestRate->rate_date->copy()->subWeeks(52);
            $termRates = ($ratesByTerm[$termYears] ?? collect())
                ->filter(fn (SwapRate $rate): bool => $rate->rate_date->gte($startDate));

            if ($termRates->isEmpty()) {
                $ranges[$termYears] = ['low' => null, 'high' => null];

                continue;
            }

            $ranges[$termYears] = [
                'low' => round((float) $termRates->min('rate'), 4),
                'high' => round((float) $termRates->max('rate'), 4),
            ];
        }

        return $ranges;
    }

    /**
     * @param  EloquentCollection<int, SwapRate>  $rates
     * @param  array<int>  $terms
     * @return array<int, array{
     *     term_years: int,
     *     label: string,
     *     latest_rate: ?float,
     *     latest_rate_date: ?Carbon,
     *     previous_rate: ?float,
     *     previous_rate_date: ?Carbon,
     *     latest_movement: ?float,
     *     five_day_change: ?float,
     *     trend: ?array{label: string, direction: string},
     *     sparkline: array<int, float>,
     *     sparkline_dates: array<int, string>
     * }>
     */
    private function buildTermSnapshots(EloquentCollection $rates, array $terms): array
    {
        $ratesByTerm = $rates->groupBy('term_years');
        $snapshots = [];

        foreach ($terms as $termYears) {
            /** @var EloquentCollection<int, SwapRate> $series */
            $series = ($ratesByTerm[$termYears] ?? collect())
                ->sortBy('rate_date')
                ->values();

            /** @var SwapRate|null $latest */
            $latest = $series->last();
            /** @var SwapRate|null $previous */
            $previous = $series->count() > 1 ? $series->get($series->count() - 2) : null;

            $latestMovement = null;

            if ($latest !== null && $previous !== null) {
                $latestMovement = round((float) $latest->rate - (float) $previous->rate, 4);
            } elseif ($latest?->daily_change !== null) {
                $latestMovement = round((float) $latest->daily_change, 4);
            }

            $comparisonRate = $series->count() >= 5 ? $series->get($series->count() - 5) : null;
            $fiveDayChange = $latest !== null && $comparisonRate !== null
                ? round((float) $latest->rate - (float) $comparisonRate->rate, 4)
                : null;

            $trend = $this->buildTrendBadge($fiveDayChange);
            $sparklineSeries = $series->slice(-30)->values();

            $snapshots[$termYears] = [
                'term_years' => $termYears,
                'label' => $termYears.'Y Swap',
                'latest_rate' => $latest === null ? null : round((float) $latest->rate, 4),
                'latest_rate_date' => $latest?->rate_date,
                'previous_rate' => $previous === null ? null : round((float) $previous->rate, 4),
                'previous_rate_date' => $previous?->rate_date,
                'latest_movement' => $latestMovement,
                'five_day_change' => $fiveDayChange,
                'trend' => $trend,
                'sparkline' => $sparklineSeries
                    ->map(fn (SwapRate $rate): float => round((float) $rate->rate, 4))
                    ->all(),
                'sparkline_dates' => $sparklineSeries
                    ->map(fn (SwapRate $rate): string => $rate->rate_date->toDateString())
                    ->all(),
            ];
        }

        return $snapshots;
    }

    /**
     * @return array{label: string, direction: string}|null
     */
    private function buildTrendBadge(?float $fiveDayChange): ?array
    {
        if ($fiveDayChange === null) {
            return null;
        }

        if ($fiveDayChange >= 0.10) {
            return ['label' => 'Rising over 5 days', 'direction' => 'rising'];
        }

        if ($fiveDayChange <= -0.10) {
            return ['label' => 'Falling over 5 days', 'direction' => 'falling'];
        }

        return ['label' => 'Stable over 5 days', 'direction' => 'stable'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $termSnapshots
     * @return array{
     *     signal: string,
     *     explanation: string,
     *     signal_direction: string
     * }|null
     */
    private function buildMortgageMarketSummary(array $termSnapshots): ?array
    {
        $movements = collect($termSnapshots)
            ->pluck('latest_movement')
            ->filter(fn ($value): bool => $value !== null)
            ->map(fn ($value): float => (float) $value)
            ->values();

        if ($movements->isEmpty()) {
            return null;
        }

        $positiveCount = $movements->filter(fn (float $value): bool => $value > 0)->count();
        $negativeCount = $movements->filter(fn (float $value): bool => $value < 0)->count();
        $averageMovement = $movements->avg() ?? 0.0;

        if ($negativeCount > $positiveCount && $averageMovement <= -0.01) {
            return [
                'signal' => 'Improving',
                'signal_direction' => 'improving',
                'explanation' => 'Wholesale mortgage pricing pressure has eased slightly. If this continues, some lenders may have a little more room to reduce fixed-rate mortgage pricing.',
            ];
        }

        if ($positiveCount > $negativeCount && $averageMovement >= 0.01) {
            return [
                'signal' => 'Worsening',
                'signal_direction' => 'worsening',
                'explanation' => 'Wholesale mortgage pricing pressure has firmed slightly. If this continues, lenders could have less room to reduce fixed-rate mortgage pricing.',
            ];
        }

        return [
            'signal' => 'Neutral',
            'signal_direction' => 'neutral',
            'explanation' => 'Swap movements are fairly balanced. Fixed mortgage pricing pressure looks broadly steady for now, although lenders may still react to funding costs, competition and risk appetite.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $termSnapshots
     * @return array{
     *     title: string,
     *     label: string,
     *     lines: array<int, string>,
     *     biggest_mover: string,
     *     interpretation: string
     * }|null
     */
    private function buildLatestMovementDetails(array $termSnapshots, ?string $latestAvailableDate): ?array
    {
        $availableSnapshots = collect($termSnapshots)
            ->filter(fn (array $snapshot): bool => $snapshot['latest_movement'] !== null)
            ->values();

        if ($availableSnapshots->isEmpty()) {
            return null;
        }

        $lines = $availableSnapshots
            ->map(function (array $snapshot): string {
                $movement = (float) $snapshot['latest_movement'];
                $magnitude = number_format(abs($movement), 2);

                if (abs($movement) < 0.005) {
                    return sprintf('%d-year swaps were broadly unchanged.', $snapshot['term_years']);
                }

                return sprintf(
                    '%d-year swaps moved %s by %s percentage points.',
                    $snapshot['term_years'],
                    $movement > 0 ? 'up' : 'down',
                    $magnitude
                );
            })
            ->all();

        $biggestMover = $availableSnapshots
            ->sortByDesc(fn (array $snapshot): float => abs((float) $snapshot['latest_movement']))
            ->first();

        $positiveCount = $availableSnapshots->filter(fn (array $snapshot): bool => (float) $snapshot['latest_movement'] > 0)->count();
        $negativeCount = $availableSnapshots->filter(fn (array $snapshot): bool => (float) $snapshot['latest_movement'] < 0)->count();

        $interpretation = match (true) {
            $negativeCount > $positiveCount => 'This points to slightly lower wholesale funding pressure, which may support fixed mortgage pricing if the move continues.',
            $positiveCount > $negativeCount => 'This points to slightly firmer wholesale funding pressure, which could make mortgage price cuts less likely if the move continues.',
            default => 'This suggests wholesale funding expectations were mixed on the latest update, so mortgage pricing pressure does not look one-way.',
        };

        return [
            'title' => $latestAvailableDate !== null && Carbon::parse($latestAvailableDate)->isToday()
                ? 'What changed today?'
                : 'Latest available movement',
            'label' => $latestAvailableDate !== null && Carbon::parse($latestAvailableDate)->isToday()
                ? 'Today'
                : 'Latest available data',
            'lines' => $lines,
            'biggest_mover' => $biggestMover === null
                ? 'No clear biggest mover'
                : sprintf(
                    '%d-year swap (%s%s pts)',
                    $biggestMover['term_years'],
                    (float) $biggestMover['latest_movement'] > 0 ? '+' : '',
                    number_format((float) $biggestMover['latest_movement'], 2)
                ),
            'interpretation' => $interpretation,
        ];
    }

    /**
     * @param  array<int, SwapRate|null>  $latestRates
     * @param  array<int>  $terms
     * @return array{text: string, direction: string}|null
     */
    private function buildLatestMovementSummary(array $latestRates, array $terms): ?array
    {
        $movements = collect($terms)
            ->mapWithKeys(function (int $termYears) use ($latestRates): array {
                return [$termYears => ($latestRates[$termYears] ?? null)?->daily_change];
            })
            ->filter(fn ($value): bool => $value !== null)
            ->values();

        if ($movements->isEmpty()) {
            return null;
        }

        $direction = 'mixed';

        if ($movements->every(fn (float $value): bool => $value < 0)) {
            $direction = 'lower';
        } elseif ($movements->every(fn (float $value): bool => $value > 0)) {
            $direction = 'higher';
        }

        $termLabels = collect($terms)
            ->filter(fn (int $termYears): bool => ($latestRates[$termYears] ?? null)?->daily_change !== null)
            ->map(fn (int $termYears): string => $termYears.'Y')
            ->values();

        if ($termLabels->isEmpty()) {
            return null;
        }

        $termText = match ($termLabels->count()) {
            1 => $termLabels[0],
            2 => $termLabels[0].' and '.$termLabels[1],
            default => $termLabels->slice(0, -1)->implode(', ').' and '.$termLabels->last(),
        };

        return [
            'text' => match ($direction) {
                'lower' => "On the latest trading day, {$termText} swap rates all moved lower.",
                'higher' => "On the latest trading day, {$termText} swap rates all moved higher.",
                default => "On the latest trading day, {$termText} swap rates were mixed.",
            },
            'direction' => $direction,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $termSnapshots
     * @param  array<int>  $terms
     * @return array<int, array{term: string, rate: ?float, daily_change: ?float, five_day_change: ?float, rate_date: ?Carbon, previous_rate: ?float}>
     */
    private function buildCurrentRatesTable(array $termSnapshots, array $terms): array
    {
        return collect($terms)->map(function (int $termYears) use ($termSnapshots): array {
            $snapshot = $termSnapshots[$termYears] ?? null;

            return [
                'term' => $termYears.' Year',
                'rate' => $snapshot['latest_rate'] ?? null,
                'daily_change' => $snapshot['latest_movement'] ?? null,
                'five_day_change' => $snapshot['five_day_change'] ?? null,
                'previous_rate' => $snapshot['previous_rate'] ?? null,
                'rate_date' => $snapshot['latest_rate_date'] ?? null,
            ];
        })->all();
    }

    /**
     * @param  Collection<string, EloquentCollection<int, SwapRate>>  $dates
     * @param  array<int, string>  $labels
     * @return array{
     *     labels: array<int, string>,
     *     datasets: array<int, array{label: string, data: array<int, ?float>}>
     * }|null
     */
    private function buildBankRateComparisonChart(Collection $dates, array $labels): ?array
    {
        if ($labels === [] || ! Schema::hasTable('interest_rates')) {
            return null;
        }

        $interestRates = InterestRate::query()
            ->orderBy('effective_date')
            ->get(['effective_date', 'rate']);

        if ($interestRates->isEmpty()) {
            return null;
        }

        $bankRateSeries = [];
        $currentRate = null;
        $interestIndex = 0;

        foreach ($labels as $label) {
            $labelDate = Carbon::parse($label)->startOfDay();

            while (
                isset($interestRates[$interestIndex]) &&
                $interestRates[$interestIndex]->effective_date->startOfDay()->lte($labelDate)
            ) {
                $currentRate = round((float) $interestRates[$interestIndex]->rate, 2);
                $interestIndex++;
            }

            $bankRateSeries[] = $currentRate;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Bank Rate',
                    'data' => $bankRateSeries,
                ],
                [
                    'label' => '2Y Swap',
                    'data' => array_map(function (string $date) use ($dates): ?float {
                        $row = $dates[$date]->firstWhere('term_years', 2);

                        return $row === null ? null : round((float) $row->rate, 4);
                    }, $labels),
                ],
                [
                    'label' => '5Y Swap',
                    'data' => array_map(function (string $date) use ($dates): ?float {
                        $row = $dates[$date]->firstWhere('term_years', 5);

                        return $row === null ? null : round((float) $row->rate, 4);
                    }, $labels),
                ],
            ],
        ];
    }
}
