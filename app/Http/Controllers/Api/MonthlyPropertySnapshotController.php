<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Property\MonthlyPropertySnapshot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonthlyPropertySnapshotController extends Controller
{
    public function __invoke(Request $request, MonthlyPropertySnapshot $snapshot): JsonResponse
    {
        $request->validate([
            'year' => ['nullable', 'required_with:month', 'integer', 'digits:4'],
            'month' => ['nullable', 'required_with:year', 'regex:/^(?:0?[1-9]|1[0-2])$/'],
        ]);

        if ($request->filled(['year', 'month'])) {
            $month = Carbon::createFromDate(
                $request->integer('year'),
                $request->integer('month'),
                1,
            )->startOfMonth();

            abort_unless($snapshot->isAvailable($month), 404);

            return response()->json(['data' => $snapshot->cachedDataFor($month)]);
        }

        return response()->json(['data' => $snapshot->cachedData()]);
    }
}
