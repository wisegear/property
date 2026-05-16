<?php

namespace App\Services;

class MortgageCalculatorService
{
    /**
     * @return array{
     *     amount:int,
     *     term_years:int,
     *     rate_pct:float,
     *     annual_overpayment:int,
     *     repayment_monthly:float,
     *     repayment_annual:float,
     *     repayment_total_paid:float,
     *     repayment_total_interest:float,
     *     repayment_per_pound:float,
     *     repayment_chart:array{
     *         standard_points:array<int, array{x:float, y:float}>,
     *         overpayment_points:array<int, array{x:float, y:float}>|null
     *     },
     *     interest_only_monthly:float,
     *     interest_only_annual:float,
     *     interest_only_total_interest:float,
     *     interest_only_per_pound:float,
     *     interest_only_chart:array{
     *         standard_points:array<int, array{x:float, y:float}>,
     *         overpayment_points:array<int, array{x:float, y:float}>|null
     *     },
     *     overpayment_impact:?array{
     *         annual_overpayment:int,
     *         repayment:array{
     *             new_term_months:int,
     *             months_saved:int,
     *             total_interest:float,
     *             interest_saved:float,
     *             new_term_label:string,
     *             time_saved_label:string
     *         },
     *         interest_only:array{
     *             new_term_months:int,
     *             months_saved:int,
     *             total_interest:float,
     *             interest_saved:float,
     *             new_term_label:string,
     *             time_saved_label:string
     *         }
     *     },
     *     stress_rate:float,
     *     stressed_rate_pct:float,
     *     repayment_monthly_stressed:float,
     *     repayment_monthly_extra:float,
     *     interest_only_monthly_stressed:float,
     *     interest_only_monthly_extra:float
     * }
     */
    public function calculate(int $amount, int $termYears, float $ratePct, int $annualOverpayment = 0): array
    {
        $termMonths = $termYears * 12;
        $monthlyRate = $ratePct / 100 / 12;

        $repaymentMonthly = $monthlyRate === 0.0
            ? $amount / max($termMonths, 1)
            : ($amount * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$termMonths));

        $standardRepayment = $this->buildRepaymentSchedule($amount, $monthlyRate, $repaymentMonthly, $termMonths);

        $interestOnlyMonthly = ($amount * ($ratePct / 100)) / 12;
        $interestOnlyTotalInterest = $interestOnlyMonthly * $termMonths;

        $stressRate = 3.0;
        $stressedRatePct = $ratePct + $stressRate;
        $stressedMonthlyRate = $stressedRatePct / 100 / 12;
        $repaymentMonthlyStressed = $stressedMonthlyRate === 0.0
            ? $amount / max($termMonths, 1)
            : ($amount * $stressedMonthlyRate) / (1 - pow(1 + $stressedMonthlyRate, -$termMonths));
        $interestOnlyMonthlyStressed = ($amount * ($stressedRatePct / 100)) / 12;

        $overpaymentImpact = null;
        $overpaymentPoints = null;
        $interestOnlyOverpaymentPoints = null;

        if ($annualOverpayment > 0) {
            $overpaymentSchedule = $this->buildRepaymentSchedule(
                $amount,
                $monthlyRate,
                $repaymentMonthly,
                $termMonths,
                $annualOverpayment
            );
            $interestOnlyOverpaymentSchedule = $this->buildInterestOnlyOverpaymentSchedule(
                $amount,
                $monthlyRate,
                $termMonths,
                $annualOverpayment
            );

            $overpaymentImpact = [
                'annual_overpayment' => $annualOverpayment,
                'repayment' => [
                    'new_term_months' => $overpaymentSchedule['months'],
                    'months_saved' => max(0, $standardRepayment['months'] - $overpaymentSchedule['months']),
                    'total_interest' => $overpaymentSchedule['total_interest'],
                    'interest_saved' => max(0, $standardRepayment['total_interest'] - $overpaymentSchedule['total_interest']),
                    'new_term_label' => $this->formatMonths($overpaymentSchedule['months']),
                    'time_saved_label' => $this->formatMonths(max(0, $standardRepayment['months'] - $overpaymentSchedule['months'])),
                ],
                'interest_only' => [
                    'new_term_months' => $interestOnlyOverpaymentSchedule['months'],
                    'months_saved' => max(0, $termMonths - $interestOnlyOverpaymentSchedule['months']),
                    'total_interest' => $interestOnlyOverpaymentSchedule['total_interest'],
                    'interest_saved' => max(0, $interestOnlyTotalInterest - $interestOnlyOverpaymentSchedule['total_interest']),
                    'new_term_label' => $this->formatMonths($interestOnlyOverpaymentSchedule['months']),
                    'time_saved_label' => $this->formatMonths(max(0, $termMonths - $interestOnlyOverpaymentSchedule['months'])),
                ],
            ];
            $overpaymentPoints = $overpaymentSchedule['chart_points'];
            $interestOnlyOverpaymentPoints = $this->buildInterestOnlyChart($amount, $termYears, $annualOverpayment);
        }

        return [
            'amount' => $amount,
            'term_years' => $termYears,
            'rate_pct' => $ratePct,
            'annual_overpayment' => $annualOverpayment,
            'repayment_monthly' => $repaymentMonthly,
            'repayment_annual' => $repaymentMonthly * 12,
            'repayment_total_paid' => $standardRepayment['total_paid'],
            'repayment_total_interest' => $standardRepayment['total_interest'],
            'repayment_per_pound' => $amount > 0 ? $standardRepayment['total_paid'] / $amount : 0.0,
            'repayment_chart' => [
                'standard_points' => $standardRepayment['chart_points'],
                'overpayment_points' => $overpaymentPoints,
            ],
            'interest_only_monthly' => $interestOnlyMonthly,
            'interest_only_annual' => $interestOnlyMonthly * 12,
            'interest_only_total_interest' => $interestOnlyTotalInterest,
            'interest_only_per_pound' => $amount > 0 ? $interestOnlyTotalInterest / $amount : 0.0,
            'interest_only_chart' => [
                'standard_points' => $this->buildInterestOnlyChart($amount, $termYears),
                'overpayment_points' => $interestOnlyOverpaymentPoints,
            ],
            'overpayment_impact' => $overpaymentImpact,
            'stress_rate' => $stressRate,
            'stressed_rate_pct' => $stressedRatePct,
            'repayment_monthly_stressed' => $repaymentMonthlyStressed,
            'repayment_monthly_extra' => max(0, $repaymentMonthlyStressed - $repaymentMonthly),
            'interest_only_monthly_stressed' => $interestOnlyMonthlyStressed,
            'interest_only_monthly_extra' => max(0, $interestOnlyMonthlyStressed - $interestOnlyMonthly),
        ];
    }

    /**
     * @return array{
     *     months:int,
     *     total_interest:float,
     *     total_paid:float,
     *     chart_points:array<int, array{x:float, y:float}>
     * }
     */
    private function buildRepaymentSchedule(
        int $amount,
        float $monthlyRate,
        float $scheduledMonthlyPayment,
        int $scheduledMonths,
        int $annualOverpayment = 0
    ): array {
        $balance = (float) $amount;
        $month = 0;
        $totalInterest = 0.0;
        $totalPaid = 0.0;
        $chartPoints = [
            ['x' => 0.0, 'y' => round($balance, 2)],
        ];

        while ($balance > 0.0 && $month < $scheduledMonths) {
            $month++;

            $interest = $monthlyRate === 0.0 ? 0.0 : $balance * $monthlyRate;
            $actualMonthlyPayment = min($scheduledMonthlyPayment, $balance + $interest);
            $principal = max(0.0, $actualMonthlyPayment - $interest);

            $balance = max(0.0, $balance - $principal);
            $totalInterest += $interest;
            $totalPaid += $actualMonthlyPayment;

            if ($annualOverpayment > 0 && $balance > 0.0 && $month % 12 === 0) {
                $appliedOverpayment = min((float) $annualOverpayment, $balance);
                $balance -= $appliedOverpayment;
                $totalPaid += $appliedOverpayment;
            }

            if ($month % 12 === 0) {
                $chartPoints[] = ['x' => round($month / 12, 2), 'y' => round($balance, 2)];
            }

            if ($balance <= 0.0) {
                $payoffYears = round($month / 12, 2);
                $lastPoint = end($chartPoints);
                if (! is_array($lastPoint) || $lastPoint['x'] !== $payoffYears) {
                    $chartPoints[] = ['x' => $payoffYears, 'y' => 0.0];
                } else {
                    $chartPoints[array_key_last($chartPoints)] = ['x' => $payoffYears, 'y' => 0.0];
                }
                break;
            }
        }

        return [
            'months' => $month,
            'total_interest' => round($totalInterest, 2),
            'total_paid' => round($totalPaid, 2),
            'chart_points' => $chartPoints,
        ];
    }

    /**
     * @return array<int, array{x:float, y:float}>
     */
    private function buildInterestOnlyChart(int $amount, int $termYears, int $annualOverpayment = 0): array
    {
        $points = [
            ['x' => 0.0, 'y' => (float) $amount],
        ];

        $balance = (float) $amount;

        for ($year = 1; $year <= $termYears; $year++) {
            if ($annualOverpayment > 0 && $balance > 0.0) {
                $balance = max(0.0, $balance - $annualOverpayment);
            }

            $points[] = ['x' => (float) $year, 'y' => round($balance, 2)];

            if ($balance <= 0.0) {
                break;
            }
        }

        return $points;
    }

    /**
     * @return array{
     *     months:int,
     *     total_interest:float
     * }
     */
    private function buildInterestOnlyOverpaymentSchedule(
        int $amount,
        float $monthlyRate,
        int $scheduledMonths,
        int $annualOverpayment
    ): array {
        $balance = (float) $amount;
        $month = 0;
        $totalInterest = 0.0;

        while ($balance > 0.0 && $month < $scheduledMonths) {
            $month++;

            $interest = $monthlyRate === 0.0 ? 0.0 : $balance * $monthlyRate;
            $totalInterest += $interest;

            if ($month % 12 === 0) {
                $balance = max(0.0, $balance - $annualOverpayment);
            }
        }

        return [
            'months' => $month,
            'total_interest' => round($totalInterest, 2),
        ];
    }

    private function formatMonths(int $months): string
    {
        $years = intdiv($months, 12);
        $remainingMonths = $months % 12;

        if ($years === 0) {
            return $remainingMonths === 1 ? '1 month' : "{$remainingMonths} months";
        }

        if ($remainingMonths === 0) {
            return $years === 1 ? '1 year' : "{$years} years";
        }

        $yearLabel = $years === 1 ? '1 year' : "{$years} years";
        $monthLabel = $remainingMonths === 1 ? '1 month' : "{$remainingMonths} months";

        return "{$yearLabel}, {$monthLabel}";
    }
}
