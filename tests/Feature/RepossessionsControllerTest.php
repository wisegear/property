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

        $this->ensureRepoTableExists();
        Cache::forget('repos:index:v1');
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
