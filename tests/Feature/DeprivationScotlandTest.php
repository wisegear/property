<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class DeprivationScotlandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    }

    public function test_scotland_show_handles_comma_separated_rank_values(): void
    {
        Cache::forget('simd.total_rank');

        $scotlandRowQuery = Mockery::mock();
        $scotlandRowQuery->shouldReceive('where')
            ->once()
            ->with('data_zone', 'S01008861')
            ->andReturnSelf();
        $scotlandRowQuery->shouldReceive('orderBy')
            ->once()
            ->with('postcode')
            ->andReturnSelf();
        $scotlandRowQuery->shouldReceive('first')
            ->once()
            ->andReturn((object) [
                'postcode' => 'EH1 1AA',
                'data_zone' => 'S01008861',
                'Council_area' => 'City of Edinburgh',
                'Intermediate_Zone' => 'Old Town',
                'rank' => '3,500',
                'decile' => '6',
                'income_rank' => '3,100',
                'employment_rank' => '3,200',
                'health_rank' => '3,300',
                'education_rank' => '3,400',
                'access_rank' => '3,500',
                'crime_rank' => '3,600',
                'housing_rank' => '3,700',
                'lat' => 55.9533,
                'long' => -3.1883,
            ]);

        $simdTotalQuery = Mockery::mock();
        $simdTotalQuery->shouldReceive('selectRaw')
            ->once()
            ->with("MAX(CAST(NULLIF(REPLACE(\"SIMD2020v2_Rank\", ',', ''), '') AS INTEGER)) as max_rank")
            ->andReturnSelf();
        $simdTotalQuery->shouldReceive('first')
            ->once()
            ->andReturn((object) ['max_rank' => 6976]);

        DB::shouldReceive('table')
            ->once()
            ->with('v_postcode_deprivation_scotland')
            ->andReturn($scotlandRowQuery);

        DB::shouldReceive('table')
            ->once()
            ->with('simd2020')
            ->andReturn($simdTotalQuery);

        $response = $this->get(route('deprivation.scot.show', ['dz' => 'S01008861']));

        $response->assertStatus(200);
        $response->assertSee('Rank: 3,500 of 6,976');
    }

    public function test_scotland_domain_decile_badges_follow_hero_colour_thresholds(): void
    {
        Cache::forget('simd.total_rank');

        $scotlandRowQuery = Mockery::mock();
        $scotlandRowQuery->shouldReceive('where')
            ->once()
            ->with('data_zone', 'S01012068')
            ->andReturnSelf();
        $scotlandRowQuery->shouldReceive('orderBy')
            ->once()
            ->with('postcode')
            ->andReturnSelf();
        $scotlandRowQuery->shouldReceive('first')
            ->once()
            ->andReturn((object) [
                'postcode' => 'PA3 1AA',
                'data_zone' => 'S01012068',
                'Council_area' => 'Renfrewshire',
                'Intermediate_Zone' => 'Paisley Ferguslie',
                'rank' => '4',
                'decile' => '1',
                'income_rank' => '4',
                'employment_rank' => '2800',
                'health_rank' => '6200',
                'education_rank' => '5',
                'access_rank' => '5622',
                'crime_rank' => '376',
                'housing_rank' => '514',
                'lat' => 55.8489,
                'long' => -4.4291,
            ]);

        $simdTotalQuery = Mockery::mock();
        $simdTotalQuery->shouldReceive('selectRaw')
            ->once()
            ->with("MAX(CAST(NULLIF(REPLACE(\"SIMD2020v2_Rank\", ',', ''), '') AS INTEGER)) as max_rank")
            ->andReturnSelf();
        $simdTotalQuery->shouldReceive('first')
            ->once()
            ->andReturn((object) ['max_rank' => 6976]);

        DB::shouldReceive('table')
            ->once()
            ->with('v_postcode_deprivation_scotland')
            ->andReturn($scotlandRowQuery);

        DB::shouldReceive('table')
            ->once()
            ->with('simd2020')
            ->andReturn($simdTotalQuery);

        $response = $this->get(route('deprivation.scot.show', ['dz' => 'S01012068']));

        $response->assertOk();
        $response->assertSee('Decile 1');
        $response->assertSee('Decile 5');
        $response->assertSee('Decile 9');
        $response->assertSee('bg-rose-100 text-rose-800 ring-1 ring-inset ring-rose-200', false);
        $response->assertSee('bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-200', false);
        $response->assertSee('bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200', false);
    }
}
