<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SchoolPostcodeSearchRequest;
use App\Http\Resources\SchoolPostcodeSearchResource;
use App\Services\PropertyResearch\NearbySchoolsService;
use Illuminate\Support\Facades\DB;

class SchoolPostcodeSearchController extends Controller
{
    public function __invoke(
        SchoolPostcodeSearchRequest $request,
        NearbySchoolsService $nearbySchoolsService,
    ): SchoolPostcodeSearchResource {
        $postcode = (string) $request->validated('postcode');
        $coordinates = DB::table('onspd_v2')
            ->select(['lat', 'long'])
            ->where('pcds', $postcode)
            ->whereIn('ctry25cd', ['E92000001', 'W92000004'])
            ->first();

        if ($coordinates === null || $coordinates->lat === null || $coordinates->long === null) {
            abort(404, 'Postcode not found');
        }

        $latitude = (float) $coordinates->lat;
        $longitude = (float) $coordinates->long;
        $schools = $nearbySchoolsService->forPoint(
            "POINT({$longitude} {$latitude})",
            limit: 10,
        );

        return new SchoolPostcodeSearchResource([
            'postcode' => $postcode,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'primary' => $schools['primary'],
            'secondary' => $schools['secondary'],
        ]);
    }
}
