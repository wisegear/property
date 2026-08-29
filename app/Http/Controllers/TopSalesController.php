<?php

namespace App\Http\Controllers;

use App\Http\Requests\TopSalesMapPointsRequest;
use App\Services\Property\HighValuePropertyDashboard;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class TopSalesController extends Controller
{
    public function index(HighValuePropertyDashboard $dashboard): View
    {
        return $this->dashboardView($dashboard, $dashboard->latestMonth());
    }

    public function show(string $year, string $month, HighValuePropertyDashboard $dashboard): View
    {
        $selectedMonth = Carbon::createFromFormat('!Y-m', $year.'-'.$month);

        abort_unless($dashboard->isAvailable($selectedMonth), 404);

        return $this->dashboardView($dashboard, $selectedMonth);
    }

    public function points(TopSalesMapPointsRequest $request, string $year, string $month, HighValuePropertyDashboard $dashboard): JsonResponse
    {
        $selectedMonth = Carbon::createFromFormat('!Y-m', $year.'-'.$month);

        abort_if($selectedMonth->lessThan(Carbon::create(2026, 7, 1)), 404);
        abort_unless($dashboard->isAvailable($selectedMonth), 404);

        $validated = $request->validated();

        return response()->json($dashboard->propertyMapPoints(
            $selectedMonth,
            (int) $validated['e_min'],
            (int) $validated['e_max'],
            (int) $validated['n_min'],
            (int) $validated['n_max'],
            (int) ($validated['limit'] ?? 2500),
        ));
    }

    private function dashboardView(HighValuePropertyDashboard $dashboard, Carbon $month): View
    {
        $month = $month->copy()->startOfMonth();

        return view('pages.top-sales.index', [
            ...$dashboard->cachedDataFor($month),
            'navigationYear' => now()->year,
            'availableMonths' => $dashboard->availableMonthsForYear(now()->year),
            'canonicalUrl' => route('top-sales.show', ['year' => $month->format('Y'), 'month' => $month->format('m')]),
            'propertyMapAvailable' => $month->greaterThanOrEqualTo(Carbon::create(2026, 7, 1)),
            'propertyMapPointsUrl' => route('top-sales.points', ['year' => $month->format('Y'), 'month' => $month->format('m')], false),
        ]);
    }
}
