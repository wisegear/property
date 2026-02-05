<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EpcShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_uses_cased_lmk_column(): void
    {
        $this->ensureEpcSchema();

        DB::table('epc_certificates')->insert([
            'LMK_KEY' => 'LMK-CASE-1',
            'ADDRESS' => '1 View Street',
        ]);

        $response = $this->get('/epc/LMK-CASE-1');

        $response->assertOk();
        $response->assertSee('LMK-CASE-1');
    }

    private function ensureEpcSchema(): void
    {
        if (! Schema::hasTable('epc_certificates')) {
            Schema::create('epc_certificates', function (Blueprint $table): void {
                $table->string('LMK_KEY', 128)->nullable();
                $table->text('ADDRESS')->nullable();
            });
        }

        foreach ([
            'LMK_KEY' => fn (Blueprint $table) => $table->string('LMK_KEY', 128)->nullable(),
            'ADDRESS' => fn (Blueprint $table) => $table->text('ADDRESS')->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('epc_certificates', $column)) {
                Schema::table('epc_certificates', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }
}
