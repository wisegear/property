<?php

namespace Tests\Feature;

use App\Services\EpcMatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EpcMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_matcher_uses_cased_epc_columns(): void
    {
        $this->ensureEpcSchema();

        DB::table('epc_certificates')->insert([
            'LMK_KEY' => 'LMK-MATCH-1',
            'ADDRESS' => '10 Test Street, London',
            'POSTCODE' => 'SW7 5PH',
            'LODGEMENT_DATE' => '2024-01-01',
            'CURRENT_ENERGY_RATING' => 'C',
            'POTENTIAL_ENERGY_RATING' => 'B',
            'PROPERTY_TYPE' => 'Flat',
            'TOTAL_FLOOR_AREA' => '75',
            'LOCAL_AUTHORITY_LABEL' => 'Test Authority',
        ]);

        $matcher = new EpcMatcher;
        $matches = $matcher->findForProperty('SW7 5PH', '10', null, 'Test Street');

        $this->assertNotEmpty($matches);
        $this->assertSame('LMK-MATCH-1', $matches[0]['row']->lmk_key);
        $this->assertSame(100.0, $matches[0]['score']);
    }

    private function ensureEpcSchema(): void
    {
        if (! Schema::hasTable('epc_certificates')) {
            Schema::create('epc_certificates', function (Blueprint $table): void {
                $table->string('LMK_KEY', 128)->nullable();
                $table->text('ADDRESS')->nullable();
                $table->string('POSTCODE', 16)->nullable();
                $table->string('LODGEMENT_DATE', 32)->nullable();
                $table->string('CURRENT_ENERGY_RATING', 8)->nullable();
                $table->string('POTENTIAL_ENERGY_RATING', 8)->nullable();
                $table->string('PROPERTY_TYPE', 255)->nullable();
                $table->string('TOTAL_FLOOR_AREA', 64)->nullable();
                $table->string('LOCAL_AUTHORITY_LABEL', 255)->nullable();
            });
        }

        foreach ([
            'LMK_KEY' => fn (Blueprint $table) => $table->string('LMK_KEY', 128)->nullable(),
            'ADDRESS' => fn (Blueprint $table) => $table->text('ADDRESS')->nullable(),
            'POSTCODE' => fn (Blueprint $table) => $table->string('POSTCODE', 16)->nullable(),
            'LODGEMENT_DATE' => fn (Blueprint $table) => $table->string('LODGEMENT_DATE', 32)->nullable(),
            'CURRENT_ENERGY_RATING' => fn (Blueprint $table) => $table->string('CURRENT_ENERGY_RATING', 8)->nullable(),
            'POTENTIAL_ENERGY_RATING' => fn (Blueprint $table) => $table->string('POTENTIAL_ENERGY_RATING', 8)->nullable(),
            'PROPERTY_TYPE' => fn (Blueprint $table) => $table->string('PROPERTY_TYPE', 255)->nullable(),
            'TOTAL_FLOOR_AREA' => fn (Blueprint $table) => $table->string('TOTAL_FLOOR_AREA', 64)->nullable(),
            'LOCAL_AUTHORITY_LABEL' => fn (Blueprint $table) => $table->string('LOCAL_AUTHORITY_LABEL', 255)->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('epc_certificates', $column)) {
                Schema::table('epc_certificates', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }
}
