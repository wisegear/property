<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Property\NationalPropertyDashboard;
use Illuminate\Http\JsonResponse;

class PropertyDashboardController extends Controller
{
    public function __invoke(NationalPropertyDashboard $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->cachedData()]);
    }
}
