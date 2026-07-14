<?php

namespace App\Services\PropertyResearch;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class NearbySchoolsService
{
    private const OPEN_ESTABLISHMENT_STATUS_CODE = '1';

    private const METRES_PER_MILE = 1609.344;

    private const MILES_PER_DEGREE = 69.0;

    /**
     * @return array{primary: Collection<int, object>, secondary: Collection<int, object>}
     */
    public function forPoint(string $pointWkt, int $limit = 5, float $boundingRadiusMiles = 10.0): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The nearby schools limit must be at least 1.');
        }

        if ($boundingRadiusMiles <= 0) {
            throw new InvalidArgumentException('The nearby schools bounding radius must be greater than zero.');
        }

        return [
            'primary' => $this->schoolsForPhase($pointWkt, '2', $limit, $boundingRadiusMiles),
            'secondary' => $this->schoolsForPhase($pointWkt, '4', $limit, $boundingRadiusMiles),
        ];
    }

    private function schoolsForPhase(string $pointWkt, string $phaseCode, int $limit, float $boundingRadiusMiles): Collection
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return $this->sqliteSchoolsForPhase($pointWkt, $phaseCode, $limit, $boundingRadiusMiles);
        }

        $pointSql = 'ST_SetSRID(ST_GeomFromText(?), 4326)';
        $schoolPointSql = $this->postgresSchoolPointSql();
        $distanceSql = "ST_DistanceSphere({$schoolPointSql}, {$pointSql}) / ".self::METRES_PER_MILE;
        $boundingDistanceDegrees = $boundingRadiusMiles / self::MILES_PER_DEGREE;

        return DB::table('property_school_establishments as pse')
            ->leftJoin('property_schools as os', function ($join): void {
                $join->whereRaw('pse.urn = os.urn::text');
            })
            ->where('pse.establishment_status_code', self::OPEN_ESTABLISHMENT_STATUS_CODE)
            ->where('pse.phase_of_education_code', $phaseCode)
            ->where(function ($query): void {
                $query->whereNotNull('pse.location')
                    ->orWhere(function ($query): void {
                        $query->whereNotNull('pse.easting')
                            ->whereNotNull('pse.northing');
                    });
            })
            ->whereRaw("ST_DWithin({$schoolPointSql}, {$pointSql}, ?)", [$pointWkt, $boundingDistanceDegrees])
            ->select([
                'pse.urn',
                'pse.establishment_name',
                'pse.postcode',
                'pse.phase_of_education_name as school_phase',
                'pse.type_of_establishment_name as establishment_type',
                'pse.statutory_low_age',
                'pse.statutory_high_age',
                'os.latest_oeif_overall_effectiveness as latest_ofsted_overall_effectiveness',
                'os.inspection_start_date_of_latest_oeif_graded_inspection as latest_inspection_date',
            ])
            ->selectRaw("CONCAT(pse.statutory_low_age, '-', pse.statutory_high_age) as age_range")
            ->selectRaw("ROUND(({$distanceSql})::numeric, 2) as distance_miles", [$pointWkt])
            ->orderByRaw($distanceSql, [$pointWkt])
            ->limit($limit)
            ->get();
    }

    private function postgresSchoolPointSql(): string
    {
        return <<<'SQL'
CASE
    WHEN pse.location IS NOT NULL THEN pse.location
    WHEN pse.easting IS NOT NULL AND pse.northing IS NOT NULL
        THEN ST_Transform(ST_SetSRID(ST_MakePoint(pse.easting, pse.northing), 27700), 4326)
    ELSE NULL
END
SQL;
    }

    private function sqliteSchoolsForPhase(string $pointWkt, string $phaseCode, int $limit, float $boundingRadiusMiles): Collection
    {
        if (
            ! Schema::hasColumn('property_school_establishments', 'location_latitude')
            || ! Schema::hasColumn('property_school_establishments', 'location_longitude')
        ) {
            return collect();
        }

        [$longitude, $latitude] = $this->parsePointWkt($pointWkt);
        $latitudeDelta = $boundingRadiusMiles / self::MILES_PER_DEGREE;
        $longitudeDelta = $boundingRadiusMiles / max(cos(deg2rad($latitude)) * self::MILES_PER_DEGREE, 0.000001);

        $distanceSql = '(
            3958.7613 * 2 * asin(sqrt(
                power(sin(radians((pse.location_latitude - ?) / 2)), 2)
                + cos(radians(?)) * cos(radians(pse.location_latitude))
                * power(sin(radians((pse.location_longitude - ?) / 2)), 2)
            ))
        )';

        return DB::table('property_school_establishments as pse')
            ->leftJoin('property_schools as os', 'pse.urn', '=', 'os.urn')
            ->where('pse.establishment_status_code', self::OPEN_ESTABLISHMENT_STATUS_CODE)
            ->where('pse.phase_of_education_code', $phaseCode)
            ->whereBetween('pse.location_latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('pse.location_longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
            ->select([
                'pse.urn',
                'pse.establishment_name',
                'pse.postcode',
                'pse.phase_of_education_name as school_phase',
                'pse.type_of_establishment_name as establishment_type',
                'pse.statutory_low_age',
                'pse.statutory_high_age',
                'os.latest_oeif_overall_effectiveness as latest_ofsted_overall_effectiveness',
                'os.inspection_start_date_of_latest_oeif_graded_inspection as latest_inspection_date',
            ])
            ->selectRaw("(pse.statutory_low_age || '-' || pse.statutory_high_age) as age_range")
            ->selectRaw("ROUND({$distanceSql}, 2) as distance_miles", [$latitude, $latitude, $longitude])
            ->orderByRaw($distanceSql, [$latitude, $latitude, $longitude])
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function parsePointWkt(string $pointWkt): array
    {
        if (! preg_match('/^POINT\((-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\)$/i', trim($pointWkt), $matches)) {
            throw new InvalidArgumentException('The supplied point must be WKT in the form POINT(longitude latitude).');
        }

        return [(float) $matches[1], (float) $matches[2]];
    }
}
