<?php

namespace App\Http\Controllers;

use App\Models\PropertySchoolEstablishment;
use App\Services\PropertyResearch\SchoolLocalMarketService;
use Illuminate\Contracts\View\View;

class SchoolLocalMarketController extends Controller
{
    public function __invoke(string $urn, SchoolLocalMarketService $localMarketService): View
    {
        $school = PropertySchoolEstablishment::query()
            ->select(['urn', 'postcode'])
            ->where('urn', $urn)
            ->firstOrFail();

        return view('schools.partials.local-market', [
            'snapshot' => $localMarketService->forPostcode((string) $school->postcode),
        ]);
    }
}
