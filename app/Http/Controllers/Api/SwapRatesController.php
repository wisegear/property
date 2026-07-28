<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SwapRateController;
use Illuminate\Http\JsonResponse;

class SwapRatesController extends Controller
{
    public function __invoke(SwapRateController $swapRateController): JsonResponse
    {
        $dashboard = $swapRateController->dashboardData();

        $rates = collect($dashboard['termSnapshots'])
            ->map(function (array $snapshot, int $termYears) use ($dashboard): array {
                return [
                    ...$snapshot,
                    'latest_rate_date' => $snapshot['latest_rate_date']?->toDateString(),
                    'previous_rate_date' => $snapshot['previous_rate_date']?->toDateString(),
                    'range_52_week' => $dashboard['rateRanges'][$termYears] ?? [
                        'low' => null,
                        'high' => null,
                    ],
                ];
            })
            ->values()
            ->all();

        $currentRates = collect($dashboard['currentRatesTable'])
            ->map(fn (array $rate): array => [
                ...$rate,
                'rate_date' => $rate['rate_date']?->toDateString(),
            ])
            ->all();

        return response()->json(['data' => [
            'title' => 'UK Swap Rates Today',
            'description' => 'Track the latest UK 2-year, 5-year and 10-year SONIA swap rates and see what recent wholesale moves may mean for fixed mortgage pricing.',
            'source_note' => 'Data uses the Bank of England Overnight Index Swap curve, based on SONIA. Longer-term OIS data, including 10 year rates, is only available from late 2021 onwards.',
            'latest_available_date' => $dashboard['latestAvailableDate']?->toDateString(),
            'mortgage_market_summary' => $dashboard['mortgageMarketSummary'],
            'latest_movement_summary' => $dashboard['latestMovementSummary'],
            'rates' => $rates,
            'rate_chart' => $dashboard['rateChart'],
            'bank_rate_comparison_chart' => $dashboard['bankRateComparisonChart'],
            'current_rates' => $currentRates,
            'mortgage_context' => [
                'title' => 'What this means for mortgages',
                'paragraphs' => [
                    'Swap rates are wholesale market rates lenders watch when pricing fixed mortgages.',
                    'When swap rates fall, pressure on fixed mortgage pricing can ease, which may give some lenders room to trim rates.',
                    'When swap rates rise, mortgage price cuts can become less likely and some lenders may face more pressure to reprice higher.',
                    'Mortgage rates do not move perfectly with swaps. Lenders also consider margins, funding costs, competition, service levels and risk appetite before changing deals.',
                ],
            ],
            'understanding_swaps' => [
                'title' => 'Why swap rates matter before Bank Rate moves',
                'paragraphs' => [
                    'The Bank of England Bank Rate has the clearest direct effect on tracker mortgages and standard variable rates.',
                    'Fixed mortgage pricing reacts more quickly to changes in market expectations, and swap rates are one of the clearest signals of that shift.',
                    'That is why fixed deals can move even when Bank Rate is unchanged. Markets can price in future rate expectations before the next MPC decision arrives.',
                ],
            ],
            'faq' => [
                [
                    'question' => 'What are swap rates?',
                    'answer' => 'Swap rates are wholesale market interest rates that help lenders judge the cost of offering fixed-rate lending over different time periods.',
                ],
                [
                    'question' => 'Why do swap rates matter for mortgages?',
                    'answer' => 'They are one of the main market inputs behind fixed mortgage pricing, so sustained moves in swaps can influence whether lenders cut, hold or raise deals.',
                ],
                [
                    'question' => 'Do mortgage rates change immediately when swap rates move?',
                    'answer' => 'Not always. Lenders also consider margins, competition, funding costs and risk before changing mortgage pricing.',
                ],
                [
                    'question' => 'What is the difference between Bank Rate and swap rates?',
                    'answer' => 'Bank Rate is the official rate set by the Bank of England. Swap rates reflect market expectations for future rates and tend to matter more for fixed mortgages.',
                ],
            ],
            'update_note' => 'Updated on UK business days when new Bank of England data is available. Weekends and market gaps use the latest available records.',
            'website_url' => route('insights.swap-rates'),
        ]]);
    }
}
