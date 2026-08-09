<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchoolIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_index_displays_cached_national_statistics_and_school_landscape(): void
    {
        $this->insertSchool('100001', 'Oak Primary', 'Primary', 'Westminster', 'SW1A 1AA', '1');
        $this->insertSchool('100002', 'River Secondary', 'Secondary', 'Camden', 'NW1 2DB', '2');
        $this->insertSchool('100003', 'Hill Special School', 'Special', 'Camden', 'NW1 3AB');
        $this->insertSchool('100004', 'New Academy', 'Primary', 'Camden', 'NW1 4AB', hasOfstedRecord: true);
        $this->insertSchool('100005', 'Not Judged Academy', 'Secondary', 'Camden', 'NW1 5AB', 'Not judged');
        $this->insertSchool('100006', 'Welsh Example School', 'Primary', 'Cardiff', 'CF10 1AA', establishmentType: 'Welsh establishment');

        $this->get('/schools/england')
            ->assertOk()
            ->assertSee('Schools in England')
            ->assertSee('Schools (England)')
            ->assertSee('href="'.route('schools.index').'"', false)
            ->assertDontSee('placeholder="Search by school name or postcode"', false)
            ->assertSee('Ofsted rating distribution')
            ->assertSee('School landscape')
            ->assertDontSee('Example schools A–Z')
            ->assertSee('Not judged')
            ->assertSee('No current overall grade')
            ->assertSee('means Ofsted explicitly records no current graded overall judgement.')
            ->assertSee('overall-effectiveness field is blank')
            ->assertSee('1 other open establishment')
            ->assertViewHas('dashboard', function (array $dashboard): bool {
                $ratings = collect($dashboard['ratings'])->keyBy('value');

                return $dashboard['total'] === 4
                    && $dashboard['excluded'] === 1
                    && $ratings['not_judged']['count'] === 1
                    && $ratings['no_grade']['count'] === 1;
            });

        $this->assertTrue(Cache::has('schools:index:dashboard:v7'));
        $this->assertTrue(Cache::has('schools:index:filters:v7'));
    }

    public function test_search_matches_partial_names_and_postcode_prefixes_with_canonical_links(): void
    {
        $this->insertSchool('100001', 'Oak Primary School', 'Primary', 'Westminster', 'SW1A 1AA', '1');
        $this->insertSchool('100002', 'Oak Primary School', 'Primary', 'Camden', 'NW1 2DB', '2');

        $this->getJson('/schools/england/search?q=Oak%20Primary')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Oak Primary School')
            ->assertJsonPath('data.0.url', route('schools.show', ['slug' => 'oak-primary-school-100001']))
            ->assertJsonPath('data.0.rating', 'Outstanding');

        $this->getJson('/schools/england/search?q=NW1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.postcode', 'NW1 2DB');
    }

    public function test_search_supports_filters_and_caps_results(): void
    {
        foreach (range(1, 23) as $index) {
            $this->insertSchool(
                (string) (200000 + $index),
                'Example School '.$index,
                $index === 1 ? 'Secondary' : 'Primary',
                $index === 1 ? 'Camden' : 'Westminster',
                'SW1A 1AA',
                $index === 1 ? '3' : '2',
            );
        }

        $this->getJson('/schools/england/search?q=Example')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('pagination.total', 23)
            ->assertJsonPath('pagination.last_page', 2);

        $this->getJson('/schools/england/search?q=Example&page=2')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('pagination.current_page', 2);

        $this->getJson('/schools/england/search?rating=3&phase=Secondary&local_authority=Camden')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Example School 1')
            ->assertJsonPath('data.0.rating', 'Requires improvement');
    }

    public function test_welsh_establishments_are_excluded_from_search_and_filters(): void
    {
        $this->insertSchool('300001', 'Cardiff Example School', 'Primary', 'Cardiff', 'CF10 1AA', establishmentType: 'Welsh establishment');
        $this->insertSchool('300003', 'Vale Specialist College', '16 plus', 'Vale of Glamorgan', 'CF62 1AA', establishmentType: 'Special post 16 institution', gsslaCode: 'W06000014');
        $this->insertSchool('300002', 'English Example School', 'Primary', 'Bristol, City of', 'BS1 1AA', '2');

        $this->getJson('/schools/england/search?q=Example')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'English Example School');

        $this->get('/schools/england')
            ->assertOk()
            ->assertDontSee('Cardiff Example School')
            ->assertDontSee('<option value="Cardiff">Cardiff</option>', false);
    }

    public function test_legacy_schools_url_redirects_to_england_dashboard(): void
    {
        $this->get('/schools')->assertRedirect('/schools/england')->assertStatus(301);
    }

    private function insertSchool(
        string $urn,
        string $name,
        string $phase,
        string $localAuthority,
        string $postcode,
        ?string $rating = null,
        bool $hasOfstedRecord = false,
        string $establishmentType = 'Academy converter',
        ?string $gsslaCode = null,
    ): void {
        DB::table('property_school_establishments')->insert([
            'urn' => $urn,
            'establishment_name' => $name,
            'establishment_status_code' => '1',
            'phase_of_education_name' => $phase,
            'type_of_establishment_name' => $establishmentType,
            'town' => 'London',
            'la_name' => $localAuthority,
            'postcode' => $postcode,
            'gssla_code' => $gsslaCode,
        ]);

        if ($rating !== null || $hasOfstedRecord) {
            DB::table('property_schools')->insert([
                'urn' => (int) $urn,
                'school_name' => $name,
                'latest_oeif_overall_effectiveness' => $rating,
            ]);
        }
    }
}
