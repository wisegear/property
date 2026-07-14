<?php

namespace Tests\Feature;

use App\Services\PropertyResearch\NearbySchoolsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class PropertyShowNearbySchoolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_property_with_coordinates_displays_nearby_schools_panel(): void
    {
        $this->insertProperty();
        $this->insertCoordinates();

        $this->mock(NearbySchoolsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forPoint')
                ->once()
                ->with('POINT(-1.123456 52.123456)')
                ->andReturn([
                    'primary' => collect([
                        $this->school('Alpha Primary', 0.2, '1'),
                    ]),
                    'secondary' => collect([
                        $this->school('Beta Secondary', 0.7, 'Good', highAge: 18),
                    ]),
                ]);
        });

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertSee('<details class="group mb-6 rounded border border-zinc-200 bg-white p-4 shadow-lg">', false)
            ->assertDontSee('<details open', false)
            ->assertSee('Nearby schools')
            ->assertSee('Show')
            ->assertSee('Hide')
            ->assertSee('Nearest open primary and secondary schools based on straight-line distance from this property.')
            ->assertSee('Primary schools')
            ->assertSee('Secondary schools')
            ->assertSee('md:grid-cols-2', false)
            ->assertSee('Alpha Primary')
            ->assertSee('Beta Secondary')
            ->assertSee('href="http://localhost/school/alpha-primary"', false)
            ->assertSee('href="http://localhost/school/beta-secondary"', false)
            ->assertViewHas('nearbySchools', function (array $nearbySchools): bool {
                return $nearbySchools['primary']->first()->establishment_name === 'Alpha Primary'
                    && $nearbySchools['secondary']->first()->establishment_name === 'Beta Secondary';
            });

        $cachedPayload = Cache::get('onspd:coords:pcds:AB1 2CD:v3');

        $this->assertIsArray($cachedPayload);
        $this->assertArrayHasKey('nearbySchools', $cachedPayload);
        $this->assertSame('Alpha Primary', $cachedPayload['nearbySchools']['primary']->first()->establishment_name);
    }

    public function test_primary_and_secondary_schools_appear_in_correct_sections_ordered_by_rating_then_distance(): void
    {
        $this->insertProperty();
        $this->insertCoordinates();

        $this->mock(NearbySchoolsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forPoint')
                ->once()
                ->andReturn([
                    'primary' => collect([
                        $this->school('Nearest Unrated Primary', 0.1, null),
                        $this->school('Requires Improvement Primary', 0.2, 'Requires improvement'),
                        $this->school('Further Good Primary', 0.9, 'Good'),
                        $this->school('Nearest Good Primary', 0.4, 'Good'),
                        $this->school('Further Outstanding Primary', 1.2, 'Outstanding'),
                    ]),
                    'secondary' => collect([
                        $this->school('Nearest Secondary', 0.3, null, highAge: 18),
                        $this->school('Further Secondary', 1.2, '4', highAge: 18),
                        $this->school('Good Secondary', 1.4, '2', highAge: 18),
                    ]),
                ]);
        });

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertSeeInOrder([
                'Primary schools',
                'Further Outstanding Primary',
                'Nearest Good Primary',
                'Further Good Primary',
                'Requires Improvement Primary',
                'Nearest Unrated Primary',
                'Secondary schools',
            ])
            ->assertSeeInOrder([
                'Secondary schools',
                'Good Secondary',
                'Further Secondary',
                'Nearest Secondary',
            ])
            ->assertDontSee('urn-nearest-primary')
            ->assertDontSee('urn-nearest-secondary');
    }

    public function test_ofsted_ratings_map_numeric_text_and_missing_values(): void
    {
        $this->insertProperty();
        $this->insertCoordinates();

        $this->mock(NearbySchoolsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forPoint')
                ->once()
                ->andReturn([
                    'primary' => collect([
                        $this->school('Numeric Outstanding Primary', 0.1, '1'),
                        $this->school('Text Good Primary', 0.2, 'Good'),
                        $this->school('Numeric Requires Improvement Primary', 0.3, '3'),
                        $this->school('Unrated Primary', 0.4, null),
                    ]),
                    'secondary' => collect([
                        $this->school('Text Inadequate Secondary', 0.5, 'Inadequate', highAge: 18),
                    ]),
                ]);
        });

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertSee('Outstanding')
            ->assertSee('Good')
            ->assertSee('Requires improvement')
            ->assertSee('Inadequate')
            ->assertSee('No current Ofsted rating')
            ->assertDontSee('Not yet rated')
            ->assertSee('bg-green-100', false)
            ->assertSee('bg-blue-100', false)
            ->assertSee('bg-amber-100', false)
            ->assertSee('bg-red-100', false)
            ->assertSee('bg-zinc-100', false);
    }

    public function test_property_without_coordinates_hides_the_nearby_schools_panel(): void
    {
        $this->insertProperty();
        $this->insertCoordinates(latitude: null, longitude: null);

        $this->mock(NearbySchoolsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forPoint')->never();
        });

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertDontSee('Nearby schools')
            ->assertDontSee('No nearby primary schools were found within 10 miles.');
    }

    public function test_empty_primary_or_secondary_results_show_the_correct_messages(): void
    {
        $this->insertProperty();
        $this->insertCoordinates();

        $this->mock(NearbySchoolsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forPoint')
                ->once()
                ->andReturn([
                    'primary' => collect(),
                    'secondary' => collect(),
                ]);
        });

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertSee('Nearby schools')
            ->assertSee('No nearby primary schools were found within 10 miles.')
            ->assertSee('No nearby secondary schools were found within 10 miles.');
    }

    public function test_school_details_render_in_the_compact_card_format(): void
    {
        $this->insertProperty();
        $this->insertCoordinates();

        $this->mock(NearbySchoolsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forPoint')
                ->once()
                ->andReturn([
                    'primary' => collect([
                        $this->school('Compact Primary', 0.43, 'Good', lowAge: 3, highAge: 11, latestInspectionDate: '2024-02-20'),
                    ]),
                    'secondary' => collect([
                        $this->school('No Date Secondary', 0.91, null, highAge: 18, latestInspectionDate: null),
                    ]),
                ]);
        });

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertSee('0.43 miles', false)
            ->assertSee('Ages 3–11')
            ->assertSee('Academy sponsor led')
            ->assertSee('Inspected 20 Feb 2024')
            ->assertSee('0.91 miles', false)
            ->assertSee('Ages 4–18')
            ->assertDontSee('Age range')
            ->assertDontSee('Establishment type')
            ->assertDontSee('Latest inspection date');
    }

    public function test_inspection_date_is_omitted_when_absent(): void
    {
        $this->insertProperty();
        $this->insertCoordinates();

        $this->mock(NearbySchoolsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forPoint')
                ->once()
                ->andReturn([
                    'primary' => collect([
                        $this->school('No Inspection Primary', 0.52, null, latestInspectionDate: null),
                    ]),
                    'secondary' => collect(),
                ]);
        });

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertSee('No Inspection Primary')
            ->assertDontSee('Inspected');
    }

    public function test_rating_summary_counts_displayed_ratings_and_omits_zero_count_categories(): void
    {
        $this->insertProperty();
        $this->insertCoordinates();

        $this->mock(NearbySchoolsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forPoint')
                ->once()
                ->andReturn([
                    'primary' => collect([
                        $this->school('Outstanding Primary', 0.1, 'Outstanding'),
                        $this->school('Good Primary One', 0.2, 'Good'),
                        $this->school('Good Primary Two', 0.3, '2'),
                        $this->school('Requires Improvement Primary', 0.4, '3'),
                        $this->school('Unrated Primary', 0.5, null),
                        $this->school('Hidden Primary', 0.6, 'Inadequate'),
                    ]),
                    'secondary' => collect([
                        $this->school('Inadequate Secondary', 0.7, '4', highAge: 18),
                        $this->school('Unrated Secondary', 0.8, '', highAge: 18),
                    ]),
                ]);
        });

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertSee('1 Outstanding · 2 Good · 1 Requires improvement · 1 Inadequate · 2 without a current rating')
            ->assertDontSee('2 Outstanding ·')
            ->assertDontSee('Hidden Primary');
    }

    public function test_rating_summary_omits_zero_count_rating_categories(): void
    {
        $this->insertProperty();
        $this->insertCoordinates();

        $this->mock(NearbySchoolsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forPoint')
                ->once()
                ->andReturn([
                    'primary' => collect([
                        $this->school('Only Good Primary', 0.2, 'Good'),
                    ]),
                    'secondary' => collect(),
                ]);
        });

        $this->get('/property/ab1-2cd-10-market-road')
            ->assertOk()
            ->assertSee('1 Good')
            ->assertDontSee('0 Outstanding')
            ->assertDontSee('0 Requires improvement')
            ->assertDontSee('0 Inadequate')
            ->assertDontSee('0 without a current rating');
    }

    public function test_wkt_is_passed_to_the_service_as_longitude_then_latitude(): void
    {
        $this->insertProperty();
        $this->insertCoordinates(latitude: 51.501111, longitude: -0.174222);

        $this->mock(NearbySchoolsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forPoint')
                ->once()
                ->with('POINT(-0.174222 51.501111)')
                ->andReturn([
                    'primary' => collect(),
                    'secondary' => collect(),
                ]);
        });

        $this->get('/property/ab1-2cd-10-market-road')->assertOk();
    }

    private function insertProperty(): void
    {
        DB::table('land_registry')->insert($this->landRegistryRow());
    }

    private function insertCoordinates(?float $latitude = 52.123456, ?float $longitude = -1.123456): void
    {
        DB::table('onspd_v2')->insert([
            'pcds' => 'AB1 2CD',
            'lat' => $latitude,
            'long' => $longitude,
        ]);
    }

    private function school(
        string $name,
        float $distanceMiles,
        mixed $ofstedRating,
        int $lowAge = 4,
        int $highAge = 11,
        ?string $latestInspectionDate = '2026-01-15',
    ): object {
        return (object) [
            'urn' => 'urn-'.str($name)->slug(),
            'establishment_name' => $name,
            'latest_ofsted_overall_effectiveness' => $ofstedRating,
            'distance_miles' => $distanceMiles,
            'age_range' => $lowAge.'-'.$highAge,
            'establishment_type' => 'Academy sponsor led',
            'latest_inspection_date' => $latestInspectionDate,
        ];
    }

    private function landRegistryRow(): array
    {
        $row = [
            'TransactionID' => '11111111-1111-1111-1111-111111111111',
            'Price' => 325000,
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
