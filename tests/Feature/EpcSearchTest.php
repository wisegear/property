<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EpcSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_uses_cased_postcode_column(): void
    {
        $this->ensureEpcSchema();

        DB::table('epc_certificates')->insert([
            'POSTCODE' => 'WR5 3EU',
            'LODGEMENT_DATE' => '2024-01-01',
            'LMK_KEY' => 'LMK-SEARCH',
            'ADDRESS' => '1 Search Street',
            'CURRENT_ENERGY_RATING' => 'D',
        ]);

        $response = $this->get('/epc/search?postcode=WR5+3EU');

        $response->assertOk();
        $response->assertSee('WR5 3EU');
    }

    private function ensureEpcSchema(): void
    {
        if (! Schema::hasTable('epc_certificates')) {
            Schema::create('epc_certificates', function (Blueprint $table): void {
                $table->string('POSTCODE', 16)->nullable();
                $table->string('LODGEMENT_DATE', 32)->nullable();
                $table->string('LMK_KEY', 128)->nullable();
                $table->text('ADDRESS')->nullable();
                $table->string('CURRENT_ENERGY_RATING', 8)->nullable();
            });
        }

        foreach ([
            'POSTCODE' => fn (Blueprint $table) => $table->string('POSTCODE', 16)->nullable(),
            'LODGEMENT_DATE' => fn (Blueprint $table) => $table->string('LODGEMENT_DATE', 32)->nullable(),
            'LMK_KEY' => fn (Blueprint $table) => $table->string('LMK_KEY', 128)->nullable(),
            'ADDRESS' => fn (Blueprint $table) => $table->text('ADDRESS')->nullable(),
            'CURRENT_ENERGY_RATING' => fn (Blueprint $table) => $table->string('CURRENT_ENERGY_RATING', 8)->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('epc_certificates', $column)) {
                Schema::table('epc_certificates', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }
}
