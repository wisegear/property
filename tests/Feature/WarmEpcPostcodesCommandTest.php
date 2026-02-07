<?php

namespace Tests\Feature;

use App\Http\Controllers\EpcPostcodeController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarmEpcPostcodesCommandTest extends TestCase
{
    private ?string $originalPostcodeIndex = null;

    protected function setUp(): void
    {
        parent::setUp();

        $path = public_path('data/epc-postcodes.json');
        $this->originalPostcodeIndex = File::exists($path) ? File::get($path) : null;
    }

    protected function tearDown(): void
    {
        $path = public_path('data/epc-postcodes.json');
        File::ensureDirectoryExists(dirname($path));

        if ($this->originalPostcodeIndex === null) {
            if (File::exists($path)) {
                File::delete($path);
            }
        } else {
            File::put($path, $this->originalPostcodeIndex);
        }

        parent::tearDown();
    }

    public function test_command_warms_cache_for_all_indexed_postcodes(): void
    {
        Cache::flush();
        $this->ensureEpcTablesExist();
        DB::table('epc_certificates')->delete();
        DB::table('epc_certificates_scotland')->delete();

        DB::table('epc_certificates')->insert([
            'POSTCODE' => 'WR5 3EU',
            'INSPECTION_DATE' => '2022-01-01',
            'CURRENT_ENERGY_RATING' => 'C',
            'POTENTIAL_ENERGY_RATING' => 'B',
            'ADDRESS' => '1 Test Street',
            'LMK_KEY' => 'LMK-100',
        ]);

        DB::table('epc_certificates_scotland')->insert([
            'POSTCODE' => 'KA7 3XY',
            'INSPECTION_DATE' => '2022-01-01',
            'CURRENT_ENERGY_RATING' => 'C',
            'POTENTIAL_ENERGY_RATING' => 'B',
            'ADDRESS1' => '1 Scot Street',
            'REPORT_REFERENCE_NUMBER' => 'RRN-100',
        ]);

        $this->writePostcodeIndex([
            'england_wales' => ['WR5 3EU'],
            'scotland' => ['KA7 3XY'],
        ]);

        $this->artisan('epc:warm-postcodes')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has(EpcPostcodeController::cacheKey('england_wales', 'WR5 3EU')));
        $this->assertTrue(Cache::has(EpcPostcodeController::cacheKey('scotland', 'KA7 3XY')));
        $this->assertTrue(Cache::has('epc:postcode:last_warm'));
    }

    private function writePostcodeIndex(array $postcodes): void
    {
        File::ensureDirectoryExists(public_path('data'));
        File::put(
            public_path('data/epc-postcodes.json'),
            json_encode([
                'meta' => [
                    'generated_at' => now()->toIso8601String(),
                    'min_certificates' => 30,
                    'from_year' => 2015,
                ],
                'postcodes' => $postcodes,
            ], JSON_THROW_ON_ERROR)
        );
    }

    private function ensureEpcTablesExist(): void
    {
        if (! Schema::hasTable('epc_certificates')) {
            Schema::create('epc_certificates', function (Blueprint $table): void {
                $table->string('POSTCODE', 16)->nullable();
                $table->string('INSPECTION_DATE', 32)->nullable();
                $table->string('CURRENT_ENERGY_RATING', 8)->nullable();
                $table->string('POTENTIAL_ENERGY_RATING', 8)->nullable();
                $table->text('ADDRESS')->nullable();
                $table->string('LMK_KEY', 128)->nullable();
            });
        }

        foreach ([
            'POSTCODE' => fn (Blueprint $table) => $table->string('POSTCODE', 16)->nullable(),
            'INSPECTION_DATE' => fn (Blueprint $table) => $table->string('INSPECTION_DATE', 32)->nullable(),
            'CURRENT_ENERGY_RATING' => fn (Blueprint $table) => $table->string('CURRENT_ENERGY_RATING', 8)->nullable(),
            'POTENTIAL_ENERGY_RATING' => fn (Blueprint $table) => $table->string('POTENTIAL_ENERGY_RATING', 8)->nullable(),
            'ADDRESS' => fn (Blueprint $table) => $table->text('ADDRESS')->nullable(),
            'LMK_KEY' => fn (Blueprint $table) => $table->string('LMK_KEY', 128)->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('epc_certificates', $column)) {
                Schema::table('epc_certificates', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }

        if (! Schema::hasTable('epc_certificates_scotland')) {
            Schema::create('epc_certificates_scotland', function (Blueprint $table): void {
                $table->string('POSTCODE', 16)->nullable();
                $table->string('INSPECTION_DATE', 32)->nullable();
                $table->string('CURRENT_ENERGY_RATING', 8)->nullable();
                $table->string('POTENTIAL_ENERGY_RATING', 8)->nullable();
                $table->text('ADDRESS1')->nullable();
                $table->string('REPORT_REFERENCE_NUMBER', 128)->nullable();
            });
        }

        foreach ([
            'POSTCODE' => fn (Blueprint $table) => $table->string('POSTCODE', 16)->nullable(),
            'INSPECTION_DATE' => fn (Blueprint $table) => $table->string('INSPECTION_DATE', 32)->nullable(),
            'CURRENT_ENERGY_RATING' => fn (Blueprint $table) => $table->string('CURRENT_ENERGY_RATING', 8)->nullable(),
            'POTENTIAL_ENERGY_RATING' => fn (Blueprint $table) => $table->string('POTENTIAL_ENERGY_RATING', 8)->nullable(),
            'ADDRESS1' => fn (Blueprint $table) => $table->text('ADDRESS1')->nullable(),
            'REPORT_REFERENCE_NUMBER' => fn (Blueprint $table) => $table->string('REPORT_REFERENCE_NUMBER', 128)->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('epc_certificates_scotland', $column)) {
                Schema::table('epc_certificates_scotland', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }
}
