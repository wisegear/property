<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EpcNativeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-25 12:00:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_england_wales_dashboard_returns_cached_statistics_and_percentages(): void
    {
        DB::table('epc_certificates')->insert([
            ['LMK_KEY' => 'EW-1', 'LODGEMENT_DATE' => '2026-06-25', 'CURRENT_ENERGY_RATING' => 'C', 'POTENTIAL_ENERGY_RATING' => 'B', 'TENURE' => 'Owner-occupied'],
            ['LMK_KEY' => 'EW-2', 'LODGEMENT_DATE' => '2026-06-20', 'CURRENT_ENERGY_RATING' => 'C', 'POTENTIAL_ENERGY_RATING' => 'A', 'TENURE' => 'Rented (private)'],
        ]);

        $this->getJson('/api/v1/epc/dashboard?nation=england-wales')
            ->assertOk()
            ->assertJsonPath('data.nation', 'england-wales')
            ->assertJsonPath('data.nation_label', 'England & Wales')
            ->assertJsonPath('data.available_from', '2008-01-01')
            ->assertJsonPath('data.statistics.total_certificates', 2)
            ->assertJsonPath('data.statistics.latest_lodgement_date', '2026-06-25')
            ->assertJsonPath('data.current_ratings_by_year.0.percentage', 100)
            ->assertJsonPath('data.website_url', route('epc.home', ['nation' => 'ew']));

        $this->assertTrue(Cache::has('epc:ew:stats'));
    }

    public function test_scotland_dashboard_uses_the_same_structure(): void
    {
        DB::table('epc_certificates_scotland')->insert([
            'REPORT_REFERENCE_NUMBER' => 'SC-1',
            'LODGEMENT_DATE' => '2026-06-25',
            'CURRENT_ENERGY_RATING' => 'D',
            'POTENTIAL_ENERGY_RATING' => 'C',
            'TENURE' => 'rented (social)',
        ]);

        $this->getJson('/api/v1/epc/dashboard?nation=scotland')
            ->assertOk()
            ->assertJsonPath('data.nation_label', 'Scotland')
            ->assertJsonPath('data.available_from', '2015-01-01')
            ->assertJsonPath('data.statistics.total_certificates', 1)
            ->assertJsonPath('data.tenure_by_year.0.tenure', 'Rented (social)')
            ->assertJsonStructure(['data' => [
                'statistics', 'certificates_by_year', 'current_ratings_by_year',
                'potential_ratings_by_year', 'rating_distribution', 'tenure_by_year',
            ]]);
    }

    public function test_dashboard_handles_empty_datasets(): void
    {
        $this->getJson('/api/v1/epc/dashboard?nation=england-wales')
            ->assertOk()
            ->assertJsonPath('data.statistics.total_certificates', 0)
            ->assertJsonPath('data.statistics.latest_lodgement_date', null)
            ->assertJsonPath('data.certificates_by_year', [])
            ->assertJsonPath('data.rating_distribution', []);
    }

    public function test_england_wales_search_normalizes_postcode_and_generates_urls(): void
    {
        DB::table('epc_certificates')->insert([
            'LMK_KEY' => 'EW-SEARCH-1',
            'ADDRESS' => '1 Exhibition Road',
            'POSTCODE' => 'SW7 5PH',
            'LODGEMENT_DATE' => '2026-01-02',
            'CURRENT_ENERGY_RATING' => 'C',
            'POTENTIAL_ENERGY_RATING' => 'B',
            'PROPERTY_TYPE' => 'Flat',
            'TOTAL_FLOOR_AREA' => '72.5',
            'LOCAL_AUTHORITY_LABEL' => 'Kensington and Chelsea',
        ]);

        $this->getJson('/api/v1/epc/search?nation=england-wales&postcode=sw75ph')
            ->assertOk()
            ->assertJsonPath('data.postcode', 'SW7 5PH')
            ->assertJsonPath('data.results.0.reference', 'EW-SEARCH-1')
            ->assertJsonPath('data.results.0.total_floor_area_square_metres', 72.5)
            ->assertJsonPath('data.results.0.api_url', route('api.v1.epc.show', ['reference' => 'EW-SEARCH-1']))
            ->assertJsonPath('data.results.0.website_url', route('epc.show', ['lmk' => 'EW-SEARCH-1']));
    }

    public function test_scotland_search_normalizes_postcode_and_generates_urls(): void
    {
        DB::table('epc_certificates_scotland')->insert([
            'REPORT_REFERENCE_NUMBER' => 'SC-SEARCH-1',
            'ADDRESS1' => '10 University Avenue',
            'ADDRESS2' => 'Glasgow',
            'POSTCODE' => 'G12 8QQ',
            'LODGEMENT_DATE' => '2026-01-02',
            'CURRENT_ENERGY_RATING' => 'D',
            'POTENTIAL_ENERGY_RATING' => 'C',
        ]);

        $this->getJson('/api/v1/epc/search?nation=scotland&postcode=g128qq')
            ->assertOk()
            ->assertJsonPath('data.postcode', 'G12 8QQ')
            ->assertJsonPath('data.results.0.reference', 'SC-SEARCH-1')
            ->assertJsonPath('data.results.0.address', '10 University Avenue, Glasgow')
            ->assertJsonPath('data.results.0.api_url', route('api.v1.epc.scotland.show', ['reference' => 'SC-SEARCH-1']))
            ->assertJsonPath('data.results.0.website_url', route('epc.scotland.show', ['rrn' => 'SC-SEARCH-1']));
    }

    public function test_valid_postcode_without_certificates_returns_empty_results(): void
    {
        $this->getJson('/api/v1/epc/search?nation=scotland&postcode=G12%208QQ')
            ->assertOk()
            ->assertJsonPath('data.results', [])
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonPath('data.meta.last_page', 1);
    }

    public function test_invalid_or_incomplete_postcode_returns_422(): void
    {
        $this->getJson('/api/v1/epc/search?nation=england-wales&postcode=SW7')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('postcode');
    }

    public function test_invalid_nation_returns_clear_validation_error(): void
    {
        $this->getJson('/api/v1/epc/dashboard?nation=wales')
            ->assertUnprocessable()
            ->assertJsonPath('errors.nation.0', 'The nation must be either england-wales or scotland.');
    }

    public function test_search_returns_pagination_metadata(): void
    {
        $rows = [];
        for ($index = 1; $index <= 51; $index++) {
            $rows[] = [
                'LMK_KEY' => "PAGE-{$index}",
                'POSTCODE' => 'SW7 5PH',
                'LODGEMENT_DATE' => '2026-01-02',
            ];
        }
        DB::table('epc_certificates')->insert($rows);

        $this->getJson('/api/v1/epc/search?nation=england-wales&postcode=SW7%205PH&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonPath('data.meta.per_page', 50)
            ->assertJsonPath('data.meta.total', 51);
    }

    public function test_scottish_certificate_is_normalized_for_the_existing_detail_screen(): void
    {
        DB::table('epc_certificates_scotland')->insert([
            'REPORT_REFERENCE_NUMBER' => 'SC-DETAIL-1',
            'BUILDING_REFERENCE_NUMBER' => 'BUILD-1',
            'ADDRESS1' => '12 Byres Road',
            'POSTCODE' => 'G12 8QQ',
            'LODGEMENT_DATE' => '2026-01-02',
            'CURRENT_ENERGY_RATING' => 'D',
            'POTENTIAL_ENERGY_RATING' => 'C',
            'TOTAL_FLOOR_AREA' => '83',
            'WALL_DESCRIPTION' => 'Solid brick',
        ]);

        $this->getJson('/api/v1/epc/scotland/SC-DETAIL-1')
            ->assertOk()
            ->assertJsonPath('data.reference', 'SC-DETAIL-1')
            ->assertJsonPath('data.report_reference_number', 'SC-DETAIL-1')
            ->assertJsonPath('data.address.display', '12 Byres Road')
            ->assertJsonPath('data.energy.current_rating', 'D')
            ->assertJsonPath('data.property.total_floor_area_square_metres', 83)
            ->assertJsonPath('data.construction.walls.description', 'Solid brick')
            ->assertJsonPath('data.website_url', route('epc.scotland.show', ['rrn' => 'SC-DETAIL-1']));
    }

    public function test_missing_scottish_certificate_returns_404(): void
    {
        $this->getJson('/api/v1/epc/scotland/missing')
            ->assertNotFound()
            ->assertJsonPath('message', 'EPC certificate not found.');
    }

    public function test_scottish_certificate_keeps_missing_optional_fields_nullable(): void
    {
        DB::table('epc_certificates_scotland')->insert([
            'REPORT_REFERENCE_NUMBER' => 'SC-SPARSE-1',
            'POSTCODE' => 'G12 8QQ',
        ]);

        $this->getJson('/api/v1/epc/scotland/SC-SPARSE-1')
            ->assertOk()
            ->assertJsonPath('data.address.display', null)
            ->assertJsonPath('data.property.type', null)
            ->assertJsonPath('data.energy.current_rating', null)
            ->assertJsonPath('data.estimated_costs.heating.current', null);
    }
}
