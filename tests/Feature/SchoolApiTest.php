<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
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

    public function test_successful_school_response_contains_website_school_information(): void
    {
        $this->insertSchool();
        $this->insertOfsted();

        $this->getJson('/api/v1/schools/bousfield-primary-school')
            ->assertOk()
            ->assertJsonPath('data.slug', 'bousfield-primary-school')
            ->assertJsonPath('data.urn', '100001')
            ->assertJsonPath('data.name', 'Bousfield Primary School')
            ->assertJsonPath('data.phase', 'Primary')
            ->assertJsonPath('data.establishment_type', 'Community school')
            ->assertJsonPath('data.age_range.minimum', 4)
            ->assertJsonPath('data.age_range.maximum', 11)
            ->assertJsonPath('data.pupil_count', 450)
            ->assertJsonPath('data.capacity', 480)
            ->assertJsonPath('data.postcode', 'SW5 0DJ')
            ->assertJsonPath('data.latitude', 51.4912)
            ->assertJsonPath('data.longitude', -0.1911)
            ->assertJsonPath('data.school_website', 'https://bousfield.example')
            ->assertJsonPath('data.headteacher', 'Ms Alex Example')
            ->assertJsonPath('data.current_ofsted_rating', 'Outstanding')
            ->assertJsonPath('data.latest_inspection_date', '2025-02-03')
            ->assertJsonPath('data.inspection_type', 'Graded inspection')
            ->assertJsonPath('data.inspection_outcome', 'Inspection')
            ->assertJsonPath('data.ofsted_report_url', 'https://reports.ofsted.gov.uk/provider/21/100001')
            ->assertJsonPath('data.local_property_market.outcode', 'SW5')
            ->assertJsonPath('data.website_url', route('schools.show', [
                'slug' => 'bousfield-primary-school',
            ]));
    }

    public function test_non_canonical_school_slug_redirects_to_the_canonical_api_url(): void
    {
        $this->insertSchool();

        $this->getJson('/api/v1/schools/bousfield-primary-school-100001')
            ->assertMovedPermanently()
            ->assertRedirect(route('api.v1.schools.show', [
                'slug' => 'bousfield-primary-school',
            ]));
    }

    public function test_missing_optional_school_fields_are_returned_as_null(): void
    {
        $this->insertSchool(optionalFields: false);

        $this->getJson('/api/v1/schools/bousfield-primary-school')
            ->assertOk()
            ->assertJsonPath('data.telephone', null)
            ->assertJsonPath('data.school_website', null)
            ->assertJsonPath('data.headteacher', null)
            ->assertJsonPath('data.religious_character', null)
            ->assertJsonPath('data.trust', null)
            ->assertJsonPath('data.academy_sponsor', null)
            ->assertJsonPath('data.current_ofsted_rating', null)
            ->assertJsonPath('data.latest_inspection_date', null)
            ->assertJsonPath('data.ofsted_report_url', null);
    }

    public function test_school_not_found_returns_404(): void
    {
        $this->getJson('/api/v1/schools/not-a-real-school')->assertNotFound();
    }

    private function insertSchool(bool $optionalFields = true): void
    {
        $row = [
            'urn' => '100001',
            'establishment_name' => 'Bousfield Primary School',
            'type_of_establishment_name' => 'Community school',
            'phase_of_education_name' => 'Primary',
            'statutory_low_age' => 4,
            'statutory_high_age' => 11,
            'number_of_pupils' => 450,
            'school_capacity' => 480,
            'open_date' => '1905-01-01',
            'street' => 'Bolton Gardens',
            'town' => 'London',
            'postcode' => 'SW5 0DJ',
            'la_name' => 'Kensington and Chelsea',
            'admissions_policy_name' => 'Not applicable',
            'gender_name' => 'Mixed',
            'boarders_name' => 'No boarders',
        ];

        if ($optionalFields) {
            $row += [
                'telephone_num' => '02073736544',
                'school_website' => 'bousfield.example',
                'head_title_name' => 'Ms',
                'head_first_name' => 'Alex',
                'head_last_name' => 'Example',
                'religious_character_name' => 'Does not apply',
                'trusts_name' => 'Example Trust',
                'school_sponsors_name' => 'Example Sponsor',
            ];
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            $row['location_latitude'] = 51.4912;
            $row['location_longitude'] = -0.1911;
        }

        DB::table('property_school_establishments')->insert($row);
    }

    private function insertOfsted(): void
    {
        DB::table('property_schools')->insert([
            'urn' => 100001,
            'school_name' => 'Bousfield Primary School',
            'web_link_opens_in_new_window' => 'https://reports.ofsted.gov.uk/provider/21/100001',
            'latest_oeif_overall_effectiveness' => '1',
            'inspection_start_date_of_latest_oeif_graded_inspection' => '2025-02-03',
            'inspection_type_of_latest_oeif_graded_inspection' => 'Graded inspection',
            'event_type_grouping_of_latest_oeif_graded_inspection' => 'Inspection',
        ]);
    }
}
