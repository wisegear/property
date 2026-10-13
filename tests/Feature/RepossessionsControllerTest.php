<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RepossessionsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $this->ensureRepoTableExists();
        Cache::flush();
    }

    public function test_repossessions_index_builds_payload_with_trimmed_local_authorities(): void
    {
        DB::table('repo_la_quarterlies')->insert([
            [
                'year' => 2024,
                'quarter' => 'Q4',
                'possession_type' => 'Mortgage',
                'possession_action' => 'Claims',
                'la_code' => 'E06000001',
                'local_authority' => 'Birmingham ',
                'county_ua' => 'West Midlands',
                'region' => 'West Midlands',
                'value' => 10,
            ],
            [
                'year' => 2025,
                'quarter' => 'Q1',
                'possession_type' => 'Mortgage',
                'possession_action' => 'Claims',
                'la_code' => 'E06000001',
                'local_authority' => 'Birmingham',
                'county_ua' => 'West Midlands',
                'region' => 'West Midlands',
                'value' => 12,
            ],
            [
                'year' => 2025,
                'quarter' => 'Q1',
                'possession_type' => 'Social_Landlord',
                'possession_action' => 'Orders',
                'la_code' => 'E06000002',
                'local_authority' => 'Leeds',
                'county_ua' => 'West Yorkshire',
                'region' => 'Yorkshire and The Humber',
                'value' => 8,
            ],
        ]);

        $response = $this->get(route('repossessions.index', absolute: false));

        $response->assertOk();
        $response->assertSee('Repossession Actions');
        $response->assertSee('Birmingham');
        $response->assertSee('-mx-6 -mt-6', false);
        $response->assertSee('borderRadius: 0', false);
        $response->assertSee("k === 'Mortgage' ? 'Lender'", false);
        $response->assertSee('Types: Accelerated Landlord, Lender, Private Landlord, Social Landlord.');

        $rows = collect($response->viewData('la_breakdown_rows'));
        $this->assertSame('Birmingham', $rows->first()['local_authority']);
    }

    public function test_local_authority_route_resolves_slug_against_trimmed_name(): void
    {
        DB::table('repo_la_quarterlies')->insert([
            [
                'year' => 2024,
                'quarter' => 'Q4',
                'possession_type' => 'Mortgage',
                'possession_action' => 'Claims',
                'la_code' => 'E06000001',
                'local_authority' => 'Birmingham ',
                'county_ua' => 'West Midlands',
                'region' => 'West Midlands',
                'value' => 11,
            ],
            [
                'year' => 2025,
                'quarter' => 'Q1',
                'possession_type' => 'Private_Landlord',
                'possession_action' => 'Orders',
                'la_code' => 'E06000001',
                'local_authority' => 'Birmingham',
                'county_ua' => 'West Midlands',
                'region' => 'West Midlands',
                'value' => 9,
            ],
        ]);

        $response = $this->get(route('repossessions.local-authority', ['slug' => 'birmingham'], absolute: false));

        $response->assertOk();
        $response->assertSee('Birmingham');
        $response->assertSee('-mx-6 -mt-6', false);
        $response->assertSee('borderRadius: 0', false);
        $response->assertSee("k === 'Mortgage' ? 'Lender'", false);
        $response->assertSee('Types: Accelerated Landlord, Lender, Private Landlord, Social Landlord.');
    }

    public function test_header_shows_latest_quarter_even_when_dashboard_cache_is_warm(): void
    {
        $row = [
            'year' => 2025,
            'quarter' => 'Q4',
            'possession_type' => 'Mortgage',
            'possession_action' => 'Claims',
            'la_code' => 'E08000025',
            'local_authority' => 'Birmingham',
            'county_ua' => 'Birmingham',
            'region' => 'West Midlands',
            'value' => 10,
        ];
        DB::table('repo_la_quarterlies')->insert($row);
        $this->get('/repossessions')->assertOk()->assertSee('Data through Q4 2025');

        DB::table('repo_la_quarterlies')->insert(array_replace($row, ['year' => 2026, 'quarter' => 'Q1']));
        $this->get('/repossessions')->assertOk()->assertSee('Data through Q1 2026')
            ->assertDontSee('Next update January 2026')->assertDontSee('Data from 2003 to 2025');
        $this->get('/repossessions/local-authority/birmingham')->assertOk()->assertSee('Data through Q1 2026');
    }

    public function test_header_handles_an_empty_dataset(): void
    {
        $this->get('/repossessions')->assertOk()->assertSee('No data available');
    }

    public function test_claims_comparison_and_rankings_exclude_other_stages_and_historical_activity(): void
    {
        $row = [
            'year' => 2025, 'quarter' => 'Q1', 'possession_type' => 'Mortgage',
            'possession_action' => 'Claims', 'la_code' => 'E08000025',
            'local_authority' => 'Birmingham', 'county_ua' => 'Birmingham',
            'region' => 'West Midlands', 'value' => 100,
        ];
        DB::table('repo_la_quarterlies')->insert([
            $row,
            array_replace($row, ['quarter' => 'Q4', 'value' => 1000]),
            array_replace($row, ['year' => 2026, 'value' => 80]),
            array_replace($row, ['year' => 2026, 'possession_action' => 'Warrants', 'value' => 500]),
            array_replace($row, ['year' => 2026, 'possession_action' => 'Repossessions', 'value' => 20]),
            array_replace($row, ['year' => 2026, 'local_authority' => 'Leeds', 'la_code' => 'E08000035', 'value' => 90]),
            array_replace($row, ['year' => 2026, 'local_authority' => 'Not Identified', 'la_code' => '1000', 'value' => 10000]),
        ]);
        $response = $this->get('/repossessions')->assertOk();
        $this->assertSame(10170, $response->viewData('latest')->total);
        $this->assertSame(100, $response->viewData('previous')->total);
        $this->assertSame(10070, $response->viewData('yoy'));
        $rows = $response->viewData('la_breakdown_rows');
        $this->assertSame('Leeds', $rows->first()['local_authority']);
        $this->assertSame(80, $rows->last()['total']);
        $response->assertSee('2026 is a partial year through Q1');
        $localResponse = $this->get('/repossessions/local-authority/birmingham')->assertOk();
        $this->assertSame(80, $localResponse->viewData('latest')->total);
        $this->assertSame(100, $localResponse->viewData('previous')->total);
        $this->assertSame(-20, $localResponse->viewData('yoy'));
    }

    public function test_missing_matching_quarter_does_not_produce_a_year_on_year_change(): void
    {
        $row = [
            'year' => 2025, 'quarter' => 'Q4', 'possession_type' => 'Mortgage',
            'possession_action' => 'Claims', 'la_code' => 'E08000025',
            'local_authority' => 'Birmingham', 'county_ua' => 'Birmingham',
            'region' => 'West Midlands', 'value' => 100,
        ];
        DB::table('repo_la_quarterlies')->insert([$row, array_replace($row, ['year' => 2026, 'quarter' => 'Q1'])]);
        $response = $this->get('/repossessions')->assertOk();
        $this->assertNull($response->viewData('previous'));
        $this->assertNull($response->viewData('yoy'));
        $response->assertSee('Matching prior-year data is unavailable');
    }

    protected function ensureRepoTableExists(): void
    {
        if (! Schema::hasTable('repo_la_quarterlies')) {
            Schema::create('repo_la_quarterlies', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year');
                $table->string('quarter', 2);
                $table->string('possession_type', 64);
                $table->string('possession_action', 32);
                $table->string('la_code', 12);
                $table->string('local_authority', 128);
                $table->string('county_ua', 128);
                $table->string('region', 64);
                $table->unsignedInteger('value');
                $table->timestamps();
            });
        }
    }
}
