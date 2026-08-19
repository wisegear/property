<?php

namespace App\Http\Controllers;

use App\Services\Property\HighValuePropertyDashboard;
use Carbon\Carbon;
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

    private function dashboardView(HighValuePropertyDashboard $dashboard, Carbon $month): View
    {
        $month = $month->copy()->startOfMonth();

        return view('pages.top-sales.index', [
            ...$dashboard->cachedDataFor($month),
            'navigationYear' => now()->year,
            'availableMonths' => $dashboard->availableMonthsForYear(now()->year),
            'canonicalUrl' => route('top-sales.show', ['year' => $month->format('Y'), 'month' => $month->format('m')]),
        ]);
    }
}
