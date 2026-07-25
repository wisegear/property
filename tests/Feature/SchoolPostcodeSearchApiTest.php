<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolPostcodeSearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('property_school_establishments', function (Blueprint $table): void {
                if (! Schema::hasColumn('property_school_establishments', 'location_latitude')) {
                    $table->decimal('location_latitude', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('property_school_establishments', 'location_longitude')) {
                    $table->decimal('location_longitude', 10, 7)->nullable();
                }
            });
        }
    }

    public function test_successful_postcode_search_groups_schools_and_returns_urls(): void
    {
        $this->insertPostcode();
        $this->insertSchool('100001', 'Near Primary', '2', 'Primary', -0.1810, 51.4900);
        $this->insertSchool('100002', 'Near Secondary', '4', 'Secondary', -0.1790, 51.4900);
        DB::table('property_schools')->insert([
            'urn' => 100001,
            'latest_oeif_overall_effectiveness' => '1',
            'inspection_start_date_of_latest_oeif_graded_inspection' => '2025-02-03',
        ]);

        $this->getJson('/api/v1/schools?postcode=SW7%205PH')
            ->assertOk()
            ->assertJsonPath('data.postcode', 'SW7 5PH')
            ->assertJsonPath('data.latitude', 51.49)
            ->assertJsonPath('data.longitude', -0.18)
            ->assertJsonPath('data.primary.0.urn', '100001')
            ->assertJsonPath('data.primary.0.name', 'Near Primary')
            ->assertJsonPath('data.primary.0.phase', 'Primary')
            ->assertJsonPath('data.primary.0.establishment_type', 'Community school')
            ->assertJsonPath('data.primary.0.age_range', '4-11')
            ->assertJsonPath('data.primary.0.current_ofsted_rating', 'Outstanding')
            ->assertJsonPath('data.primary.0.latest_inspection_date', '2025-02-03')
            ->assertJsonPath('data.primary.0.api_url', route('api.v1.schools.show', ['slug' => 'near-primary']))
            ->assertJsonPath('data.primary.0.website_url', route('schools.show', ['slug' => 'near-primary']))
            ->assertJsonPath('data.secondary.0.urn', '100002')
            ->assertJsonPath('data.secondary.0.current_ofsted_rating', null)
            ->assertJsonPath('data.secondary.0.latest_inspection_date', null);
    }

    public function test_postcode_is_normalized(): void
    {
        $this->insertPostcode();

        $this->getJson('/api/v1/schools?postcode=sw75ph')
            ->assertOk()
            ->assertJsonPath('data.postcode', 'SW7 5PH');
    }

    public function test_schools_are_ordered_by_distance_nearest_first(): void
    {
        $this->insertPostcode();
        $this->insertSchool('100003', 'Further Primary', '2', 'Primary', -0.2200, 51.4900);
        $this->insertSchool('100004', 'Nearest Primary', '2', 'Primary', -0.1810, 51.4900);
        $this->insertSchool('100005', 'Middle Primary', '2', 'Primary', -0.1950, 51.4900);

        $response = $this->getJson('/api/v1/schools?postcode=SW7%205PH')->assertOk();

        $this->assertSame(
            ['100004', '100005', '100003'],
            $response->json('data.primary.*.urn'),
        );
        $this->assertLessThan(
            $response->json('data.primary.1.distance_miles'),
            $response->json('data.primary.0.distance_miles'),
        );
    }

    public function test_invalid_or_incomplete_postcode_returns_validation_error(): void
    {
        $this->getJson('/api/v1/schools?postcode=SW7')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('postcode');
    }

    public function test_unknown_postcode_returns_not_found(): void
    {
        $this->getJson('/api/v1/schools?postcode=ZZ1%201ZZ')
            ->assertNotFound();
    }

    public function test_valid_postcode_with_no_nearby_schools_returns_empty_groups(): void
    {
        $this->insertPostcode();

        $this->getJson('/api/v1/schools?postcode=SW7%205PH')
            ->assertOk()
            ->assertJsonPath('data.primary', [])
            ->assertJsonPath('data.secondary', []);
    }

    private function insertPostcode(): void
    {
        DB::table('onspd_v2')->insert([
            'pcds' => 'SW7 5PH',
            'ctry25cd' => 'E92000001',
            'lat' => 51.49,
            'long' => -0.18,
        ]);
    }

    private function insertSchool(
        string $urn,
        string $name,
        string $phaseCode,
        string $phaseName,
        float $longitude,
        float $latitude,
    ): void {
        $school = [
            'urn' => $urn,
            'establishment_name' => $name,
            'postcode' => 'SW7 4AB',
            'establishment_status_code' => '1',
            'phase_of_education_code' => $phaseCode,
            'phase_of_education_name' => $phaseName,
            'type_of_establishment_name' => 'Community school',
            'statutory_low_age' => $phaseCode === '2' ? 4 : 11,
            'statutory_high_age' => $phaseCode === '2' ? 11 : 18,
        ];

        if (DB::connection()->getDriverName() === 'pgsql') {
            $school['location'] = DB::raw("ST_SetSRID(ST_MakePoint({$longitude}, {$latitude}), 4326)");
        } else {
            $school['location_longitude'] = $longitude;
            $school['location_latitude'] = $latitude;
        }

        DB::table('property_school_establishments')->insert($school);
    }
}
