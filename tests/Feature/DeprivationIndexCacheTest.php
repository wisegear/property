<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeprivationIndexCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_scotland_top_bottom_cache_refreshes_when_empty(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Cache refresh test only runs on PostgreSQL.');
        }

        Schema::create('simd2020', function (Blueprint $table): void {
            $table->text('Data_Zone')->nullable();
            $table->text('Intermediate_Zone')->nullable();
            $table->text('Council_area')->nullable();
            $table->text('SIMD2020v2_Decile')->nullable();
            $table->text('SIMD2020v2_Rank')->nullable();
        });

        Schema::create('imd2025', function (Blueprint $table): void {
            $table->text('LSOA_Code_2021')->nullable();
            $table->text('LSOA_Name_2021')->nullable();
            $table->text('Index_of_Multiple_Deprivation_Rank')->nullable();
            $table->text('Index_of_Multiple_Deprivation_Decile')->nullable();
        });

        Schema::create('wimd2019', function (Blueprint $table): void {
            $table->text('LSOA_code')->nullable();
            $table->text('LSOA_name')->nullable();
            $table->text('WIMD_2019')->nullable();
        });

        Schema::create('ni_deprivation', function (Blueprint $table): void {
            $table->text('SA2011')->nullable();
            $table->text('SOA2001name')->nullable();
            $table->text('MDM_rank')->nullable();
        });

        \DB::table('simd2020')->insert([
            'Data_Zone' => 'S01000001',
            'Intermediate_Zone' => 'Zone One',
            'Council_area' => 'Council One',
            'SIMD2020v2_Decile' => '5',
            'SIMD2020v2_Rank' => '12',
        ]);

        Cache::put('imd25:top10', collect([]), now()->addMinutes(5));
        Cache::put('imd25:bottom10', collect([]), now()->addMinutes(5));
        Cache::put('simd:top10', collect([]), now()->addMinutes(5));
        Cache::put('simd:bottom10', collect([]), now()->addMinutes(5));
        Cache::put('wimd:top10', collect([(object) ['lsoa_name' => 'Wales', 'lsoa_code' => 'W010', 'rank' => 1, 'decile' => 1]]), now()->addMinutes(5));
        Cache::put('wimd:bottom10', collect([(object) ['lsoa_name' => 'Wales', 'lsoa_code' => 'W010', 'rank' => 1, 'decile' => 1]]), now()->addMinutes(5));
        Cache::put('nimdm:top10', collect([(object) ['sa_name' => 'NI', 'sa_code' => 'SA1', 'rank' => 1, 'decile' => 1]]), now()->addMinutes(5));
        Cache::put('nimdm:bottom10', collect([(object) ['sa_name' => 'NI', 'sa_code' => 'SA1', 'rank' => 1, 'decile' => 1]]), now()->addMinutes(5));
        Cache::put('imd25.total_rank', 33755);
        Cache::put('simd.total_rank', 6976);
        Cache::put('wimd.total_rank', 1909);
        Cache::put('nimdm.total_rank', 4537);

        $response = $this->get(route('deprivation.index', absolute: false));

        $response->assertOk();
        $response->assertSee('Zone One');
    }
}
