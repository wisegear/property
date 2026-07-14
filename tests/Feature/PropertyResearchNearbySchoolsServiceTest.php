<?php

namespace Tests\Feature;

use App\Services\PropertyResearch\NearbySchoolsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PropertyResearchNearbySchoolsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('property_school_establishments', function (Blueprint $table): void {
                $table->decimal('location_longitude', 10, 7)->nullable();
                $table->decimal('location_latitude', 10, 7)->nullable();
            });
        }
    }

    public function test_it_returns_a_school_with_an_ofsted_match(): void
    {
        $this->insertEstablishment(
            urn: '100001',
            name: 'Matched Primary School',
            phaseCode: '2',
            phaseName: 'Primary',
            longitude: -0.1000,
            latitude: 51.5000
        );

        DB::table('property_schools')->insert([
            'urn' => 100001,
            'latest_oeif_overall_effectiveness' => '1',
            'inspection_start_date_of_latest_oeif_graded_inspection' => '2026-01-15',
        ]);

        $schools = app(NearbySchoolsService::class)->forPoint('POINT(-0.1001 51.5001)');

        $this->assertCount(1, $schools['primary']);
        $this->assertSame('100001', (string) $schools['primary']->first()->urn);
        $this->assertSame('Matched Primary School', $schools['primary']->first()->establishment_name);
        $this->assertSame('4-11', $schools['primary']->first()->age_range);
        $this->assertSame('1', $schools['primary']->first()->latest_ofsted_overall_effectiveness);
        $this->assertSame('2026-01-15', (string) $schools['primary']->first()->latest_inspection_date);
    }

    public function test_it_returns_a_school_without_an_ofsted_match(): void
    {
        $this->insertEstablishment(
            urn: '100002',
            name: 'Unmatched Secondary School',
            phaseCode: '4',
            phaseName: 'Secondary',
            longitude: -0.1000,
            latitude: 51.5000
        );

        $schools = app(NearbySchoolsService::class)->forPoint('POINT(-0.1001 51.5001)');

        $this->assertCount(1, $schools['secondary']);
        $this->assertSame('100002', (string) $schools['secondary']->first()->urn);
        $this->assertNull($schools['secondary']->first()->latest_ofsted_overall_effectiveness);
        $this->assertNull($schools['secondary']->first()->latest_inspection_date);
    }

    public function test_it_excludes_closed_schools(): void
    {
        $this->insertEstablishment(
            urn: '100003',
            name: 'Open Primary School',
            phaseCode: '2',
            phaseName: 'Primary',
            longitude: -0.1000,
            latitude: 51.5000
        );

        $this->insertEstablishment(
            urn: '100004',
            name: 'Closed Primary School',
            phaseCode: '2',
            phaseName: 'Primary',
            longitude: -0.1001,
            latitude: 51.5001,
            statusCode: '2'
        );

        $schools = app(NearbySchoolsService::class)->forPoint('POINT(-0.1001 51.5001)');

        $this->assertSame(['100003'], $schools['primary']->pluck('urn')->map(fn ($urn): string => (string) $urn)->all());
    }

    public function test_it_orders_schools_by_nearest_distance(): void
    {
        $this->insertEstablishment(
            urn: '100005',
            name: 'Further Primary School',
            phaseCode: '2',
            phaseName: 'Primary',
            longitude: -0.1400,
            latitude: 51.5000
        );

        $this->insertEstablishment(
            urn: '100006',
            name: 'Nearest Primary School',
            phaseCode: '2',
            phaseName: 'Primary',
            longitude: -0.1010,
            latitude: 51.5000
        );

        $this->insertEstablishment(
            urn: '100007',
            name: 'Middle Primary School',
            phaseCode: '2',
            phaseName: 'Primary',
            longitude: -0.1200,
            latitude: 51.5000
        );

        $schools = app(NearbySchoolsService::class)->forPoint('POINT(-0.1000 51.5000)');

        $this->assertSame(
            ['100006', '100007', '100005'],
            $schools['primary']->pluck('urn')->map(fn ($urn): string => (string) $urn)->all()
        );

        $this->assertLessThan(
            (float) $schools['primary'][1]->distance_miles,
            (float) $schools['primary'][0]->distance_miles
        );
    }

    public function test_it_uses_british_national_grid_coordinates_when_postgis_location_is_missing(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('British National Grid fallback requires PostGIS.');
        }

        DB::table('property_school_establishments')->insert([
            'urn' => '100008',
            'establishment_name' => 'Grid Coordinate Primary School',
            'postcode' => 'SW7 5PH',
            'establishment_status_code' => '1',
            'phase_of_education_code' => '2',
            'phase_of_education_name' => 'Primary',
            'type_of_establishment_name' => 'Community school',
            'statutory_low_age' => 4,
            'statutory_high_age' => 11,
            'easting' => 526486,
            'northing' => 178667,
            'location' => null,
        ]);

        $schools = app(NearbySchoolsService::class)->forPoint('POINT(-0.182234 51.498923)');

        $this->assertSame('Grid Coordinate Primary School', $schools['primary']->first()->establishment_name);
    }

    private function insertEstablishment(
        string $urn,
        string $name,
        string $phaseCode,
        string $phaseName,
        float $longitude,
        float $latitude,
        string $statusCode = '1'
    ): void {
        $row = [
            'urn' => $urn,
            'establishment_name' => $name,
            'postcode' => 'AB1 2CD',
            'establishment_status_code' => $statusCode,
            'phase_of_education_code' => $phaseCode,
            'phase_of_education_name' => $phaseName,
            'type_of_establishment_name' => 'Community school',
            'statutory_low_age' => 4,
            'statutory_high_age' => $phaseCode === '2' ? 11 : 18,
        ];

        if (DB::connection()->getDriverName() === 'pgsql') {
            $row['location'] = DB::raw("ST_SetSRID(ST_MakePoint({$longitude}, {$latitude}), 4326)");
        } else {
            $row['location_longitude'] = $longitude;
            $row['location_latitude'] = $latitude;
        }

        DB::table('property_school_establishments')->insert($row);
    }
}
