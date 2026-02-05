<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EpcPointsMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_ew_points_endpoint_returns_points(): void
    {
        $this->ensureOnsudSchema();
        $this->ensureEpcSchema();

        DB::table('onsud')->insert([
            'UPRN' => '10000000001',
            'GRIDGB1E' => 530000,
            'GRIDGB1N' => 180000,
            'ctry25cd' => 'E92000001',
        ]);

        DB::table('epc_certificates')->insert([
            'UPRN' => '10000000001',
            'LODGEMENT_DATE' => '2024-01-01',
            'LMK_KEY' => 'LMK-1',
            'ADDRESS' => '1 Test Street',
            'POSTCODE' => 'AB1 2CD',
            'CURRENT_ENERGY_RATING' => 'C',
        ]);

        $response = $this->getJson('/epc/points?zoom=12&limit=1000&e_min=529000&e_max=531000&n_min=179000&n_max=181000');

        $response->assertOk();
        $response->assertJsonCount(1, 'points');
    }

    public function test_scotland_points_endpoint_returns_points(): void
    {
        $this->ensureOnsudSchema();
        $this->ensureScotlandEpcSchema();

        DB::table('onsud')->insert([
            'UPRN' => '20000000002',
            'GRIDGB1E' => 325000,
            'GRIDGB1N' => 674000,
            'ctry25cd' => 'S92000003',
        ]);

        DB::table('epc_certificates_scotland')->insert([
            'OSG_REFERENCE_NUMBER' => '20000000002',
            'LODGEMENT_DATE' => '2024-02-01',
            'REPORT_REFERENCE_NUMBER' => 'RRN-1',
            'ADDRESS1' => '1 Scot Street',
            'POSTCODE' => 'EH1 1AA',
            'CURRENT_ENERGY_RATING' => 'B',
        ]);

        $response = $this->getJson('/epc/points_scotland?zoom=12&limit=1000&e_min=324000&e_max=326000&n_min=673000&n_max=675000');

        $response->assertOk();
        $response->assertJsonCount(1, 'points');
    }

    private function ensureOnsudSchema(): void
    {
        if (! Schema::hasTable('onsud')) {
            Schema::create('onsud', function (Blueprint $table): void {
                $table->string('UPRN', 12)->nullable();
                $table->integer('GRIDGB1E')->nullable();
                $table->integer('GRIDGB1N')->nullable();
                $table->string('ctry25cd', 12)->nullable();
            });
        }

        foreach ([
            'UPRN' => fn (Blueprint $table) => $table->string('UPRN', 12)->nullable(),
            'GRIDGB1E' => fn (Blueprint $table) => $table->integer('GRIDGB1E')->nullable(),
            'GRIDGB1N' => fn (Blueprint $table) => $table->integer('GRIDGB1N')->nullable(),
            'ctry25cd' => fn (Blueprint $table) => $table->string('ctry25cd', 12)->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('onsud', $column)) {
                Schema::table('onsud', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }

    private function ensureEpcSchema(): void
    {
        if (! Schema::hasTable('epc_certificates')) {
            Schema::create('epc_certificates', function (Blueprint $table): void {
                $table->string('UPRN', 32)->nullable();
                $table->string('LODGEMENT_DATE', 32)->nullable();
                $table->string('LMK_KEY', 128)->nullable();
                $table->text('ADDRESS')->nullable();
                $table->string('POSTCODE', 16)->nullable();
                $table->string('CURRENT_ENERGY_RATING', 8)->nullable();
            });
        }

        foreach ([
            'UPRN' => fn (Blueprint $table) => $table->string('UPRN', 32)->nullable(),
            'LODGEMENT_DATE' => fn (Blueprint $table) => $table->string('LODGEMENT_DATE', 32)->nullable(),
            'LMK_KEY' => fn (Blueprint $table) => $table->string('LMK_KEY', 128)->nullable(),
            'ADDRESS' => fn (Blueprint $table) => $table->text('ADDRESS')->nullable(),
            'POSTCODE' => fn (Blueprint $table) => $table->string('POSTCODE', 16)->nullable(),
            'CURRENT_ENERGY_RATING' => fn (Blueprint $table) => $table->string('CURRENT_ENERGY_RATING', 8)->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('epc_certificates', $column)) {
                Schema::table('epc_certificates', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }

    private function ensureScotlandEpcSchema(): void
    {
        if (! Schema::hasTable('epc_certificates_scotland')) {
            Schema::create('epc_certificates_scotland', function (Blueprint $table): void {
                $table->string('OSG_REFERENCE_NUMBER')->nullable();
                $table->string('LODGEMENT_DATE')->nullable();
                $table->string('REPORT_REFERENCE_NUMBER')->nullable();
                $table->text('ADDRESS1')->nullable();
                $table->string('POSTCODE')->nullable();
                $table->string('CURRENT_ENERGY_RATING')->nullable();
            });
        }

        foreach ([
            'OSG_REFERENCE_NUMBER' => fn (Blueprint $table) => $table->string('OSG_REFERENCE_NUMBER')->nullable(),
            'LODGEMENT_DATE' => fn (Blueprint $table) => $table->string('LODGEMENT_DATE')->nullable(),
            'REPORT_REFERENCE_NUMBER' => fn (Blueprint $table) => $table->string('REPORT_REFERENCE_NUMBER')->nullable(),
            'ADDRESS1' => fn (Blueprint $table) => $table->text('ADDRESS1')->nullable(),
            'POSTCODE' => fn (Blueprint $table) => $table->string('POSTCODE')->nullable(),
            'CURRENT_ENERGY_RATING' => fn (Blueprint $table) => $table->string('CURRENT_ENERGY_RATING')->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('epc_certificates_scotland', $column)) {
                Schema::table('epc_certificates_scotland', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }
}
