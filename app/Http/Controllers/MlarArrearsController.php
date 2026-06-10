<?php

namespace App\Http\Controllers;

use App\Models\MlarArrear;
use Illuminate\View\View;

class MlarArrearsController extends Controller
{
    public function index(): View
    {
        $descriptions = MlarArrear::select('description')
            ->distinct()
            ->orderBy('description')
            ->get()
            ->keyBy('description');

        $seriesByDescription = MlarArrear::orderBy('year')
            ->orderByRaw("CASE quarter WHEN 'Q1' THEN 1 WHEN 'Q2' THEN 2 WHEN 'Q3' THEN 3 WHEN 'Q4' THEN 4 ELSE 5 END")
            ->get()
            ->groupBy('description');

        $periods = MlarArrear::select('year', 'quarter')
            ->selectRaw("CASE quarter WHEN 'Q1' THEN 1 WHEN 'Q2' THEN 2 WHEN 'Q3' THEN 3 WHEN 'Q4' THEN 4 ELSE 5 END as quarter_sort")
            ->distinct()
            ->orderBy('year')
            ->orderBy('quarter_sort')
            ->get()
            ->map(fn ($row) => $row->year.' '.$row->quarter)
            ->values();

        $latest = MlarArrear::select('year', 'quarter')
            ->orderBy('year', 'desc')
            ->orderByRaw("CASE quarter WHEN 'Q4' THEN 1 WHEN 'Q3' THEN 2 WHEN 'Q2' THEN 3 WHEN 'Q1' THEN 4 ELSE 5 END")
            ->first();

        $latestValues = null;

        if ($latest) {
            $latestValues = MlarArrear::where('year', $latest->year)
                ->where('quarter', $latest->quarter)
                ->orderBy('description')
                ->get();
        }

        return view('arrears.index', [
            'descriptions' => $descriptions,
            'seriesByDescription' => $seriesByDescription,
            'periods' => $periods,
            'latest' => $latest,
            'latestValues' => $latestValues,
        ]);
    }
}
