<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RentalController;
use Illuminate\Http\JsonResponse;

class RentalDashboardController extends Controller
{
    public function index(RentalController $rental): JsonResponse
    {
        return response()->json(['data' => $rental->dashboardData()]);
    }

    public function show(string $nation, RentalController $rental): JsonResponse
    {
        $name = match ($nation) {
            'england' => 'England',
            'scotland' => 'Scotland',
            'wales' => 'Wales',
            'northern-ireland' => 'Northern Ireland',
            default => abort(404),
        };

        return response()->json(['data' => $rental->nationData($name)]);
    }
}
