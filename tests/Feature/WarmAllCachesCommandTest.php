<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarmAllCachesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_warm_all_commands_build_caches(): void
    {
        if (! Schema::hasTable('land_registry')) {
            Schema::create('land_registry', function (Blueprint $table): void {
                $table->string('Locality')->nullable();
                $table->string('District')->nullable();
                $table->string('TownCity')->nullable();
                $table->string('County')->nullable();
                $table->string('PPDCategoryType')->nullable();
                $table->unsignedSmallInteger('YearDate')->nullable();
                $table->unsignedInteger('Price')->nullable();
                $table->string('PropertyType')->nullable();
            });
        }

        foreach ([
            'Locality',
            'District',
            'TownCity',
            'County',
            'PPDCategoryType',
            'YearDate',
            'Price',
            'PropertyType',
        ] as $column) {
            if (! Schema::hasColumn('land_registry', $column)) {
                Schema::table('land_registry', function (Blueprint $table) use ($column): void {
                    match ($column) {
                        'YearDate' => $table->unsignedSmallInteger('YearDate')->nullable(),
                        'Price' => $table->unsignedInteger('Price')->nullable(),
                        default => $table->string($column)->nullable(),
                    };
                });
            }
        }

        DB::table('land_registry')->insert([
            'Locality' => ' Testville ',
            'District' => ' Test District ',
            'TownCity' => ' Test Town ',
            'County' => ' Test County ',
            'PPDCategoryType' => 'A',
            'YearDate' => 2024,
            'Price' => 250000,
            'PropertyType' => 'D',
        ]);

        Cache::flush();

        Artisan::call('reports:warm-localities', ['--ppd' => 'A', '--limit' => 1, '--only' => 'all']);
        Artisan::call('reports:warm-districts', ['--ppd' => 'A', '--limit' => 1, '--only' => 'all']);
        Artisan::call('reports:warm-towns', ['--ppd' => 'A', '--limit' => 1, '--only' => 'all']);
        Artisan::call('reports:warm-counties', ['--ppd' => 'A', '--limit' => 1, '--only' => 'all']);

        $locality = 'Testville';
        $district = 'Test District';
        $town = 'Test Town';
        $county = 'Test County';

        $this->assertNotNull(Cache::get('locality:priceHistory:v2:catA:'.$locality));
        $this->assertNotNull(Cache::get('locality:salesHistory:v2:catA:'.$locality));
        $this->assertNotNull(Cache::get('locality:types:v2:catA:'.$locality));

        $this->assertNotNull(Cache::get('district:priceHistory:v2:catA:'.$district));
        $this->assertNotNull(Cache::get('district:salesHistory:v2:catA:'.$district));
        $this->assertNotNull(Cache::get('district:types:v2:catA:'.$district));

        $this->assertNotNull(Cache::get('town:priceHistory:v2:catA:'.$town));
        $this->assertNotNull(Cache::get('town:salesHistory:v2:catA:'.$town));
        $this->assertNotNull(Cache::get('town:types:v2:catA:'.$town));

        $this->assertNotNull(Cache::get('county:priceHistory:v2:catA:'.$county));
        $this->assertNotNull(Cache::get('county:salesHistory:v2:catA:'.$county));
        $this->assertNotNull(Cache::get('county:types:v2:catA:'.$county));
    }
}
