<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ScottishPricesController as WebScottishPricesController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScottishPricesController extends Controller
{
    public function __invoke(Request $request, WebScottishPricesController $controller): JsonResponse
    {
        return response()->json(['data' => $controller->dashboardData($request)]);
    }
}
