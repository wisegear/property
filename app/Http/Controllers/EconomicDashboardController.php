<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EconomicDashboardController extends Controller
{
    public function index(): View
    {
        return view('economic.dashboard', $this->dashboardData());
    }

    /**
     * Build the shared dashboard payload used by the website and native API.
     *
     * @return array<string, mixed>
     */
    public function dashboardData(): array
    {
        $ttl = now()->addHours(6);
        $approvalsSeriesCode = 'LPMVTVX';

        $metricFromRow = function ($row, array $extraExclude = []): ?float {
            if (! $row) {
                return null;
            }

            $exclude = array_merge(
                ['id', 'date', 'period', 'year', 'quarter', 'created_at', 'updated_at'],
                $extraExclude
            );

            foreach (get_object_vars($row) as $key => $value) {
                if (in_array($key, $exclude, true)) {
                    continue;
                }

                if (is_numeric($value)) {
                    return (float) $value;
                }
            }

            return null;
        };

        $interestSeries = $this->buildEventSeries(
            DB::table('interest_rates')->orderBy('effective_date')->get(),
            fn ($row) => Carbon::parse($row->effective_date),
            fn ($row) => (float) $row->rate
        );

        $inflationSeries = $this->buildMonthlySeries(
            DB::table('inflation_cpih_monthly')->orderBy('date')->get(),
            fn ($row) => Carbon::parse($row->date),
            fn ($row) => (float) ($metricFromRow($row) ?? 0)
        );

        $wagesSeries = $this->buildMonthlySeries(
            DB::table('wage_growth_monthly')->orderBy('date')->get(),
            fn ($row) => Carbon::parse($row->date),
            fn ($row) => (float) ($row->three_month_avg_yoy ?? 0)
        );

        $unemploymentSeries = $this->buildMonthlySeries(
            DB::table('unemployment_monthly')->orderBy('date')->get(),
            fn ($row) => Carbon::parse($row->date),
            fn ($row) => (float) ($row->three_month ?? 0)
        );

        $approvalsSeries = $this->buildMonthlySeries(
            DB::table('mortgage_approvals')
                ->where('series_code', $approvalsSeriesCode)
                ->orderBy('period')
                ->get(),
            fn ($row) => Carbon::parse($row->period),
            fn ($row) => (float) $row->value
        );

        $hpiSeries = $this->buildMonthlySeries(
            DB::table('hpi_monthly')
                ->where('AreaCode', 'K02000001')
                ->orderBy('Date')
                ->get(),
            fn ($row) => $this->parseHpiMonthlyDate((string) $row->Date),
            fn ($row) => (float) $row->AveragePrice
        );

        $repossessionsSeries = $this->buildQuarterSeries(
            DB::table('mlar_arrears')
                ->where('description', 'In possession')
                ->orderBy('year')
                ->orderByRaw("CASE quarter WHEN 'Q1' THEN 1 WHEN 'Q2' THEN 2 WHEN 'Q3' THEN 3 WHEN 'Q4' THEN 4 ELSE 5 END")
                ->get(),
            fn ($row) => $this->quarterKeyFromParts((int) $row->year, (string) $row->quarter),
            fn ($row) => (float) $row->value
        );

        $arrearsSeries = $this->buildQuarterSeries(
            DB::table('mlar_arrears')
                ->select('year', 'quarter', DB::raw('SUM(value) as total'))
                ->where('description', '!=', '1.5 < 2.5% in arrears')
                ->where('description', '!=', '1.5 < 2.5% in arrears ')
                ->groupBy('year', 'quarter')
                ->orderBy('year')
                ->orderByRaw("CASE quarter WHEN 'Q1' THEN 1 WHEN 'Q2' THEN 2 WHEN 'Q3' THEN 3 WHEN 'Q4' THEN 4 ELSE 5 END")
                ->get(),
            fn ($row) => $this->quarterKeyFromParts((int) $row->year, (string) $row->quarter),
            fn ($row) => (float) $row->total
        );

        $realWagesSeries = $this->buildMonthlyDifferenceSeries($wagesSeries, $inflationSeries);

        $sparklines = [
            'interest' => $interestSeries,
            'inflation' => $inflationSeries,
            'wages' => $wagesSeries,
            'unemployment' => $unemploymentSeries,
            'approvals' => $approvalsSeries,
            'hpi' => $hpiSeries,
            'repossessions' => $repossessionsSeries,
            'arrears' => $arrearsSeries,
        ];

        $interestSnapshot = $this->buildBankRateSnapshot($interestSeries);
        $inflationSnapshot = $this->buildLatestPublishedSnapshot($inflationSeries);
        $wagesSnapshot = $this->buildLatestPublishedSnapshot($wagesSeries);
        $realWagesSnapshot = $this->buildLatestPublishedSnapshot($realWagesSeries);
        $unemploymentSnapshot = $this->buildLatestPublishedSnapshot($unemploymentSeries);
        $approvalsSnapshot = $this->buildLatestPublishedSnapshot($approvalsSeries);
        $hpiSnapshot = $this->buildLatestPublishedSnapshot($hpiSeries);
        $repossessionsSnapshot = $this->buildLatestPublishedSnapshot($repossessionsSeries);
        $arrearsSnapshot = $this->buildLatestPublishedSnapshot($arrearsSeries);

        $cards = [
            $this->buildApprovalsCard($approvalsSnapshot),
            $this->buildHpiCard($hpiSnapshot),
            $this->buildInterestCard($interestSnapshot),
            $this->buildInflationCard($inflationSnapshot),
            $this->buildWagesCard($wagesSnapshot, $realWagesSnapshot),
            $this->buildUnemploymentCard($unemploymentSnapshot),
            $this->buildArrearsCard($arrearsSnapshot),
            $this->buildRepossessionsCard($repossessionsSnapshot),
        ];

        $statusCounts = collect($cards)
            ->groupBy(fn (array $card) => $card['status']['label'])
            ->map(fn (Collection $group) => $group->count());

        $summary = $this->buildSummary($cards);
        $totalStress = (int) collect($cards)->sum(fn (array $card) => $card['status']['weight']);

        Cache::put('eco:total_stress', $totalStress, $ttl);
        Cache::forever('eco:total_stress_persist', $totalStress);

        return [
            'cards' => $cards,
            'snapshots' => [
                'approvals' => $approvalsSnapshot,
                'hpi' => $hpiSnapshot,
                'interest' => $interestSnapshot,
                'inflation' => $inflationSnapshot,
                'wages' => $wagesSnapshot,
                'real_wages' => $realWagesSnapshot,
                'unemployment' => $unemploymentSnapshot,
                'arrears' => $arrearsSnapshot,
                'repossessions' => $repossessionsSnapshot,
            ],
            'sparklines' => $sparklines,
            'summary' => $summary,
            'statusCounts' => [
                'Positive' => $statusCounts->get('Positive', 0),
                'Neutral' => $statusCounts->get('Neutral', 0),
                'Warning' => $statusCounts->get('Warning', 0),
                'Stress' => $statusCounts->get('Stress', 0),
            ],
            'heroComparisonText' => 'Each panel shows the latest published figure. Its status reflects whether conditions improved, stayed level, or worsened across recent releases.',
            'totalStress' => $totalStress,
            'approvals' => $approvalsSnapshot['current_value'] !== null ? (object) [
                'period' => $approvalsSnapshot['current_period_label'],
                'value' => $approvalsSnapshot['current_value'],
            ] : null,
            'repossDirection' => $cards[7]['status']['weight'],
            'hpiDateLabel' => $hpiSnapshot['current_period_label'],
            'lastUpdated' => collect($sparklines)
                ->flatMap(fn (array $series): array => $series['points'])
                ->max(fn (array $point): string => $point['date']->toDateString()),
        ];
    }

    private function buildMonthlySeries(Collection $rows, callable $dateResolver, callable $valueResolver, bool $carryForwardMissingMonths = false): array
    {
        $points = [];

        foreach ($rows as $row) {
            $date = $dateResolver($row);

            if (! $date instanceof Carbon) {
                continue;
            }

            $points[] = [
                'key' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
                'date' => $date->copy()->startOfMonth(),
                'value' => round((float) $valueResolver($row), 3),
            ];
        }

        usort($points, function (array $left, array $right): int {
            if ($left['date']->equalTo($right['date'])) {
                return 0;
            }

            return $left['date']->lessThan($right['date']) ? -1 : 1;
        });

        if ($carryForwardMissingMonths) {
            $points = $this->fillMissingMonthlyPoints($points);
        }

        return [
            'type' => 'monthly',
            'points' => $points,
            'keys' => array_column($points, 'key'),
            'labels' => array_column($points, 'label'),
            'values' => array_column($points, 'value'),
        ];
    }

    private function buildEventSeries(Collection $rows, callable $dateResolver, callable $valueResolver): array
    {
        $points = [];

        foreach ($rows as $row) {
            $date = $dateResolver($row);

            if (! $date instanceof Carbon) {
                continue;
            }

            $points[] = [
                'key' => $date->format('Y-m-d'),
                'label' => $date->format('j M Y'),
                'date' => $date->copy()->startOfDay(),
                'value' => round((float) $valueResolver($row), 3),
            ];
        }

        usort($points, function (array $left, array $right): int {
            if ($left['date']->equalTo($right['date'])) {
                return 0;
            }

            return $left['date']->lessThan($right['date']) ? -1 : 1;
        });

        return [
            'type' => 'event',
            'points' => $points,
            'keys' => array_column($points, 'key'),
            'labels' => array_column($points, 'label'),
            'values' => array_column($points, 'value'),
        ];
    }

    private function fillMissingMonthlyPoints(array $points): array
    {
        if (empty($points)) {
            return [];
        }

        $filled = [];
        $currentDate = $points[0]['date']->copy()->startOfMonth();
        $endDate = $points[count($points) - 1]['date']->copy()->startOfMonth();
        $index = 0;
        $currentValue = null;

        while ($currentDate->lessThanOrEqualTo($endDate)) {
            if (
                isset($points[$index]) &&
                $points[$index]['date']->format('Y-m') === $currentDate->format('Y-m')
            ) {
                $currentValue = $points[$index]['value'];
                $index++;
            }

            if (! is_null($currentValue)) {
                $filled[] = [
                    'key' => $currentDate->format('Y-m'),
                    'label' => $currentDate->format('M Y'),
                    'date' => $currentDate->copy(),
                    'value' => round((float) $currentValue, 3),
                ];
            }

            $currentDate->addMonth();
        }

        return $filled;
    }

    private function buildQuarterSeries(Collection $rows, callable $keyResolver, callable $valueResolver): array
    {
        $points = [];

        foreach ($rows as $row) {
            $quarterKey = $keyResolver($row);
            $points[] = [
                'key' => $quarterKey,
                'label' => $this->formatQuarterLabel($quarterKey),
                'date' => $this->quarterKeyToDate($quarterKey),
                'value' => round((float) $valueResolver($row), 3),
            ];
        }

        usort($points, function (array $left, array $right): int {
            if ($left['date']->equalTo($right['date'])) {
                return 0;
            }

            return $left['date']->lessThan($right['date']) ? -1 : 1;
        });

        return [
            'type' => 'quarterly',
            'points' => $points,
            'keys' => array_column($points, 'key'),
            'labels' => array_column($points, 'label'),
            'values' => array_column($points, 'value'),
        ];
    }

    private function buildMonthlyDifferenceSeries(array $leftSeries, array $rightSeries): array
    {
        $rightByKey = [];
        foreach ($rightSeries['points'] as $point) {
            $rightByKey[$point['key']] = $point;
        }

        $points = [];
        foreach ($leftSeries['points'] as $point) {
            if (! isset($rightByKey[$point['key']])) {
                continue;
            }

            $points[] = [
                'key' => $point['key'],
                'label' => $point['label'],
                'date' => $point['date']->copy(),
                'value' => round($point['value'] - $rightByKey[$point['key']]['value'], 3),
            ];
        }

        return [
            'type' => 'monthly',
            'points' => $points,
            'keys' => array_column($points, 'key'),
            'labels' => array_column($points, 'label'),
            'values' => array_column($points, 'value'),
        ];
    }

    private function buildLatestPublishedSnapshot(array $series): array
    {
        $points = $series['points'] ?? [];
        $count = count($points);

        if ($count < 1) {
            return $this->emptySnapshot($series['type'] ?? 'monthly');
        }

        $currentPoint = $points[$count - 1];
        $previousPoint = $count >= 2 ? $points[$count - 2] : null;
        $frequency = $series['type'] ?? 'monthly';

        return [
            'current_value' => $currentPoint['value'],
            'previous_value' => $previousPoint['value'] ?? null,
            'current_label_heading' => 'Latest published figure',
            'previous_label_heading' => 'Previous release',
            'current_period_label' => $currentPoint['label'],
            'previous_period_label' => $previousPoint['label'] ?? 'Not available',
            'change_heading' => 'Latest movement',
            'trend_values' => array_values(array_map(
                fn (array $point): float => $point['value'],
                array_slice($points, -4)
            )),
            'debug' => [
                'latest_data_date' => $currentPoint['label'],
                'current_start' => $currentPoint['label'],
                'current_end' => $currentPoint['label'],
                'previous_start' => $previousPoint['label'] ?? 'n/a',
                'previous_end' => $previousPoint['label'] ?? 'n/a',
                'frequency' => $frequency,
            ],
        ];
    }

    private function buildBankRateSnapshot(array $series): array
    {
        $points = $series['points'] ?? [];

        if (empty($points)) {
            return $this->emptySnapshot('monthly');
        }

        $latestPoint = $points[count($points) - 1];
        $currentMonth = now()->startOfMonth();
        $monthlyValues = [];

        for ($monthsAgo = 3; $monthsAgo >= 0; $monthsAgo--) {
            $monthEnd = $currentMonth->copy()->subMonths($monthsAgo)->endOfMonth();
            $rateAtMonthEnd = collect($points)
                ->filter(fn (array $point): bool => $point['date']->lessThanOrEqualTo($monthEnd))
                ->last();

            if ($rateAtMonthEnd) {
                $monthlyValues[] = $rateAtMonthEnd['value'];
            }
        }

        $previousMonth = $currentMonth->copy()->subMonth();
        $previousValue = count($monthlyValues) >= 2 ? $monthlyValues[count($monthlyValues) - 2] : null;

        return [
            'current_value' => $latestPoint['value'],
            'previous_value' => $previousValue,
            'current_label_heading' => 'Current rate',
            'previous_label_heading' => 'Last month',
            'current_period_label' => 'Effective since '.$latestPoint['label'],
            'previous_period_label' => $previousMonth->format('M Y'),
            'change_heading' => 'Monthly movement',
            'trend_values' => $monthlyValues,
            'debug' => [
                'latest_data_date' => $latestPoint['label'],
                'current_start' => $latestPoint['label'],
                'current_end' => $latestPoint['label'],
                'previous_start' => $previousMonth->format('M Y'),
                'previous_end' => $previousMonth->format('M Y'),
                'frequency' => 'monthly',
            ],
        ];
    }

    private function emptySnapshot(string $frequency): array
    {
        return [
            'current_value' => null,
            'previous_value' => null,
            'current_label_heading' => 'Latest published figure',
            'previous_label_heading' => 'Previous release',
            'current_period_label' => 'Not available',
            'previous_period_label' => 'Not available',
            'change_heading' => 'Latest movement',
            'trend_values' => [],
            'debug' => [
                'latest_data_date' => 'n/a',
                'current_start' => 'n/a',
                'current_end' => 'n/a',
                'previous_start' => 'n/a',
                'previous_end' => 'n/a',
                'frequency' => $frequency,
            ],
        ];
    }

    private function buildApprovalsCard(array $snapshot): array
    {
        $current = $snapshot['current_value'];
        $previous = $snapshot['previous_value'];
        $status = $this->trendStatus($snapshot, badWhen: 'down', decimals: 0);

        return $this->baseCard(
            title: 'Mortgage approvals',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current).' approvals',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous).' approvals',
            change: $this->formatPercentChange($current, $previous, $snapshot['previous_period_label']),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Positive' => 'Buyer demand improved in the latest release.',
                'Neutral' => 'Buyer demand looks broadly steady.',
                'Warning' => 'Buyer demand is weakening.',
                default => 'Buyer demand has weakened for three consecutive releases.',
            },
            meaning: 'More approvals usually mean more buyers are active. That can support prices if the number of homes for sale stays tight.',
            sparkId: 'spark-approvals',
            sparkKey: 'approvals',
        );
    }

    private function buildHpiCard(array $snapshot): array
    {
        $current = $snapshot['current_value'];
        $previous = $snapshot['previous_value'];
        $status = $this->trendStatus($snapshot, badWhen: 'down', decimals: 0);

        return $this->baseCard(
            title: 'House prices (UK average)',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : '£'.number_format($current, 0),
            previousValue: is_null($previous) ? 'Not available' : '£'.number_format($previous, 0),
            change: $this->formatCurrencyChange($current, $previous, $snapshot['previous_period_label']),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Positive' => 'House prices increased in the latest release.',
                'Neutral' => 'House prices were unchanged in the latest release.',
                'Warning' => 'House prices are falling.',
                default => 'House prices have fallen for three consecutive releases.',
            },
            meaning: 'Stable or gently rising prices usually point to a healthier market backdrop. Falling prices can mean buyers are becoming more cautious or affordability is stretched.',
            sparkId: 'spark-hpi',
            sparkKey: 'hpi',
        );
    }

    private function buildInterestCard(array $snapshot): array
    {
        $current = $snapshot['current_value'];
        $previous = $snapshot['previous_value'];
        $status = $this->trendStatus($snapshot, badWhen: 'up', decimals: 2);

        return $this->baseCard(
            title: 'Bank rate',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 2).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 2).'%',
            change: $this->formatPercentagePointChange($current, $previous, $snapshot['previous_period_label'], 2),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Positive' => 'Bank rate is lower than it was last month.',
                'Neutral' => 'Bank rate is unchanged from last month.',
                'Warning' => 'Bank rate is rising.',
                default => 'Bank rate has risen for three consecutive months.',
            },
            meaning: 'The Bank rate influences mortgage pricing. Higher rates usually make it harder for buyers and remortgaging households to keep monthly payments comfortable.',
            sparkId: 'spark-interest',
            sparkKey: 'interest',
        );
    }

    private function buildInflationCard(array $snapshot): array
    {
        $current = $snapshot['current_value'];
        $previous = $snapshot['previous_value'];
        $status = $this->trendStatus($snapshot, badWhen: 'up', decimals: 1);

        return $this->baseCard(
            title: 'Inflation',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 1).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 1).'%',
            change: $this->formatPointChange($current, $previous, $snapshot['previous_period_label'], 1),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Positive' => 'Inflation fell in the latest release.',
                'Neutral' => 'Inflation was unchanged in the latest release.',
                'Warning' => 'Inflation is rising.',
                default => 'Inflation has risen for three consecutive releases.',
            },
            meaning: 'Lower inflation usually eases pressure on household budgets and can support the outlook for interest rates. Higher inflation can keep pressure on borrowing costs and living costs.',
            sparkId: 'spark-inflation',
            sparkKey: 'inflation',
        );
    }

    private function buildWagesCard(array $snapshot, array $realSnapshot): array
    {
        $current = $snapshot['current_value'];
        $previous = $snapshot['previous_value'];
        $realCurrent = $realSnapshot['current_value'];
        $status = $this->trendStatus($snapshot, badWhen: 'down', decimals: 2);

        return $this->baseCard(
            title: 'Wage growth',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 2).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 2).'%',
            change: $this->formatPointChange($current, $previous, $snapshot['previous_period_label'], 2),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Positive' => 'Wage growth increased in the latest release.',
                'Neutral' => 'Wage growth was unchanged in the latest release.',
                'Warning' => 'Wage growth is falling.',
                default => 'Wage growth has fallen for three consecutive releases.',
            },
            meaning: 'If wages are rising faster than inflation, buyers may find it easier to save or borrow. If not, affordability can stay tight even when rates stop rising.',
            sparkId: 'spark-wages',
            sparkKey: 'wages',
            supplementary: is_null($realCurrent) ? null : 'Real wage growth: '.number_format($realCurrent, 2).'%',
        );
    }

    private function buildUnemploymentCard(array $snapshot): array
    {
        $current = $snapshot['current_value'];
        $previous = $snapshot['previous_value'];
        $status = $this->trendStatus($snapshot, badWhen: 'up', decimals: 1);

        return $this->baseCard(
            title: 'Unemployment',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 1).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 1).'%',
            change: $this->formatPointChange($current, $previous, $snapshot['previous_period_label'], 1),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Positive' => 'Unemployment fell in the latest release.',
                'Neutral' => 'Unemployment was unchanged in the latest release.',
                'Warning' => 'Unemployment is rising.',
                default => 'Unemployment has risen for three consecutive releases.',
            },
            meaning: 'Low unemployment usually supports confidence and mortgage repayments. Rising unemployment can reduce demand and increase financial strain for some households.',
            sparkId: 'spark-unemployment',
            sparkKey: 'unemployment',
        );
    }

    private function buildArrearsCard(array $snapshot): array
    {
        $current = $snapshot['current_value'];
        $previous = $snapshot['previous_value'];
        $status = $this->trendStatus($snapshot, badWhen: 'up', decimals: 3);

        return $this->baseCard(
            title: 'Mortgage arrears',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 3).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 3).'%',
            change: $this->formatPointChange($current, $previous, $snapshot['previous_period_label'], 3),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Positive' => 'Mortgage arrears fell in the latest release.',
                'Neutral' => 'Mortgage arrears were unchanged in the latest release.',
                'Warning' => 'Mortgage arrears are rising.',
                default => 'Mortgage arrears have risen for three consecutive releases.',
            },
            meaning: 'Arrears can be an early sign of financial stress. A rising trend can matter even before repossessions move higher.',
            sparkId: 'spark-arrears',
            sparkKey: 'arrears',
        );
    }

    private function buildRepossessionsCard(array $snapshot): array
    {
        $current = $snapshot['current_value'];
        $previous = $snapshot['previous_value'];
        $status = $this->trendStatus($snapshot, badWhen: 'up', decimals: 3);

        return $this->baseCard(
            title: 'Repossessions',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 3).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 3).'%',
            change: $this->formatPointChange($current, $previous, $snapshot['previous_period_label'], 3),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Positive' => 'Repossessions fell in the latest release.',
                'Neutral' => 'Repossessions were unchanged in the latest release.',
                'Warning' => 'Repossessions are rising.',
                default => 'Repossessions have risen for three consecutive releases.',
            },
            meaning: 'Repossessions are still a very small share of mortgages, but the direction matters. A rising trend can point to pressure building after arrears have already increased.',
            sparkId: 'spark-repossessions',
            sparkKey: 'repossessions',
        );
    }

    private function baseCard(
        string $title,
        array $status,
        array $snapshot,
        string $currentValue,
        string $previousValue,
        string $change,
        string $changeArrow,
        string $signal,
        string $meaning,
        string $sparkId,
        string $sparkKey,
        ?string $supplementary = null,
    ): array {
        return [
            'title' => $title,
            'status' => $status,
            'current_heading' => $snapshot['current_label_heading'],
            'previous_heading' => $snapshot['previous_label_heading'],
            'current_label' => $snapshot['current_period_label'],
            'previous_label' => $snapshot['previous_period_label'],
            'change_heading' => $snapshot['change_heading'],
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'change' => $change,
            'change_arrow' => $changeArrow,
            'signal' => $signal,
            'meaning' => $meaning,
            'spark_id' => $sparkId,
            'spark_key' => $sparkKey,
            'supplementary' => $supplementary,
            'debug' => $snapshot['debug'],
        ];
    }

    private function buildSummary(array $cards): array
    {
        $positive = collect($cards)->where('status.label', 'Positive')->count();
        $neutral = collect($cards)->where('status.label', 'Neutral')->count();
        $warning = collect($cards)->where('status.label', 'Warning')->count();
        $stress = collect($cards)->where('status.label', 'Stress')->count();
        $pressureCount = $warning + $stress;
        $positiveWeight = $positive + ($neutral * 0.5);
        $pressureWeight = $warning + ($stress * 3);

        $tone = match (true) {
            $stress >= 2 || ($stress >= 1 && $warning >= 2) => 'showing significant pressure',
            $stress >= 1 || $pressureWeight >= 5 => 'under pressure',
            $positiveWeight >= $pressureWeight + 1.5 && $positive >= $pressureCount + 1 => 'broadly positive',
            default => 'mixed but broadly balanced',
        };

        $mainPressureSource = collect($cards)
            ->filter(fn (array $card): bool => in_array($card['status']['label'], ['Warning', 'Stress'], true))
            ->sortByDesc(fn (array $card) => $card['status']['weight'])
            ->map(fn (array $card) => strtolower($card['title']))
            ->first();

        return [
            'tone' => $tone,
            'main_pressure_source' => $mainPressureSource,
        ];
    }

    private function statusMeta(string $level): array
    {
        return match ($level) {
            'green' => [
                'label' => 'Positive',
                'weight' => 0,
                'card' => 'border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-white',
                'badge' => 'border border-emerald-200 bg-emerald-100 text-emerald-800',
                'accent' => 'bg-emerald-500',
                'change' => 'text-emerald-700',
            ],
            'amber' => [
                'label' => 'Neutral',
                'weight' => 1,
                'card' => 'border-amber-200 bg-gradient-to-br from-amber-50 via-white to-white',
                'badge' => 'border border-amber-200 bg-amber-100 text-amber-900',
                'accent' => 'bg-amber-500',
                'change' => 'text-amber-800',
            ],
            'red' => [
                'label' => 'Warning',
                'weight' => 2,
                'card' => 'border-rose-200 bg-gradient-to-br from-rose-50 via-white to-white',
                'badge' => 'border border-rose-200 bg-rose-100 text-rose-800',
                'accent' => 'bg-rose-500',
                'change' => 'text-rose-700',
            ],
            default => [
                'label' => 'Stress',
                'weight' => 3,
                'card' => 'border-rose-400 bg-gradient-to-br from-rose-100 via-rose-50 to-white',
                'badge' => 'border border-rose-300 bg-rose-200 text-rose-900',
                'accent' => 'bg-rose-700',
                'change' => 'text-rose-800',
            ],
        };
    }

    private function trendStatus(array $snapshot, string $badWhen, int $decimals): array
    {
        $values = array_map(
            fn (float $value): float => round($value, $decimals),
            $snapshot['trend_values'] ?? []
        );

        if (count($values) < 2) {
            return $this->statusMeta('amber');
        }

        $latest = $values[count($values) - 1];
        $previous = $values[count($values) - 2];

        if ($latest === $previous) {
            return $this->statusMeta('amber');
        }

        $latestIsWorse = $badWhen === 'up' ? $latest > $previous : $latest < $previous;

        if (! $latestIsWorse) {
            return $this->statusMeta('green');
        }

        $consecutiveWorseningReleases = 1;

        for ($index = count($values) - 2; $index > 0; $index--) {
            $current = $values[$index];
            $prior = $values[$index - 1];
            $isWorse = $badWhen === 'up' ? $current > $prior : $current < $prior;

            if (! $isWorse) {
                break;
            }

            $consecutiveWorseningReleases++;
        }

        return match (true) {
            $consecutiveWorseningReleases >= 3 => $this->statusMeta('stress'),
            default => $this->statusMeta('red'),
        };
    }

    private function quarterKeyFromParts(int $year, string $quarter): string
    {
        return sprintf('%s-%s', $year, strtoupper($quarter));
    }

    private function formatQuarterLabel(string $quarterKey): string
    {
        [$year, $quarter] = explode('-', $quarterKey);

        return $quarter.' '.$year;
    }

    private function formatQuarterDisplayLabel(string $quarterKey): string
    {
        [$year, $quarter] = explode('-', $quarterKey);

        return $year.' '.$quarter;
    }

    private function quarterKeyToDate(string $quarterKey): Carbon
    {
        [$year, $quarter] = explode('-', $quarterKey);
        $quarterNumber = (int) str_replace('Q', '', $quarter);
        $month = (($quarterNumber - 1) * 3) + 1;

        return Carbon::create((int) $year, $month, 1)->startOfMonth();
    }

    private function changeArrow(?float $current, ?float $previous): string
    {
        if (is_null($current) || is_null($previous) || $current === $previous) {
            return '•';
        }

        return $current > $previous ? '↑' : '↓';
    }

    private function formatPercentChange(?float $current, ?float $previous, string $previousLabel): string
    {
        if (is_null($current) || is_null($previous) || $previous == 0.0) {
            return 'Previous period comparison not available';
        }

        if ($current === $previous) {
            return 'Unchanged vs '.$previousLabel;
        }

        $direction = $current > $previous ? '+' : '-';

        return $direction.number_format(abs((($current - $previous) / $previous) * 100), 1).'% vs '.$previousLabel;
    }

    private function formatPointChange(?float $current, ?float $previous, string $previousLabel, int $decimals): string
    {
        if (is_null($current) || is_null($previous)) {
            return 'Previous period comparison not available';
        }

        if ($current === $previous) {
            return 'Unchanged vs '.$previousLabel;
        }

        $direction = $current > $previous ? '+' : '-';

        return $direction.number_format(abs($current - $previous), $decimals).' pts vs '.$previousLabel;
    }

    private function formatPercentagePointChange(?float $current, ?float $previous, string $previousLabel, int $decimals): string
    {
        if (is_null($current) || is_null($previous)) {
            return 'Previous rate comparison not available';
        }

        if ($current === $previous) {
            return 'Unchanged vs '.$previousLabel;
        }

        $direction = $current > $previous ? '+' : '-';

        return $direction.number_format(abs($current - $previous), $decimals).' percentage points vs '.$previousLabel;
    }

    private function formatCurrencyChange(?float $current, ?float $previous, string $previousLabel): string
    {
        if (is_null($current) || is_null($previous)) {
            return 'Previous period comparison not available';
        }

        if ($current === $previous) {
            return 'Unchanged vs '.$previousLabel;
        }

        $direction = $current > $previous ? '+' : '-';
        $text = $direction.'£'.number_format(abs($current - $previous), 0).' vs '.$previousLabel;

        if ($previous != 0.0) {
            $text .= ' ('.number_format(abs((($current - $previous) / $previous) * 100), 1).'%)';
        }

        return $text;
    }

    private function parseHpiMonthlyDate(string $date): ?Carbon
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches) === 1) {
            $middlePart = $matches[2];
            $lastPart = (int) $matches[3];

            if ($middlePart === '01' && $lastPart >= 1 && $lastPart <= 12) {
                try {
                    return Carbon::createFromFormat('Y-d-m', $date);
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
