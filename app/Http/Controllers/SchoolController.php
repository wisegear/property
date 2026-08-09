<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolSearchRequest;
use App\Models\PropertySchoolEstablishment;
use App\Support\PropertyResearch\OfstedRating;
use App\Support\PropertyResearch\SchoolSlug;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SchoolController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24 * 45;

    private const SHOW_CACHE_VERSION = 'v2';

    public static function showCacheKey(string $urn): string
    {
        return 'school:show:'.self::SHOW_CACHE_VERSION.':'.$urn;
    }

    private const INDEX_CACHE_VERSION = 'v7';

    public function index(SchoolSearchRequest $request): View
    {
        $dashboard = Cache::remember(
            'schools:index:dashboard:'.self::INDEX_CACHE_VERSION,
            self::CACHE_TTL,
            fn (): array => $this->buildIndexDashboard(),
        );

        $filters = Cache::remember(
            'schools:index:filters:'.self::INDEX_CACHE_VERSION,
            self::CACHE_TTL,
            fn (): array => $this->buildIndexFilters(),
        );

        $results = null;

        if (collect($request->validated())->filter(fn (mixed $value): bool => filled($value))->isNotEmpty()) {
            $results = $this->searchSchools($request);
        }

        return view('schools.index', [
            'dashboard' => $dashboard,
            'filters' => $filters,
            'results' => $results?->items() ?? [],
            'pagination' => $results === null ? null : $this->paginationData($results),
            'search' => $request->validated(),
        ]);
    }

    public function search(SchoolSearchRequest $request): JsonResponse
    {
        $schools = $this->searchSchools($request);

        return response()->json([
            'data' => $schools->items(),
            'pagination' => $this->paginationData($schools),
        ]);
    }

    /**
     * @return array{total:int,excluded:int,ratings:array<int, array{value:string,label:string,count:int,percentage:float}>,landscape:array<int, array{value:string,label:string,count:int}>}
     */
    private function buildIndexDashboard(): array
    {
        $allOpenSchoolsQuery = PropertySchoolEstablishment::query()->where('establishment_status_code', '1')->whereNotNull('establishment_name')->where('establishment_name', '!=', '');
        $this->excludeWelshSchools($allOpenSchoolsQuery, 'property_school_establishments');
        $allOpenSchools = $allOpenSchoolsQuery->count();
        $ratingQuery = DB::table('property_school_establishments as pse');
        $this->joinOfstedData($ratingQuery, 'pse.urn');
        $this->excludeWelshSchools($ratingQuery, 'pse');
        $ratingCounts = $ratingQuery
            ->whereNotNull('pse.establishment_name')
            ->where('pse.establishment_name', '!=', '')
            ->where('pse.establishment_status_code', '1')
            ->whereNotNull('os.urn')
            ->selectRaw("COALESCE(os.latest_oeif_overall_effectiveness, 'none') as rating, COUNT(*) as aggregate")
            ->groupBy('os.latest_oeif_overall_effectiveness')
            ->pluck('aggregate', 'rating');
        $total = (int) $ratingCounts->sum();

        $ratings = collect([
            ['value' => '1', 'label' => 'Outstanding'],
            ['value' => '2', 'label' => 'Good'],
            ['value' => '3', 'label' => 'Requires improvement'],
            ['value' => '4', 'label' => 'Inadequate'],
            ['value' => 'not_judged', 'label' => 'Not judged'],
            ['value' => 'no_grade', 'label' => 'No current overall grade'],
        ])->map(function (array $rating) use ($ratingCounts, $total): array {
            $sourceValue = match ($rating['value']) {
                'not_judged' => 'Not judged',
                'no_grade' => 'none',
                default => $rating['value'],
            };
            $count = (int) ($ratingCounts[$sourceValue] ?? 0);

            return $rating + [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
            ];
        })->all();

        $phaseQuery = DB::table('property_school_establishments as pse');
        $this->joinOfstedData($phaseQuery, 'pse.urn');
        $this->excludeWelshSchools($phaseQuery, 'pse');
        $phaseCounts = $phaseQuery
            ->where('pse.establishment_status_code', '1')
            ->whereNotNull('os.urn')
            ->whereNotNull('pse.phase_of_education_name')
            ->where('pse.phase_of_education_name', '!=', '')
            ->whereRaw("LOWER(pse.phase_of_education_name) NOT IN ('not applicable', 'does not apply')")
            ->selectRaw('pse.phase_of_education_name, COUNT(*) as aggregate')
            ->groupBy('pse.phase_of_education_name')
            ->orderByDesc('aggregate')
            ->limit(6)
            ->get();

        return [
            'total' => $total,
            'excluded' => $allOpenSchools - $total,
            'ratings' => $ratings,
            'landscape' => $phaseCounts->map(fn (object $phase): array => [
                'value' => (string) $phase->phase_of_education_name,
                'label' => Str::headline((string) $phase->phase_of_education_name),
                'count' => (int) $phase->aggregate,
            ])->all(),
        ];
    }

    /**
     * @return array{phases:Collection<int, string>,localAuthorities:Collection<int, string>}
     */
    private function buildIndexFilters(): array
    {
        $phasesQuery = DB::table('property_school_establishments as pse');
        $this->joinOfstedData($phasesQuery, 'pse.urn');
        $this->excludeWelshSchools($phasesQuery, 'pse');
        $phases = $phasesQuery->where('pse.establishment_status_code', '1')->whereNotNull('pse.phase_of_education_name')->where('pse.phase_of_education_name', '!=', '')->whereRaw("LOWER(pse.phase_of_education_name) NOT IN ('not applicable', 'does not apply')")->distinct()->orderBy('pse.phase_of_education_name')->pluck('pse.phase_of_education_name')->values();

        $authoritiesQuery = DB::table('property_school_establishments as pse');
        $this->joinOfstedData($authoritiesQuery, 'pse.urn');
        $this->excludeWelshSchools($authoritiesQuery, 'pse');
        $localAuthorities = $authoritiesQuery->where('pse.establishment_status_code', '1')->whereNotNull('pse.la_name')->where('pse.la_name', '!=', '')->distinct()->orderBy('pse.la_name')->pluck('pse.la_name')->values();

        return compact('phases', 'localAuthorities');
    }

    private function searchSchools(SchoolSearchRequest $request): LengthAwarePaginator
    {
        $validated = $request->validated();
        $term = trim((string) ($validated['q'] ?? ''));
        $rating = $validated['rating'] ?? null;

        $query = PropertySchoolEstablishment::query()
            ->select([
                'property_school_establishments.urn',
                'property_school_establishments.establishment_name',
                'property_school_establishments.town',
                'property_school_establishments.postcode',
                'property_school_establishments.phase_of_education_name',
                'property_school_establishments.type_of_establishment_name',
                'property_school_establishments.la_name',
            ]);
        $this->joinOfstedData($query, 'property_school_establishments.urn');
        $query->addSelect('os.latest_oeif_overall_effectiveness as rating')
            ->whereNotNull('property_school_establishments.establishment_name')
            ->where('property_school_establishments.establishment_name', '!=', '')
            ->where('property_school_establishments.establishment_status_code', '1');
        $this->excludeWelshSchools($query, 'property_school_establishments');

        if ($term !== '') {
            $normalisedPostcode = strtoupper((string) preg_replace('/\s+/', '', $term));
            $query->where(function ($query) use ($term, $normalisedPostcode): void {
                $query->whereRaw('LOWER(property_school_establishments.establishment_name) LIKE ?', ['%'.strtolower($term).'%'])
                    ->orWhereRaw("REPLACE(UPPER(property_school_establishments.postcode), ' ', '') LIKE ?", [$normalisedPostcode.'%']);
            });
        }

        if ($rating === 'no_grade') {
            $query->whereNull('os.latest_oeif_overall_effectiveness');
        } elseif ($rating === 'not_judged') {
            $query->where('os.latest_oeif_overall_effectiveness', 'Not judged');
        } elseif ($rating !== null) {
            $query->where('os.latest_oeif_overall_effectiveness', $rating);
        }

        $query->when($validated['phase'] ?? null, fn ($query, string $phase) => $query->where('property_school_establishments.phase_of_education_name', $phase));
        $query->when($validated['local_authority'] ?? null, fn ($query, string $authority) => $query->where('property_school_establishments.la_name', $authority));

        $schools = $query->orderBy('property_school_establishments.establishment_name')->paginate(20);
        $duplicateNames = PropertySchoolEstablishment::query()
            ->whereIn('establishment_name', $schools->getCollection()->pluck('establishment_name'))
            ->selectRaw('establishment_name, COUNT(*) as aggregate')
            ->groupBy('establishment_name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('aggregate', 'establishment_name');

        $schools->through(function (PropertySchoolEstablishment $school) use ($duplicateNames): array {
            $rating = OfstedRating::from($school->rating);

            return [
                'name' => (string) $school->establishment_name,
                'phase' => $school->phase_of_education_name,
                'type' => $school->type_of_establishment_name,
                'town' => $school->town,
                'local_authority' => $school->la_name,
                'postcode' => $school->postcode,
                'rating' => $rating->label,
                'rating_class' => $rating->badgeClass,
                'url' => route('schools.show', ['slug' => SchoolSlug::for((string) $school->establishment_name, $school->urn, $duplicateNames->has($school->establishment_name))]),
            ];
        });

        return $schools;
    }

    /**
     * @return array{current_page:int,last_page:int,per_page:int,total:int,from:?int,to:?int}
     */
    private function paginationData(LengthAwarePaginator $schools): array
    {
        return [
            'current_page' => $schools->currentPage(),
            'last_page' => $schools->lastPage(),
            'per_page' => $schools->perPage(),
            'total' => $schools->total(),
            'from' => $schools->firstItem(),
            'to' => $schools->lastItem(),
        ];
    }

    private function excludeWelshSchools(mixed $query, string $tableAlias): void
    {
        $query
            ->where(function ($query) use ($tableAlias): void {
                $query->whereNull($tableAlias.'.type_of_establishment_name')
                    ->orWhere($tableAlias.'.type_of_establishment_name', '!=', 'Welsh establishment');
            })
            ->where(function ($query) use ($tableAlias): void {
                $query->whereNull($tableAlias.'.gssla_code')
                    ->orWhere($tableAlias.'.gssla_code', 'not like', 'W%');
            });
    }

    private function joinOfstedData(mixed $query, string $urnColumn): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->leftJoin('property_schools as os', fn ($join) => $join->whereRaw($urnColumn.' = os.urn::text'));

            return;
        }

        $query->leftJoin('property_schools as os', $urnColumn, '=', 'os.urn');
    }

    public function show(string $slug)
    {
        $resolved = $this->resolveSchoolForSlug($slug);

        if ($resolved === null) {
            abort(404, 'School not found');
        }

        if ($resolved['canonical_slug'] !== $slug) {
            return redirect()->route('schools.show', ['slug' => $resolved['canonical_slug']], 301);
        }

        $payload = $this->warmSchoolCache($resolved['urn']);

        return view('schools.show', $payload + [
            'canonicalUrl' => route('schools.show', ['slug' => $resolved['canonical_slug']]),
            'canonicalSlug' => $resolved['canonical_slug'],
        ]);
    }

    /**
     * @return array{payload:array<string, mixed>, canonical_slug:string}|null
     */
    public function schoolPayloadForSlug(string $slug): ?array
    {
        $resolved = $this->resolveSchoolForSlug($slug);

        if ($resolved === null) {
            return null;
        }

        return [
            'payload' => $this->warmSchoolCache($resolved['urn']),
            'canonical_slug' => $resolved['canonical_slug'],
        ];
    }

    public function warmSchoolCache(string $urn): array
    {
        return Cache::remember(self::showCacheKey($urn), self::CACHE_TTL, function () use ($urn): array {
            return $this->buildSchoolPayload($urn);
        });
    }

    public function refreshSchoolCache(string $urn): array
    {
        Cache::forget(self::showCacheKey($urn));

        return $this->warmSchoolCache($urn);
    }

    private function buildSchoolPayload(string $urn): array
    {
        $school = $this->schoolQuery()
            ->where('pse.urn', $urn)
            ->first();

        if ($school === null) {
            abort(404, 'School not found');
        }

        $coordinates = $this->coordinatesFromSchool($school) ?? $this->schoolCoordinates($urn);
        $school->ofstedRating = OfstedRating::from($school->latest_ofsted_overall_effectiveness ?? null);
        $school->ageRange = $this->ageRange($school->statutory_low_age ?? null, $school->statutory_high_age ?? null);
        $school->address = $this->address($school);
        $school->openingDateLabel = $this->formatDate($school->open_date ?? null);
        $school->inspectionDateLabel = $this->formatDate($school->latest_inspection_date ?? $school->inspection_start_date ?? null);
        $school->reportUrl = $this->ofstedReportUrl($school);
        $school->capacityPercentage = $this->capacityPercentage($school);
        $school->websiteUrl = $this->websiteUrl($school->school_website ?? null);
        $school->phaseLabel = $this->phaseLabel($school->phase_of_education_name ?? null);
        $school->pupilCountLabel = $this->pupilCountLabel($school->number_of_pupils ?? $school->ofsted_total_number_of_pupils ?? null);
        $mapsPayload = $this->mapsPayload($coordinates, $school->address);

        return [
            'school' => $school,
            'coordinates' => $coordinates,
            ...$mapsPayload,
        ];
    }

    private function schoolQuery()
    {
        $query = DB::table('property_school_establishments as pse');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->leftJoin('property_schools as os', function ($join): void {
                $join->whereRaw('pse.urn = os.urn::text');
            });

            return $query->select([
                'pse.*',
                'os.web_link_opens_in_new_window',
                'os.latest_oeif_overall_effectiveness as latest_ofsted_overall_effectiveness',
                'os.inspection_start_date_of_latest_oeif_graded_inspection as latest_inspection_date',
                'os.inspection_type_of_latest_oeif_graded_inspection as latest_inspection_type',
                'os.inspection_type_grouping_of_latest_oeif_graded_inspection as latest_inspection_type_grouping',
                'os.event_type_grouping_of_latest_oeif_graded_inspection as latest_inspection_outcome',
                'os.ungraded_inspection_overall_outcome',
                'os.inspection_number_of_latest_oeif_graded_inspection',
                'os.inspection_number_of_latest_full_inspection',
                'os.latest_ungraded_inspection_number',
                'os.multi_academy_trust_name',
                'os.academy_sponsor_name',
                'os.total_number_of_pupils as ofsted_total_number_of_pupils',
                'os.inspection_start_date',
                'os.inspection_type',
                'os.inspection_type_grouping',
                'os.event_type_grouping',
            ])
                ->selectRaw('ST_Y(CASE WHEN pse.location IS NOT NULL THEN pse.location WHEN pse.easting IS NOT NULL AND pse.northing IS NOT NULL THEN ST_Transform(ST_SetSRID(ST_MakePoint(pse.easting, pse.northing), 27700), 4326) ELSE NULL END) as school_lat')
                ->selectRaw('ST_X(CASE WHEN pse.location IS NOT NULL THEN pse.location WHEN pse.easting IS NOT NULL AND pse.northing IS NOT NULL THEN ST_Transform(ST_SetSRID(ST_MakePoint(pse.easting, pse.northing), 27700), 4326) ELSE NULL END) as school_lng');
        }

        $query->leftJoin('property_schools as os', 'pse.urn', '=', 'os.urn');

        return $query->select([
            'pse.*',
            'os.web_link_opens_in_new_window',
            'os.latest_oeif_overall_effectiveness as latest_ofsted_overall_effectiveness',
            'os.inspection_start_date_of_latest_oeif_graded_inspection as latest_inspection_date',
            'os.inspection_type_of_latest_oeif_graded_inspection as latest_inspection_type',
            'os.inspection_type_grouping_of_latest_oeif_graded_inspection as latest_inspection_type_grouping',
            'os.event_type_grouping_of_latest_oeif_graded_inspection as latest_inspection_outcome',
            'os.ungraded_inspection_overall_outcome',
            'os.inspection_number_of_latest_oeif_graded_inspection',
            'os.inspection_number_of_latest_full_inspection',
            'os.latest_ungraded_inspection_number',
            'os.multi_academy_trust_name',
            'os.academy_sponsor_name',
            'os.total_number_of_pupils as ofsted_total_number_of_pupils',
            'os.inspection_start_date',
            'os.inspection_type',
            'os.inspection_type_grouping',
            'os.event_type_grouping',
            'pse.location_latitude as school_lat',
            'pse.location_longitude as school_lng',
        ]);
    }

    /**
     * @return array{lat:float,lng:float}|null
     */
    private function coordinatesFromSchool(object $school): ?array
    {
        if (($school->school_lat ?? null) === null || ($school->school_lng ?? null) === null) {
            return null;
        }

        return [
            'lat' => (float) $school->school_lat,
            'lng' => (float) $school->school_lng,
        ];
    }

    /**
     * @return array{urn:string, canonical_slug:string}|null
     */
    private function resolveSchoolForSlug(string $slug): ?array
    {
        $normalizedSlug = SchoolSlug::base($slug);

        if (preg_match('/^(.*)-(\d+)$/', $normalizedSlug, $matches)) {
            $candidate = PropertySchoolEstablishment::query()
                ->select(['urn', 'establishment_name'])
                ->where('urn', $matches[2])
                ->whereNotNull('establishment_name')
                ->first();

            return $this->resolvedSchoolCandidate($candidate, $normalizedSlug);
        }

        $checkedUrns = [];

        foreach ($this->slugSearchTokens($normalizedSlug) as $token) {
            $candidates = PropertySchoolEstablishment::query()
                ->select(['urn', 'establishment_name'])
                ->whereNotNull('establishment_name')
                ->whereRaw('LOWER(establishment_name) LIKE ?', ['%'.strtolower($token).'%'])
                ->get();

            foreach ($candidates as $candidate) {
                if (isset($checkedUrns[(string) $candidate->urn])) {
                    continue;
                }

                $checkedUrns[(string) $candidate->urn] = true;
                $resolved = $this->resolvedSchoolCandidate($candidate, $normalizedSlug);

                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function slugSearchTokens(string $slug): array
    {
        $tokens = array_values(array_unique(array_filter(
            explode('-', $slug),
            fn (string $token): bool => strlen($token) >= 3,
        )));

        usort($tokens, fn (string $first, string $second): int => strlen($second) <=> strlen($first));

        return $tokens;
    }

    /**
     * @return array{urn:string, canonical_slug:string}|null
     */
    private function resolvedSchoolCandidate(?PropertySchoolEstablishment $school, string $slug): ?array
    {
        if ($school === null) {
            return null;
        }

        $baseSlug = SchoolSlug::base((string) $school->establishment_name);

        if ($baseSlug !== $slug && ! preg_match('/^'.preg_quote($baseSlug, '/').'-\d+$/', $slug)) {
            return null;
        }

        $canonicalSlug = SchoolSlug::for((string) $school->establishment_name, $school->urn);

        if ($slug === $baseSlug && $canonicalSlug !== $baseSlug) {
            return null;
        }

        return [
            'urn' => (string) $school->urn,
            'canonical_slug' => $canonicalSlug,
        ];
    }

    /**
     * @return array{lat:float,lng:float}|null
     */
    private function schoolCoordinates(string $urn): ?array
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $row = DB::table('property_school_establishments')
                ->where('urn', $urn)
                ->selectRaw('ST_Y(CASE WHEN location IS NOT NULL THEN location WHEN easting IS NOT NULL AND northing IS NOT NULL THEN ST_Transform(ST_SetSRID(ST_MakePoint(easting, northing), 27700), 4326) ELSE NULL END) as lat')
                ->selectRaw('ST_X(CASE WHEN location IS NOT NULL THEN location WHEN easting IS NOT NULL AND northing IS NOT NULL THEN ST_Transform(ST_SetSRID(ST_MakePoint(easting, northing), 27700), 4326) ELSE NULL END) as lng')
                ->first();

            if ($row?->lat !== null && $row?->lng !== null) {
                return ['lat' => (float) $row->lat, 'lng' => (float) $row->lng];
            }
        }

        if (
            Schema::hasColumn('property_school_establishments', 'location_latitude')
            && Schema::hasColumn('property_school_establishments', 'location_longitude')
        ) {
            $row = DB::table('property_school_establishments')
                ->where('urn', $urn)
                ->select(['location_latitude as lat', 'location_longitude as lng'])
                ->first();

            if ($row?->lat !== null && $row?->lng !== null) {
                return ['lat' => (float) $row->lat, 'lng' => (float) $row->lng];
            }
        }

        return null;
    }

    private function ofstedReportUrl(object $school): ?string
    {
        $directUrl = trim((string) ($school->web_link_opens_in_new_window ?? ''));

        if ($directUrl !== '') {
            return $directUrl;
        }

        $inspectionNumber = $school->inspection_number_of_latest_oeif_graded_inspection
            ?? $school->inspection_number_of_latest_full_inspection
            ?? $school->latest_ungraded_inspection_number
            ?? null;

        if ($inspectionNumber !== null) {
            return 'https://files.ofsted.gov.uk/v1/file/'.$inspectionNumber;
        }

        return null;
    }

    private function ageRange(mixed $lowAge, mixed $highAge): ?string
    {
        if ($lowAge === null || $highAge === null) {
            return null;
        }

        return $lowAge.'–'.$highAge;
    }

    private function phaseLabel(?string $phase): ?string
    {
        $phase = trim((string) $phase);

        if ($phase === '') {
            return null;
        }

        return str_contains(strtolower($phase), 'school') ? $phase : $phase.' school';
    }

    private function pupilCountLabel(mixed $pupils): ?string
    {
        if ($pupils === null || (int) $pupils <= 0) {
            return null;
        }

        return number_format((int) $pupils).' '.Str::plural('pupil', (int) $pupils);
    }

    private function address(object $school): string
    {
        return collect([$school->street, $school->locality, $school->address3, $school->town, $school->county_name])
            ->filter(fn ($part): bool => trim((string) $part) !== '')
            ->join(', ');
    }

    private function formatDate(mixed $date): ?string
    {
        if ($date === null || trim((string) $date) === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('j M Y');
        } catch (\Throwable) {
            return null;
        }
    }

    private function capacityPercentage(object $school): ?int
    {
        $pupils = $school->number_of_pupils ?? $school->ofsted_total_number_of_pupils ?? null;
        $capacity = $school->school_capacity ?? null;

        if ($pupils === null || $capacity === null || (int) $capacity <= 0) {
            return null;
        }

        return (int) round(((int) $pupils / (int) $capacity) * 100);
    }

    private function websiteUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : 'https://'.$url;
    }

    /**
     * @param  array{lat:float,lng:float}|null  $coordinates
     * @return array{googleMapsUrl:string,directionsUrl:string}
     */
    private function mapsPayload(?array $coordinates, string $address): array
    {
        $destination = $coordinates !== null
            ? $coordinates['lat'].','.$coordinates['lng']
            : $address;

        $encodedDestination = rawurlencode($destination);

        return [
            'googleMapsUrl' => 'https://www.google.com/maps/search/?api=1&query='.$encodedDestination,
            'directionsUrl' => 'https://www.google.com/maps/dir/?api=1&destination='.$encodedDestination,
        ];
    }
}
