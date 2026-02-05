<?php

namespace App\Http\Controllers;

use App\Models\MlarArrear;

class MlarArrearsController extends Controller
{
    public function index()
    {
        // Distinct bands with their descriptions (for labels / legend)
        $bands = MlarArrear::select('band', 'description')
            ->distinct()
            ->orderBy('band')
            ->get()
            ->keyBy('band');

        // Full time series grouped by band (for charts / tables)
        $seriesByBand = MlarArrear::orderBy('year')
            ->orderByRaw("CASE quarter WHEN 'Q1' THEN 1 WHEN 'Q2' THEN 2 WHEN 'Q3' THEN 3 WHEN 'Q4' THEN 4 ELSE 5 END")
            ->get()
            ->groupBy('band');

        // Ordered list of periods like "2007 Q1", "2007 Q2", ...
        $periods = MlarArrear::select('year', 'quarter')
            ->selectRaw("CASE quarter WHEN 'Q1' THEN 1 WHEN 'Q2' THEN 2 WHEN 'Q3' THEN 3 WHEN 'Q4' THEN 4 ELSE 5 END as quarter_sort")
            ->distinct()
            ->orderBy('year')
            ->orderBy('quarter_sort')
            ->get()
            ->map(fn ($row) => $row->year.' '.$row->quarter)
            ->values();

        // Latest quarter (for headline numbers)
        $latest = MlarArrear::select('year', 'quarter')
            ->orderBy('year', 'desc')
            ->orderByRaw("CASE quarter WHEN 'Q4' THEN 1 WHEN 'Q3' THEN 2 WHEN 'Q2' THEN 3 WHEN 'Q1' THEN 4 ELSE 5 END")
            ->first();

        $latestValues = null;

        if ($latest) {
            $latestValues = MlarArrear::where('year', $latest->year)
                ->where('quarter', $latest->quarter)
                ->orderBy('band')
                ->get();
        }

        return view('arrears.index', [
            'bands' => $bands,
            'seriesByBand' => $seriesByBand,
            'periods' => $periods,
            'latest' => $latest,
            'latestValues' => $latestValues,
        ]);
    }
}
