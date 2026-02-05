<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EpcSearchScotlandTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_scotland_uses_cased_postcode_column(): void
    {
        $this->ensureScotlandEpcSchema();

        DB::table('epc_certificates_scotland')->insert([
            'POSTCODE' => 'EH1 1AA',
            'LODGEMENT_DATE' => '2024-02-01',
            'REPORT_REFERENCE_NUMBER' => 'RRN-CASE-1',
            'ADDRESS1' => '1 Scot Street',
            'CURRENT_ENERGY_RATING' => 'B',
        ]);

        $response = $this->get('/epc/search_scotland?postcode=EH1+1AA');

        $response->assertOk();
        $response->assertSee('EH1 1AA');
    }

    private function ensureScotlandEpcSchema(): void
    {
        if (! Schema::hasTable('epc_certificates_scotland')) {
            Schema::create('epc_certificates_scotland', function (Blueprint $table): void {
                $table->string('POSTCODE')->nullable();
                $table->string('LODGEMENT_DATE')->nullable();
                $table->string('REPORT_REFERENCE_NUMBER')->nullable();
                $table->text('ADDRESS1')->nullable();
                $table->string('CURRENT_ENERGY_RATING')->nullable();
            });
        }

        foreach ([
            'POSTCODE' => fn (Blueprint $table) => $table->string('POSTCODE')->nullable(),
            'LODGEMENT_DATE' => fn (Blueprint $table) => $table->string('LODGEMENT_DATE')->nullable(),
            'REPORT_REFERENCE_NUMBER' => fn (Blueprint $table) => $table->string('REPORT_REFERENCE_NUMBER')->nullable(),
            'ADDRESS1' => fn (Blueprint $table) => $table->text('ADDRESS1')->nullable(),
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
