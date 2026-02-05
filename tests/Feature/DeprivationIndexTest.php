<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeprivationIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->ensureDeprivationTablesExist();
    }

    public function test_index_loads_with_simd_rank_casting_queries(): void
    {
        DB::table('imd2025')->insert([
            'LSOA_Code_2021' => 'E01000001',
            'LSOA_Name_2021' => 'Area One',
            'Index_of_Multiple_Deprivation_Rank' => 1,
            'Index_of_Multiple_Deprivation_Decile' => 1,
        ]);

        DB::table('wimd2019')->insert([
            'LSOA_code' => 'W01000001',
            'LSOA_name' => 'Wales Area',
            'WIMD_2019' => 100,
        ]);

        DB::table('ni_deprivation')->insert([
            'SA2011' => 'N0000001',
            'SOA2001name' => 'NI Area',
            'MDM_rank' => 100,
        ]);

        DB::table('simd2020')->insert([
            'Data_Zone' => 'S01000001',
            'Intermediate_Zone' => 'IZ One',
            'Council_area' => 'Council One',
            'SIMD2020v2_Decile' => '2',
            'SIMD2020v2_Rank' => '1,234',
        ]);

        $response = $this->get(route('deprivation.index', absolute: false));

        $response->assertOk();
        $response->assertSee('IZ One');
        $response->assertSee('Wales Area');
        $response->assertSee('NI Area');
    }

    public function test_index_rebuilds_england_lists_when_cached_values_are_empty(): void
    {
        DB::table('imd2025')->insert([
            'LSOA_Code_2021' => 'E01000042',
            'LSOA_Name_2021' => 'England Area',
            'Index_of_Multiple_Deprivation_Rank' => 33755,
            'Index_of_Multiple_Deprivation_Decile' => 10,
        ]);

        DB::table('wimd2019')->insert([
            'LSOA_code' => 'W01000001',
            'LSOA_name' => 'Wales Area',
            'WIMD_2019' => 100,
        ]);

        DB::table('ni_deprivation')->insert([
            'SA2011' => 'N0000001',
            'SOA2001name' => 'NI Area',
            'MDM_rank' => 100,
        ]);

        DB::table('simd2020')->insert([
            'Data_Zone' => 'S01000001',
            'Intermediate_Zone' => 'IZ One',
            'Council_area' => 'Council One',
            'SIMD2020v2_Decile' => '2',
            'SIMD2020v2_Rank' => '1,234',
        ]);

        Cache::put('imd25:top10', collect());
        Cache::put('imd25:bottom10', collect());

        $response = $this->get(route('deprivation.index', absolute: false));

        $response->assertOk();
        $response->assertSee('England Area');
    }

    protected function ensureDeprivationTablesExist(): void
    {
        if (! Schema::hasTable('imd2025')) {
            Schema::create('imd2025', function (Blueprint $table) {
                $table->id();
                $table->string('LSOA_Code_2021')->nullable();
                $table->string('LSOA_Name_2021')->nullable();
                $table->integer('Index_of_Multiple_Deprivation_Rank')->nullable();
                $table->integer('Index_of_Multiple_Deprivation_Decile')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wimd2019')) {
            Schema::create('wimd2019', function (Blueprint $table) {
                $table->id();
                $table->string('LSOA_code')->nullable();
                $table->string('LSOA_name')->nullable();
                $table->integer('WIMD_2019')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ni_deprivation')) {
            Schema::create('ni_deprivation', function (Blueprint $table) {
                $table->id();
                $table->string('SA2011')->nullable();
                $table->string('SOA2001name')->nullable();
                $table->integer('MDM_rank')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('simd2020')) {
            Schema::create('simd2020', function (Blueprint $table) {
                $table->id();
                $table->string('Data_Zone')->nullable();
                $table->string('Intermediate_Zone')->nullable();
                $table->string('Council_area')->nullable();
                $table->string('SIMD2020v2_Decile')->nullable();
                $table->string('SIMD2020v2_Rank')->nullable();
                $table->timestamps();
            });
        }
    }
}
