<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SchoolController as WebsiteSchoolController;
use App\Http\Resources\SchoolResource;
use App\Services\PropertyResearch\SchoolLocalMarketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function __invoke(
        Request $request,
        string $slug,
        WebsiteSchoolController $schoolController,
        SchoolLocalMarketService $localMarketService,
    ): SchoolResource|RedirectResponse {
        $resolved = $schoolController->schoolPayloadForSlug($slug);

        if ($resolved === null) {
            abort(404, 'School not found');
        }

        if ($resolved['canonical_slug'] !== $slug) {
            return redirect()->route('api.v1.schools.show', [
                'slug' => $resolved['canonical_slug'],
            ], 301);
        }

        $payload = $resolved['payload'];
        $payload['canonical_slug'] = $resolved['canonical_slug'];
        $payload['local_property_market'] = $localMarketService->forPostcode(
            (string) ($payload['school']->postcode ?? ''),
        );

        return new SchoolResource($payload);
    }
}
