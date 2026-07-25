<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EconomicDashboardController;
use Illuminate\Http\JsonResponse;

class StressInsightsController extends Controller
{
    public function __invoke(EconomicDashboardController $dashboardController): JsonResponse
    {
        $dashboard = $dashboardController->dashboardData();
        $rawScore = (int) $dashboard['totalStress'];
        $maximumRawScore = 31;
        $score = (int) round(($rawScore / $maximumRawScore) * 100);

        $keys = [
            'mortgage_approvals',
            'house_price_index',
            'interest_rates',
            'inflation',
            'wage_growth',
            'unemployment',
            'mortgage_arrears',
            'repossessions',
        ];
        $snapshotKeys = [
            'approvals',
            'hpi',
            'interest',
            'inflation',
            'wages',
            'unemployment',
            'arrears',
            'repossessions',
        ];
        $units = ['approvals', 'GBP', 'percent', 'percent', 'percent', 'percent', 'percent', 'percent'];
        $websiteRoutes = [
            'mortgages.home',
            'hpi.home',
            'interest.home',
            'inflation.home',
            'wagegrowth.home',
            'unemployment.home',
            'arrears.index',
            'repossessions.index',
        ];

        $indicators = collect($dashboard['cards'])
            ->values()
            ->map(function (array $card, int $index) use ($dashboard, $keys, $snapshotKeys, $units, $websiteRoutes): array {
                $snapshot = $dashboard['snapshots'][$snapshotKeys[$index]];
                $status = $this->statusValue((int) $card['status']['weight']);

                return [
                    'key' => $keys[$index],
                    'title' => $card['title'],
                    'description' => $card['meaning'],
                    'value' => $snapshot['current_value'],
                    'secondary_value' => $keys[$index] === 'wage_growth'
                        ? $dashboard['snapshots']['real_wages']['current_value']
                        : null,
                    'unit' => $units[$index],
                    'period' => $snapshot['current_value'] === null ? null : $snapshot['current_period_label'],
                    'bad_streak' => $this->badStreak($dashboard['sparklines'][$snapshotKeys[$index]]['values'] ?? [], $keys[$index]),
                    'status' => $status,
                    'status_label' => $card['status']['label'],
                    'score' => (int) $card['status']['weight'],
                    'maximum_score' => 3,
                    'api_url' => null,
                    'website_url' => route($websiteRoutes[$index]),
                ];
            });

        $status = match (true) {
            $score >= 70 => 'dark_red',
            $score >= 40 => 'amber',
            default => 'low',
        };

        return response()->json(['data' => [
            'title' => 'PropertyResearch Stress Indicators Dashboard',
            'description' => 'Eight indicators combining housing demand, prices, borrowing costs, household finances and forced-sale pressure.',
            'last_updated' => $dashboard['lastUpdated'],
            'score' => [
                'value' => $score,
                'maximum' => 100,
                'raw_value' => $rawScore,
                'raw_maximum' => $maximumRawScore,
                'status' => $status,
                'status_label' => match ($status) {
                    'dark_red' => 'High stress',
                    'amber' => 'Elevated risk',
                    default => 'Low stress',
                },
                'description' => 'A single score combining all eight indicators. Higher scores mean more stress and risk.',
            ],
            'indicators' => $indicators,
            'website_url' => route('economic.dashboard'),
        ]]);
    }

    /**
     * @param  array<int, float|int>  $values
     */
    private function badStreak(array $values, string $key): int
    {
        if (count($values) < 2) {
            return 0;
        }

        $lowerIsBad = in_array($key, ['mortgage_approvals', 'house_price_index', 'wage_growth'], true);
        $streak = 0;

        for ($index = count($values) - 1; $index > 0; $index--) {
            $isBad = $lowerIsBad
                ? $values[$index] < $values[$index - 1]
                : $values[$index] > $values[$index - 1];

            if (! $isBad) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    private function statusValue(int $weight): string
    {
        return match ($weight) {
            0 => 'low',
            1 => 'amber',
            2 => 'red',
            default => 'dark_red',
        };
    }
}
