<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CrimeController as WebsiteCrimeController;
use Illuminate\Http\JsonResponse;

class CrimeController extends Controller
{
    public function __construct(
        private WebsiteCrimeController $crimeController,
    ) {}

    public function index(): JsonResponse
    {
        $payload = $this->crimeController->nationalPayloadForApi();

        return response()->json([
            'data' => [
                'latest_month' => $payload['latest_month'],
                'latest_month_label' => $payload['latest_month_label'],
                'summary' => $payload['summary'],
                'chart' => $payload['chart'],
                'crime_types' => $payload['crime_types'],
                'drivers' => $payload['drivers'],
                'areas' => collect($payload['areas'])
                    ->map(fn (array $area): array => [
                        ...$area,
                        'api_url' => route('api.v1.insights.crime.show', ['area_slug' => $area['slug']]),
                        'website_url' => route('insights.crime.show', ['area' => $area['slug']]),
                    ])
                    ->values(),
                'website_url' => route('insights.crime.index'),
            ],
        ]);
    }

    public function show(string $areaSlug): JsonResponse
    {
        $payload = $this->crimeController->areaPayloadForApi($areaSlug);

        abort_if($payload === null, 404);

        return response()->json([
            'data' => [
                ...$payload,
                'website_url' => route('insights.crime.show', ['area' => $payload['area_slug']]),
            ],
        ]);
    }
}
