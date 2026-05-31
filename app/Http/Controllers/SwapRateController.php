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
            return view('insights.swap-rates', [
                'latestRates' => [],
                'rateRanges' => [],
                'latestAvailableDate' => null,
                'latestMovementSummary' => null,
                'currentRatesTable' => [],
                'rateChart' => ['labels' => [], 'datasets' => []],
                'bankRateComparisonChart' => null,
            ]);
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
        $currentRatesTable = $this->buildCurrentRatesTable($latestRates, $terms);
        $bankRateComparisonChart = $this->buildBankRateComparisonChart($dates, $labels);

        return view('insights.swap-rates', [
            'latestRates' => $latestRates,
            'rateRanges' => $rateRanges,
            'latestAvailableDate' => $latestAvailableDate === null ? null : Carbon::parse($latestAvailableDate),
            'latestMovementSummary' => $latestMovementSummary,
            'currentRatesTable' => $currentRatesTable,
            'rateChart' => [
                'labels' => $labels,
                'datasets' => $rateDatasets,
            ],
            'bankRateComparisonChart' => $bankRateComparisonChart,
        ]);
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
     * @param  array<int, SwapRate|null>  $latestRates
     * @param  array<int>  $terms
     * @return array<int, array{term: string, rate: ?float, daily_change: ?float, rate_date: ?Carbon}>
     */
    private function buildCurrentRatesTable(array $latestRates, array $terms): array
    {
        return collect($terms)->map(function (int $termYears) use ($latestRates): array {
            $latestRate = $latestRates[$termYears] ?? null;

            return [
                'term' => $termYears.' Year',
                'rate' => $latestRate === null ? null : round((float) $latestRate->rate, 4),
                'daily_change' => $latestRate === null || $latestRate->daily_change === null
                    ? null
                    : round((float) $latestRate->daily_change, 4),
                'rate_date' => $latestRate?->rate_date,
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
