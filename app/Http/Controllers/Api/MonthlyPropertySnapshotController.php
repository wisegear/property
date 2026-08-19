<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Property\MonthlyPropertySnapshot;
use Illuminate\Http\JsonResponse;

class MonthlyPropertySnapshotController extends Controller
{
    public function __invoke(MonthlyPropertySnapshot $snapshot): JsonResponse
    {
        return response()->json(['data' => $snapshot->cachedData()]);
    }
}
