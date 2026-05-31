<?php

namespace App\Http\Controllers;

use App\Models\WageGrowthMonthly;
use Illuminate\View\View;

class WageGrowthController extends Controller
{
    public function index(): View
    {
        $all = WageGrowthMonthly::orderBy('date')->get();

        if ($all->isEmpty()) {
            return view('wage_growth.index', [
                'all' => collect(),
                'latest' => null,
                'previous' => null,
                'labels_three' => [],
                'values_three' => [],
            ]);
        }

        $latest = $all->last();
        $previous = $all->count() > 1 ? $all[$all->count() - 2] : null;
        $labels = $all->map(fn ($r) => $r->date->format('Y-m-d'))->values();
        $valuesThree = $all->map(function ($r) {
            return $r->three_month_avg_yoy !== null ? (float) $r->three_month_avg_yoy : null;
        })->values();

        return view('wage_growth.index', [
            'all' => $all,
            'latest' => $latest,
            'previous' => $previous,
            'labels' => $labels,
            'values_three' => $valuesThree,
        ]);
    }
}
