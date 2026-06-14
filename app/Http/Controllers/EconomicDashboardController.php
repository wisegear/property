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

        $interestSeries = $this->buildMonthlySeries(
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

        $interestSnapshot = $this->buildRollingThreeMonthSnapshot($interestSeries, 'avg');
        $inflationSnapshot = $this->buildRollingThreeMonthSnapshot($inflationSeries, 'avg');
        $wagesSnapshot = $this->buildRollingThreeMonthSnapshot($wagesSeries, 'avg');
        $realWagesSnapshot = $this->buildRollingThreeMonthSnapshot($realWagesSeries, 'avg');
        $unemploymentSnapshot = $this->buildRollingThreeMonthSnapshot($unemploymentSeries, 'avg');
        $approvalsSnapshot = $this->buildRollingThreeMonthSnapshot($approvalsSeries, 'sum');
        $hpiSnapshot = $this->buildRollingThreeMonthSnapshot($hpiSeries, 'avg');
        $repossessionsSnapshot = $this->buildQuarterSnapshot($repossessionsSeries);
        $arrearsSnapshot = $this->buildQuarterSnapshot($arrearsSeries);

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

        return view('economic.dashboard', [
            'cards' => $cards,
            'sparklines' => $sparklines,
            'summary' => $summary,
            'statusCounts' => [
                'Supportive' => $statusCounts->get('Supportive', 0),
                'Neutral' => $statusCounts->get('Neutral', 0),
                'Warning' => $statusCounts->get('Warning', 0),
                'Stress' => $statusCounts->get('Stress', 0),
            ],
            'heroComparisonText' => 'Each panel compares the latest available 3-month or quarterly period with the previous period, so the signals stay current while avoiding monthly noise.',
            'totalStress' => $totalStress,
            'approvals' => $approvalsSnapshot['current_value'] !== null ? (object) [
                'period' => $approvalsSnapshot['current_period_label'],
                'value' => $approvalsSnapshot['current_value'],
            ] : null,
            'repossDirection' => $cards[7]['status']['weight'],
            'hpiDateLabel' => $hpiSnapshot['current_period_label'],
        ]);
    }

    private function buildMonthlySeries(Collection $rows, callable $dateResolver, callable $valueResolver): array
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

        usort($points, fn (array $left, array $right) => $left['date']->lessThan($right['date']) ? -1 : 1);

        return [
            'type' => 'monthly',
            'points' => $points,
            'keys' => array_column($points, 'key'),
            'labels' => array_column($points, 'label'),
            'values' => array_column($points, 'value'),
        ];
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

        usort($points, fn (array $left, array $right) => $left['date']->lessThan($right['date']) ? -1 : 1);

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

    private function buildRollingThreeMonthSnapshot(array $series, string $mode): array
    {
        $points = $series['points'] ?? [];
        $count = count($points);

        if ($count < 1) {
            return $this->emptySnapshot('Current 3-month period', 'Previous 3-month period');
        }

        $currentPoints = array_slice($points, -min(3, $count));
        $previousPoints = $count >= 6 ? array_slice($points, -6, 3) : [];

        return [
            'current_value' => $this->aggregatePoints($currentPoints, $mode),
            'previous_value' => count($previousPoints) === 3 ? $this->aggregatePoints($previousPoints, $mode) : null,
            'current_label_heading' => 'Current 3-month period',
            'previous_label_heading' => 'Previous 3-month period',
            'current_period_label' => $this->formatMonthRangeLabel($currentPoints),
            'previous_period_label' => count($previousPoints) === 3 ? $this->formatMonthRangeLabel($previousPoints) : 'Not available',
            'change_heading' => '3-month change',
        ];
    }

    private function buildQuarterSnapshot(array $series): array
    {
        $points = $series['points'] ?? [];
        $count = count($points);

        if ($count < 1) {
            return $this->emptySnapshot('Current quarter', 'Previous quarter');
        }

        $currentPoint = $points[$count - 1];
        $previousPoint = $count >= 2 ? $points[$count - 2] : null;

        return [
            'current_value' => $currentPoint['value'],
            'previous_value' => $previousPoint['value'] ?? null,
            'current_label_heading' => 'Current quarter',
            'previous_label_heading' => 'Previous quarter',
            'current_period_label' => $this->formatQuarterDisplayLabel($currentPoint['key']),
            'previous_period_label' => $previousPoint ? $this->formatQuarterDisplayLabel($previousPoint['key']) : 'Not available',
            'change_heading' => 'Quarterly change',
        ];
    }

    private function emptySnapshot(string $currentHeading, string $previousHeading): array
    {
        return [
            'current_value' => null,
            'previous_value' => null,
            'current_label_heading' => $currentHeading,
            'previous_label_heading' => $previousHeading,
            'current_period_label' => 'Not available',
            'previous_period_label' => 'Not available',
            'change_heading' => $currentHeading === 'Current quarter' ? 'Quarterly change' : '3-month change',
        ];
    }

    private function aggregatePoints(array $points, string $mode): float
    {
        $values = array_column($points, 'value');

        return round(match ($mode) {
            'sum' => array_sum($values),
            'last' => end($values),
            default => array_sum($values) / count($values),
        }, 3);
    }

    private function formatMonthRangeLabel(array $points): string
    {
        $start = $points[0]['date'];
        $end = $points[count($points) - 1]['date'];

        return $start->format('M Y').' - '.$end->format('M Y');
    }

    private function buildApprovalsCard(array $snapshot): array
    {
        $current = $snapshot['current_value'];
        $previous = $snapshot['previous_value'];
        $isFalling = ! is_null($current) && ! is_null($previous) && $current < $previous;

        $status = match (true) {
            is_null($current) => $this->statusMeta('amber'),
            $current >= 190000 && ! $isFalling => $this->statusMeta('green'),
            $current >= 160000 && ! $isFalling => $this->statusMeta('amber'),
            $current >= 160000 => $this->statusMeta('red'),
            default => $this->statusMeta('deep'),
        };

        return $this->baseCard(
            title: 'Mortgage approvals',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current).' approvals',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous).' approvals',
            change: $this->formatPercentChange($current, $previous, $snapshot['previous_period_label']),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Supportive' => 'Buyer demand is improving.',
                'Neutral' => 'Buyer demand looks broadly steady.',
                'Warning' => 'Buyer demand is softening.',
                default => 'Buyer demand is under clear pressure.',
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
        $delta = is_null($current) || is_null($previous) ? null : $current - $previous;

        $status = match (true) {
            is_null($current) => $this->statusMeta('amber'),
            ! is_null($delta) && $delta >= 3000 => $this->statusMeta('green'),
            ! is_null($delta) && $delta >= 0 => $this->statusMeta('amber'),
            ! is_null($delta) && $delta > -5000 => $this->statusMeta('red'),
            default => $this->statusMeta('deep'),
        };

        return $this->baseCard(
            title: 'House prices (UK average)',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : '£'.number_format($current, 0),
            previousValue: is_null($previous) ? 'Not available' : '£'.number_format($previous, 0),
            change: $this->formatCurrencyChange($current, $previous, $snapshot['previous_period_label']),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Supportive' => 'Prices are holding up well.',
                'Neutral' => 'Prices look broadly stable.',
                'Warning' => 'Price growth is losing momentum.',
                default => 'Prices are under more noticeable pressure.',
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
        $delta = is_null($current) || is_null($previous) ? null : $current - $previous;

        $status = match (true) {
            is_null($current) => $this->statusMeta('amber'),
            $current <= 3.0 && (! is_null($delta) ? $delta <= 0 : true) => $this->statusMeta('green'),
            $current <= 4.5 && (! is_null($delta) ? $delta <= 0.25 : true) => $this->statusMeta('amber'),
            $current <= 5.0 => $this->statusMeta('red'),
            default => $this->statusMeta('deep'),
        };

        return $this->baseCard(
            title: 'Bank rate',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 2).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 2).'%',
            change: $this->formatPointChange($current, $previous, $snapshot['previous_period_label'], 2),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Supportive' => 'Borrowing costs look relatively supportive.',
                'Neutral' => 'Borrowing costs are elevated but steady.',
                'Warning' => 'Borrowing costs are still restrictive.',
                default => 'Borrowing costs are putting strong pressure on affordability.',
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

        $status = match (true) {
            is_null($current) => $this->statusMeta('amber'),
            $current < 2.5 && (! is_null($previous) ? $current <= $previous : true) => $this->statusMeta('green'),
            $current < 3.5 => $this->statusMeta('amber'),
            $current < 5.0 => $this->statusMeta('red'),
            default => $this->statusMeta('deep'),
        };

        return $this->baseCard(
            title: 'Inflation',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 1).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 1).'%',
            change: $this->formatPointChange($current, $previous, $snapshot['previous_period_label'], 1),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Supportive' => 'Price pressures are easing.',
                'Neutral' => 'Inflation looks more contained.',
                'Warning' => 'Everyday cost pressure is still noticeable.',
                default => 'Inflation is still putting clear pressure on household budgets.',
            },
            meaning: 'High inflation leaves households with less room for deposits, bills, and mortgage payments. Lower inflation can gradually make affordability easier.',
            sparkId: 'spark-inflation',
            sparkKey: 'inflation',
        );
    }

    private function buildWagesCard(array $snapshot, array $realSnapshot): array
    {
        $current = $snapshot['current_value'];
        $previous = $snapshot['previous_value'];
        $realCurrent = $realSnapshot['current_value'];

        $status = match (true) {
            is_null($realCurrent) => $this->statusMeta('amber'),
            $realCurrent >= 1.0 => $this->statusMeta('green'),
            $realCurrent >= 0.0 => $this->statusMeta('amber'),
            $realCurrent >= -1.0 => $this->statusMeta('red'),
            default => $this->statusMeta('deep'),
        };

        return $this->baseCard(
            title: 'Wage growth',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 2).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 2).'%',
            change: $this->formatPointChange($current, $previous, $snapshot['previous_period_label'], 2),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Supportive' => 'Pay growth is keeping up with living costs.',
                'Neutral' => 'Pay growth is helping, but only modestly.',
                'Warning' => 'Pay growth is not fully offsetting cost pressure.',
                default => 'Household incomes are struggling to keep up with prices.',
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

        $status = match (true) {
            is_null($current) => $this->statusMeta('amber'),
            $current < 4.5 && (! is_null($previous) ? $current <= $previous : true) => $this->statusMeta('green'),
            $current < 5.0 => $this->statusMeta('amber'),
            $current < 6.0 => $this->statusMeta('red'),
            default => $this->statusMeta('deep'),
        };

        return $this->baseCard(
            title: 'Unemployment',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 1).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 1).'%',
            change: $this->formatPointChange($current, $previous, $snapshot['previous_period_label'], 1),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Supportive' => 'The jobs market still looks supportive.',
                'Neutral' => 'The jobs market looks broadly steady.',
                'Warning' => 'The jobs market is showing signs of weakening.',
                default => 'The jobs market is adding clear pressure to housing demand.',
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

        $status = match (true) {
            is_null($current) => $this->statusMeta('amber'),
            $current < 0.8 && (! is_null($previous) ? $current <= $previous : true) => $this->statusMeta('green'),
            $current < 1.2 => $this->statusMeta('amber'),
            $current < 1.8 => $this->statusMeta('red'),
            default => $this->statusMeta('deep'),
        };

        return $this->baseCard(
            title: 'Mortgage arrears',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 3).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 3).'%',
            change: $this->formatPointChange($current, $previous, $snapshot['previous_period_label'], 3),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Supportive' => 'Borrowers look broadly on top of repayments.',
                'Neutral' => 'Repayment pressure looks contained.',
                'Warning' => 'More borrowers are starting to fall behind.',
                default => 'Repayment pressure is becoming more noticeable.',
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
        $isRising = ! is_null($current) && ! is_null($previous) && $current > $previous;

        $status = match (true) {
            is_null($current) => $this->statusMeta('amber'),
            $current < 0.08 && ! $isRising => $this->statusMeta('green'),
            $current < 0.08 => $this->statusMeta('amber'),
            $current < 0.15 => $this->statusMeta('red'),
            default => $this->statusMeta('deep'),
        };

        return $this->baseCard(
            title: 'Repossessions',
            status: $status,
            snapshot: $snapshot,
            currentValue: is_null($current) ? 'No data' : number_format($current, 3).'%',
            previousValue: is_null($previous) ? 'Not available' : number_format($previous, 3).'%',
            change: $this->formatPointChange($current, $previous, $snapshot['previous_period_label'], 3),
            changeArrow: $this->changeArrow($current, $previous),
            signal: match ($status['label']) {
                'Supportive' => 'Forced-sale pressure remains low.',
                'Neutral' => 'Forced-sale pressure looks contained.',
                'Warning' => 'Repossessions are rising, but from a low base.',
                default => 'Repossessions are rising and becoming more noticeable.',
            },
            meaning: 'Repossessions remain a very small share of mortgages, but the trend is worth monitoring.',
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
        ];
    }

    private function buildSummary(array $cards): array
    {
        $supportive = collect($cards)->where('status.label', 'Supportive')->count();
        $neutral = collect($cards)->where('status.label', 'Neutral')->count();
        $warning = collect($cards)->where('status.label', 'Warning')->count();
        $stress = collect($cards)->where('status.label', 'Stress')->count();
        $pressureCount = $warning + $stress;

        $tone = match (true) {
            $stress >= 2 => 'significant pressure',
            $pressureCount >= 3 => 'under pressure',
            $supportive + $neutral >= 6 && $pressureCount <= 2 && $supportive >= $neutral => 'broadly supportive',
            default => 'balanced',
        };

        $mainPressureSource = collect($cards)
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
                'label' => 'Supportive',
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
