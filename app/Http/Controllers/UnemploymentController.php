<?php

namespace App\Http\Controllers;

use App\Models\UnemploymentMonthly;
use Illuminate\Contracts\View\View;

class UnemploymentController extends Controller
{
    public function index(): View
    {
        $series = UnemploymentMonthly::orderBy('date')->get();

        if ($series->isEmpty()) {
            return view('unemployment.index', [
                'series' => collect(),
                'latest' => null,
                'previousYear' => null,
                'yearOnYearDelta' => null,
                'labels' => json_encode([]),
                'values' => json_encode([]),
            ]);
        }

        $latest = $series->last();

        $previousYear = UnemploymentMonthly::whereDate(
            'date',
            $latest->date->copy()->subYear()
        )->first();

        $yearOnYearDelta = $previousYear
            ? round((float) $latest->three_month - (float) $previousYear->three_month, 2)
            : null;

        $chartSeries = $series;

        $labels = $chartSeries
            ->map(fn ($row) => $row->date->format('M Y'))
            ->values();

        $values = $chartSeries
            ->map(fn ($row) => round((float) $row->three_month, 2))
            ->values();

        return view('unemployment.index', [
            'series' => $series,
            'latest' => $latest,
            'previousYear' => $previousYear,
            'yearOnYearDelta' => $yearOnYearDelta,
            'labels' => $labels->toJson(),
            'values' => $values->toJson(),
        ]);
    }
}
