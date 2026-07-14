<?php

namespace Tests\Feature;

use App\Http\Controllers\SchoolController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolControllerTest extends TestCase
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

    public function test_school_page_exists(): void
    {
        $this->insertSchool();
        $this->insertOfsted();

        $this->get('/school/the-london-oratory-school')
            ->assertOk()
            ->assertSee('The London Oratory School')
            ->assertSee('School details')
            ->assertSee('Ofsted')
            ->assertDontSee('Local crime trend')
            ->assertDontSee('Average EPC rating')
            ->assertDontSee('Nearby property statistics')
            ->assertDontSee('Latest nearby sales');
    }

    public function test_hero_includes_compact_school_summary(): void
    {
        $this->insertSchool();

        $this->get('/school/the-london-oratory-school')
            ->assertOk()
            ->assertSee('Secondary school')
            ->assertSee('Ages 7–18')
            ->assertSee('Academy converter')
            ->assertSee('1,300 pupils');
    }

    public function test_invalid_school_returns_404(): void
    {
        $this->get('/school/not-a-real-school')->assertNotFound();
    }

    public function test_ofsted_button_is_only_shown_when_available(): void
    {
        $this->insertSchool(urn: '111111', name: 'Report School');
        $this->insertOfsted(urn: 111111, reportUrl: 'https://reports.ofsted.gov.uk/provider/21/111111');

        $this->get('/school/report-school')
            ->assertOk()
            ->assertSee('View latest Ofsted report')
            ->assertSee('https://reports.ofsted.gov.uk/provider/21/111111', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('Opens the official Ofsted inspection report.');

        Cache::flush();

        $this->insertSchool(urn: '222222', name: 'No Report School');
        $this->insertOfsted(urn: 222222, reportUrl: null, inspectionNumber: null);

        $this->get('/school/no-report-school')
            ->assertOk()
            ->assertDontSee('View latest Ofsted report');
    }

    public function test_fake_inspection_history_is_not_shown_for_latest_only_dataset(): void
    {
        $this->insertSchool();
        $this->insertOfsted();

        $this->get('/school/the-london-oratory-school')
            ->assertOk()
            ->assertDontSee('Inspection history');
    }

    public function test_map_loads_when_coordinates_exist(): void
    {
        $this->insertSchool(latitude: 51.498923, longitude: -0.182234);

        $this->get('/school/the-london-oratory-school')
            ->assertOk()
            ->assertSee('id="school-map"', false)
            ->assertSee("L.map('school-map'", false)
            ->assertSee('51.498923', false)
            ->assertSee('-0.182234', false);
    }

    public function test_google_maps_links_use_coordinates_when_available(): void
    {
        $this->insertSchool(latitude: 51.498923, longitude: -0.182234);

        $this->get('/school/the-london-oratory-school')
            ->assertOk()
            ->assertSee('Get directions')
            ->assertSee('View on Google Maps')
            ->assertSee('destination=51.498923%2C-0.182234', false)
            ->assertSee('query=51.498923%2C-0.182234', false);
    }

    public function test_google_maps_links_fall_back_to_address_without_coordinates(): void
    {
        $this->insertSchool();

        $this->get('/school/the-london-oratory-school')
            ->assertOk()
            ->assertSee('Map coordinates are not available for this school.')
            ->assertSee('destination=Seagrave%20Road%2C%20London', false)
            ->assertSee('query=Seagrave%20Road%2C%20London', false);
    }

    public function test_school_page_metadata_matches_the_trimmed_page(): void
    {
        $this->insertSchool();

        $this->get('/school/the-london-oratory-school')
            ->assertOk()
            ->assertDontSee('crime trends', false)
            ->assertDontSee('EPC information', false)
            ->assertSee('map location and key school information.', false)
            ->assertDontSee('nearby property prices', false)
            ->assertDontSee('recent sales', false);
    }

    public function test_catchment_disclaimer_is_present_without_catchment_claims(): void
    {
        $this->insertSchool();

        $this->get('/school/the-london-oratory-school')
            ->assertOk()
            ->assertSee('PropertyResearch does not publish or estimate school catchment boundaries.')
            ->assertDontSee('in catchment');
    }

    public function test_structured_data_is_generated(): void
    {
        $this->insertSchool();

        $this->get('/school/the-london-oratory-school')
            ->assertOk()
            ->assertSee('"@type":"School"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"name":"The London Oratory School"', false)
            ->assertSee('"name":"Secondary school"', false)
            ->assertSee('Hammersmith and Fulham')
            ->assertSee('/school/the-london-oratory-school', false);
    }

    public function test_schools_index_exists(): void
    {
        $this->insertSchool();

        $this->get('/schools')
            ->assertOk()
            ->assertSee('Schools')
            ->assertSee('/school/the-london-oratory-school', false);
    }

    public function test_school_page_warmer_populates_show_cache(): void
    {
        $this->insertSchool(latitude: 51.498923, longitude: -0.182234);
        $this->insertOfsted();

        $cacheKey = SchoolController::showCacheKey('137157');
        $this->assertFalse(Cache::has($cacheKey));

        $this->artisan('property:school-warm', ['--limit' => 1, '--no-progress' => true])
            ->expectsOutputToContain('Warming 1 school page caches')
            ->expectsOutputToContain('Warmed: 1')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertTrue(Cache::has('property:school:last_warm'));
    }

    public function test_school_page_warmer_can_skip_existing_cache_entries(): void
    {
        $this->insertSchool();
        Cache::put(SchoolController::showCacheKey('137157'), ['existing' => true], now()->addDay());

        $this->artisan('property:school-warm', ['--limit' => 1, '--skip-existing' => true, '--no-progress' => true])
            ->expectsOutputToContain('Skipped: 1')
            ->assertExitCode(0);

        $this->assertSame(['existing' => true], Cache::get(SchoolController::showCacheKey('137157')));
    }

    private function insertSchool(
        string $urn = '137157',
        string $name = 'The London Oratory School',
        ?float $latitude = null,
        ?float $longitude = null,
    ): void {
        $row = [
            'urn' => $urn,
            'establishment_name' => $name,
            'type_of_establishment_name' => 'Academy converter',
            'establishment_status_code' => '1',
            'phase_of_education_code' => '4',
            'phase_of_education_name' => 'Secondary',
            'statutory_low_age' => 7,
            'statutory_high_age' => 18,
            'open_date' => '2011-08-01',
            'street' => 'Seagrave Road',
            'town' => 'London',
            'postcode' => 'SW6 1RX',
            'telephone_num' => '02012345678',
            'school_website' => 'www.london-oratory.example',
            'head_title_name' => 'Mr',
            'head_first_name' => 'Example',
            'head_last_name' => 'Head',
            'la_name' => 'Hammersmith and Fulham',
            'religious_character_name' => 'Roman Catholic',
            'admissions_policy_name' => 'Selective',
            'gender_name' => 'Boys',
            'boarders_name' => 'No boarders',
            'trusts_name' => 'Example Trust',
            'school_sponsors_name' => 'Example Sponsor',
            'number_of_pupils' => 1300,
            'school_capacity' => 1500,
        ];

        if (DB::connection()->getDriverName() === 'sqlite') {
            $row['location_latitude'] = $latitude;
            $row['location_longitude'] = $longitude;
        }

        DB::table('property_school_establishments')->insert($row);
    }

    private function insertOfsted(
        int $urn = 137157,
        ?string $reportUrl = 'https://reports.ofsted.gov.uk/provider/21/137157',
        ?int $inspectionNumber = 10242411,
    ): void {
        DB::table('property_schools')->insert([
            'urn' => $urn,
            'school_name' => $urn === 137157 ? 'The London Oratory School' : 'Report School',
            'web_link_opens_in_new_window' => $reportUrl,
            'latest_oeif_overall_effectiveness' => '1',
            'inspection_start_date_of_latest_oeif_graded_inspection' => '2024-02-20',
            'inspection_type_of_latest_oeif_graded_inspection' => 'Graded inspection',
            'event_type_grouping_of_latest_oeif_graded_inspection' => 'Inspection',
            'inspection_number_of_latest_oeif_graded_inspection' => $inspectionNumber,
            'multi_academy_trust_name' => 'Example MAT',
            'academy_sponsor_name' => 'Example Sponsor',
            'total_number_of_pupils' => 1300,
        ]);
    }
}
