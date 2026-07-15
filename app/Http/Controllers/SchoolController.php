<?php

namespace App\Http\Controllers;

use App\Models\PropertySchoolEstablishment;
use App\Support\PropertyResearch\OfstedRating;
use App\Support\PropertyResearch\SchoolSlug;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SchoolController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24 * 45;

    private const SHOW_CACHE_VERSION = 'v2';

    public static function showCacheKey(string $urn): string
    {
        return 'school:show:'.self::SHOW_CACHE_VERSION.':'.$urn;
    }

    public function index()
    {
        $schools = Cache::remember('schools:index:v1', self::CACHE_TTL, function (): Collection {
            return PropertySchoolEstablishment::query()
                ->select(['urn', 'establishment_name', 'town', 'postcode', 'phase_of_education_name', 'type_of_establishment_name'])
                ->whereNotNull('establishment_name')
                ->where('establishment_name', '!=', '')
                ->orderBy('establishment_name')
                ->limit(100)
                ->get()
                ->map(function (PropertySchoolEstablishment $school): object {
                    return (object) [
                        'name' => (string) $school->establishment_name,
                        'phase' => $school->phase_of_education_name,
                        'type' => $school->type_of_establishment_name,
                        'place' => collect([$school->town, $school->postcode])->filter()->join(', '),
                        'url' => route('schools.show', [
                            'slug' => SchoolSlug::for((string) $school->establishment_name, $school->urn),
                        ]),
                    ];
                });
        });

        return view('schools.index', [
            'schools' => $schools,
        ]);
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

        if ($canonicalSlug !== $slug) {
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
