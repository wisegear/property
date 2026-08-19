<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Property\HighValuePropertyDashboard;
use Illuminate\Http\JsonResponse;

class TopPropertySalesController extends Controller
{
    public function __invoke(HighValuePropertyDashboard $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->cachedDataFor($dashboard->latestMonth())]);
    }
}
