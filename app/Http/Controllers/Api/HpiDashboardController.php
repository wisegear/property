<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HpiDashboardController as WebHpiDashboardController;
use App\Models\HpiMonthly;
use Illuminate\Http\JsonResponse;

class HpiDashboardController extends Controller
{
    public function __invoke(WebHpiDashboardController $dashboardController): JsonResponse
    {
        $dashboard = $dashboardController->dashboardData();

        $nations = collect($dashboard['nations'])
            ->map(fn (HpiMonthly $nation): array => [
                'name' => $nation->RegionName,
                'code' => $nation->AreaCode,
                'date' => HpiMonthly::normalizedDate($nation->Date)?->toDateString(),
                'average_price' => $nation->AveragePrice === null ? null : (float) $nation->AveragePrice,
                'one_month_change' => $nation->one_m_change === null ? null : (float) $nation->one_m_change,
                'twelve_month_change' => $nation->twelve_m_change === null ? null : (float) $nation->twelve_m_change,
                'sales_volume' => $nation->SalesVolume === null ? null : (int) $nation->SalesVolume,
            ])
            ->all();

        $mapRegion = fn (HpiMonthly $region): array => [
            'name' => $region->RegionName,
            'code' => $region->AreaCode,
            'average_price' => $region->AveragePrice === null ? null : (float) $region->AveragePrice,
            'twelve_month_change' => $region->{'12m%Change'} === null
                ? null
                : (float) $region->{'12m%Change'},
        ];

        return response()->json(['data' => [
            'title' => 'HPI Dashboard',
            'description' => 'House Price Index for UK and England, Wales, Scotland and Northern Ireland.',
            'source_note' => 'All data is official government House Price Index data provided by gov.uk and presented without modification. Figures may differ from commercial indices such as Halifax, Nationwide or Rightmove.',
            'latest_date' => $dashboard['latestGlobal'] === null
                ? null
                : HpiMonthly::normalizedDate($dashboard['latestGlobal'])?->toDateString(),
            'nations' => $nations,
            'annual_change_series' => $dashboard['seriesByArea'],
            'property_type_series' => $dashboard['typePriceSeries'],
            'movers' => collect($dashboard['movers'])->map($mapRegion)->all(),
            'losers' => collect($dashboard['losers'])->map($mapRegion)->all(),
            'website_url' => route('hpi.home'),
        ]]);
    }
}
