<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HeatmapWarmCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_heatmap_warm_command_builds_cache_points(): void
    {
        if (! Schema::hasTable('land_registry')) {
            Schema::create('land_registry', function (Blueprint $table): void {
                $table->string('Postcode')->nullable();
                $table->string('PPDCategoryType')->nullable();
            });
        }

        if (! Schema::hasColumn('land_registry', 'Postcode')) {
            Schema::table('land_registry', function (Blueprint $table): void {
                $table->string('Postcode')->nullable();
            });
        }

        if (! Schema::hasColumn('land_registry', 'PPDCategoryType')) {
            Schema::table('land_registry', function (Blueprint $table): void {
                $table->string('PPDCategoryType')->nullable();
            });
        }

        if (! Schema::hasTable('onspd')) {
            Schema::create('onspd', function (Blueprint $table): void {
                $table->string('pcds');
                $table->string('lsoa21')->nullable();
                $table->string('lsoa11')->nullable();
                $table->decimal('lat', 10, 6)->nullable();
                $table->decimal('long', 10, 6)->nullable();
            });
        }

        if (! Schema::hasColumn('onspd', 'pcds')) {
            Schema::table('onspd', function (Blueprint $table): void {
                $table->string('pcds');
            });
        }

        if (! Schema::hasColumn('onspd', 'lsoa21')) {
            Schema::table('onspd', function (Blueprint $table): void {
                $table->string('lsoa21')->nullable();
            });
        }

        if (! Schema::hasColumn('onspd', 'lsoa11')) {
            Schema::table('onspd', function (Blueprint $table): void {
                $table->string('lsoa11')->nullable();
            });
        }

        if (! Schema::hasColumn('onspd', 'lat')) {
            Schema::table('onspd', function (Blueprint $table): void {
                $table->decimal('lat', 10, 6)->nullable();
            });
        }

        if (! Schema::hasColumn('onspd', 'long')) {
            Schema::table('onspd', function (Blueprint $table): void {
                $table->decimal('long', 10, 6)->nullable();
            });
        }

        if (! Schema::hasTable('lsoa_2011_to_2021')) {
            Schema::create('lsoa_2011_to_2021', function (Blueprint $table): void {
                $table->string('LSOA11CD');
                $table->string('LSOA11NM');
                $table->string('LSOA21CD');
                $table->string('LSOA21NM');
            });
        }

        if (! Schema::hasColumn('lsoa_2011_to_2021', 'LSOA11CD')) {
            Schema::table('lsoa_2011_to_2021', function (Blueprint $table): void {
                $table->string('LSOA11CD');
            });
        }

        if (! Schema::hasColumn('lsoa_2011_to_2021', 'LSOA11NM')) {
            Schema::table('lsoa_2011_to_2021', function (Blueprint $table): void {
                $table->string('LSOA11NM')->nullable();
            });
        }

        if (! Schema::hasColumn('lsoa_2011_to_2021', 'LSOA21CD')) {
            Schema::table('lsoa_2011_to_2021', function (Blueprint $table): void {
                $table->string('LSOA21CD');
            });
        }

        if (! Schema::hasColumn('lsoa_2011_to_2021', 'LSOA21NM')) {
            Schema::table('lsoa_2011_to_2021', function (Blueprint $table): void {
                $table->string('LSOA21NM')->nullable();
            });
        }

        if (! Schema::hasTable('lsoa21_ruc_geo')) {
            Schema::create('lsoa21_ruc_geo', function (Blueprint $table): void {
                $table->string('LSOA21CD');
                $table->string('LSOA21NM');
                $table->decimal('LAT', 10, 6)->nullable();
                $table->decimal('LONG', 10, 6)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('lsoa21_ruc_geo', 'LSOA21CD')) {
            Schema::table('lsoa21_ruc_geo', function (Blueprint $table): void {
                $table->string('LSOA21CD');
            });
        }

        if (! Schema::hasColumn('lsoa21_ruc_geo', 'LSOA21NM')) {
            Schema::table('lsoa21_ruc_geo', function (Blueprint $table): void {
                $table->string('LSOA21NM')->nullable();
            });
        }

        if (! Schema::hasColumn('lsoa21_ruc_geo', 'LAT')) {
            Schema::table('lsoa21_ruc_geo', function (Blueprint $table): void {
                $table->decimal('LAT', 10, 6);
            });
        }

        if (! Schema::hasColumn('lsoa21_ruc_geo', 'LONG')) {
            Schema::table('lsoa21_ruc_geo', function (Blueprint $table): void {
                $table->decimal('LONG', 10, 6);
            });
        }

        if (! Schema::hasColumn('lsoa21_ruc_geo', 'created_at')) {
            Schema::table('lsoa21_ruc_geo', function (Blueprint $table): void {
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasColumn('lsoa21_ruc_geo', 'updated_at')) {
            Schema::table('lsoa21_ruc_geo', function (Blueprint $table): void {
                $table->timestamp('updated_at')->nullable();
            });
        }

        DB::table('land_registry')->insert([
            'Postcode' => 'AB1 2CD',
            'PPDCategoryType' => 'A',
        ]);

        DB::table('onspd')->insert([
            'pcds' => 'AB12CD',
            'lsoa21' => null,
            'lsoa11' => 'E01000001',
            'lat' => 51.501,
            'long' => -0.142,
        ]);

        DB::table('lsoa_2011_to_2021')->insert([
            'LSOA11CD' => 'E01000001',
            'LSOA11NM' => 'Test LSOA 2011',
            'LSOA21CD' => 'E01000001',
            'LSOA21NM' => 'Test LSOA 2021',
        ]);

        $geoRow = [
            'LSOA21CD' => 'E01000001',
            'LSOA21NM' => 'Test LSOA 2021',
            'LAT' => 51.501,
            'LONG' => -0.142,
        ];

        if (Schema::hasColumn('lsoa21_ruc_geo', 'created_at')) {
            $geoRow['created_at'] = now();
        }

        if (Schema::hasColumn('lsoa21_ruc_geo', 'updated_at')) {
            $geoRow['updated_at'] = now();
        }

        DB::table('lsoa21_ruc_geo')->insert($geoRow);

        Cache::flush();

        Artisan::call('property:heatmap-warm', ['--force' => true]);

        $points = Cache::get('land_registry_heatmap:lsoa21:v2');

        $this->assertNotNull($points);
        $this->assertCount(1, $points);
    }
}
