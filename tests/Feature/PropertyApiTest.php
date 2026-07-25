<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PropertyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_postcode_search_returns_app_ready_property_results(): void
    {
        DB::table('land_registry')->insert($this->landRegistryRow());

        $this->getJson('/api/v1/properties?postcode=ab12cd')
            ->assertOk()
            ->assertJsonPath('postcode', 'AB1 2CD')
            ->assertJsonPath('results.0.property_slug', 'ab1-2cd-10-market-road')
            ->assertJsonPath('results.0.price', 410000)
            ->assertJsonPath('results.0.url', route('api.v1.properties.show', [
                'slug' => 'ab1-2cd-10-market-road',
            ]))
            ->assertJsonPath('meta.total', 1);
    }

    public function test_api_route_returns_json_without_an_accept_header(): void
    {
        DB::table('land_registry')->insert($this->landRegistryRow());

        $this->get('/api/v1/properties?postcode=AB1%202CD')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('postcode', 'AB1 2CD');
    }

    public function test_postcode_search_returns_json_validation_errors(): void
    {
        $this->getJson('/api/v1/properties?postcode=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('postcode');
    }

    public function test_property_research_returns_the_website_research_sections(): void
    {
        DB::table('land_registry')->insert($this->landRegistryRow());
        DB::table('onspd_v2')->insert([
            'pcds' => 'AB1 2CD',
            'lat' => 52.123456,
            'long' => -1.123456,
        ]);

        $this->getJson('/api/v1/properties/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertJsonPath('data.slug', 'ab1-2cd-10-market-road')
            ->assertJsonPath('data.location.postcode', 'AB1 2CD')
            ->assertJsonPath('data.transactions.0.price', 410000)
            ->assertJsonStructure([
                'data' => [
                    'property_type',
                    'location',
                    'transactions',
                    'epc_certificates',
                    'nearby_schools' => ['primary', 'secondary'],
                    'crime',
                    'deprivation',
                    'council_tax_estimate',
                    'market' => ['property_price_history', 'postcode', 'locality', 'town', 'district', 'county'],
                ],
            ]);
    }

    public function test_property_research_school_summaries_contain_api_and_website_urls(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('property_school_establishments', function (Blueprint $table): void {
                $table->decimal('location_latitude', 10, 7)->nullable();
                $table->decimal('location_longitude', 10, 7)->nullable();
            });
        }

        DB::table('land_registry')->insert($this->landRegistryRow());
        DB::table('onspd_v2')->insert([
            'pcds' => 'AB1 2CD',
            'lat' => 52.123456,
            'long' => -1.123456,
        ]);

        $school = [
            'urn' => '100001',
            'establishment_name' => 'Bousfield Primary School',
            'postcode' => 'AB1 2CD',
            'establishment_status_code' => '1',
            'phase_of_education_code' => '2',
            'phase_of_education_name' => 'Primary',
            'type_of_establishment_name' => 'Community school',
            'statutory_low_age' => 4,
            'statutory_high_age' => 11,
        ];

        if (DB::connection()->getDriverName() === 'pgsql') {
            $school['location'] = DB::raw('ST_SetSRID(ST_MakePoint(-1.123456, 52.123456), 4326)');
        } else {
            $school['location_longitude'] = -1.123456;
            $school['location_latitude'] = 52.123456;
        }

        DB::table('property_school_establishments')->insert($school);

        $this->getJson('/api/v1/properties/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertJsonPath('data.nearby_schools.primary.0.slug', 'bousfield-primary-school')
            ->assertJsonPath('data.nearby_schools.primary.0.api_url', route('api.v1.schools.show', [
                'slug' => 'bousfield-primary-school',
            ]))
            ->assertJsonPath('data.nearby_schools.primary.0.website_url', route('schools.show', [
                'slug' => 'bousfield-primary-school',
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function landRegistryRow(): array
    {
        $row = [
            'TransactionID' => '23232323-2323-2323-2323-23232323232323',
            'Price' => 410000,
            'Date' => '2025-03-01 00:00:00',
            'Postcode' => 'AB1 2CD',
            'PAON' => '10',
            'Street' => 'MARKET ROAD',
            'PropertyType' => 'D',
            'NewBuild' => 'N',
            'Duration' => 'F',
            'Locality' => 'LOCAL',
            'TownCity' => 'TOWN',
            'District' => 'DISTRICT',
            'County' => 'COUNTY',
            'PPDCategoryType' => 'A',
        ];

        if (Schema::hasColumn('land_registry', 'YearDate')) {
            $row['YearDate'] = 2025;
        }

        return $row;
    }
}
