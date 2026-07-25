<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EpcDashboardRequest;
use App\Services\EpcDashboardData;
use Illuminate\Http\JsonResponse;

class EpcDashboardController extends Controller
{
    public function __invoke(EpcDashboardRequest $request, EpcDashboardData $dashboardData): JsonResponse
    {
        $nation = $request->validated('nation');
        $dashboard = $dashboardData->forNation($nation === 'scotland' ? 'scotland' : 'ew');

        return response()->json(['data' => [
            'nation' => $nation,
            'nation_label' => $nation === 'scotland' ? 'Scotland' : 'England & Wales',
            'available_from' => $nation === 'scotland' ? '2015-01-01' : '2008-01-01',
            'statistics' => [
                'total_certificates' => $dashboard['stats']['total'],
                'latest_lodgement_date' => $dashboard['stats']['latest_lodgement'],
                'last_30_days' => $dashboard['stats']['last30_count'],
                'last_12_months' => $dashboard['stats']['last365_count'],
            ],
            'certificates_by_year' => collect($dashboard['byYear'])->map(fn ($row): array => [
                'year' => (int) $row->yr,
                'count' => (int) $row->cnt,
            ])->values(),
            'current_ratings_by_year' => $this->withPercentages($dashboard['ratingByYear'], 'yr'),
            'potential_ratings_by_year' => $this->withPercentages($dashboard['potentialByYear'], 'yr'),
            'rating_distribution' => $this->withPercentages($dashboard['ratingDist']),
            'tenure_by_year' => $this->withPercentages($dashboard['tenureByYear'], 'yr', 'tenure'),
            'website_url' => route('epc.home', ['nation' => $nation === 'scotland' ? 'scotland' : 'ew']),
        ]]);
    }

    private function withPercentages(mixed $rows, ?string $group = null, string $label = 'rating'): mixed
    {
        $collection = collect($rows);
        $totals = $group === null
            ? collect(['all' => $collection->sum(fn ($row): int => (int) $row->cnt)])
            : $collection->groupBy($group)->map(fn ($groupRows): int => $groupRows->sum(fn ($row): int => (int) $row->cnt));

        return $collection->map(function ($row) use ($group, $label, $totals): array {
            $total = (int) $totals->get($group === null ? 'all' : $row->{$group}, 0);
            $result = $group === null ? [] : ['year' => (int) $row->{$group}];
            $result[$label] = $row->{$label};
            $result['count'] = (int) $row->cnt;
            $result['percentage'] = $total > 0 ? round(((int) $row->cnt / $total) * 100, 1) : 0.0;

            return $result;
        })->values();
    }
}
