<?php

namespace Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
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

    public function test_england_wales_detail_does_not_query_scotland_certificates(): void
    {
        $this->ensureEpcSchema();

        DB::table('epc_certificates')->insert([
            'LMK_KEY' => 'EW-ONLY-1',
            'ADDRESS' => '1 England Street',
        ]);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('epc.show', ['lmk' => 'EW-ONLY-1']))
            ->assertOk()
            ->assertSee('EW-ONLY-1');

        $this->assertFalse(collect($queries)->contains(
            fn (string $sql): bool => str_contains(strtolower($sql), 'epc_certificates_scotland')
        ));
    }

    public function test_scotland_detail_uses_report_reference_number_on_dedicated_route(): void
    {
        DB::table('epc_certificates_scotland')->insert([
            'REPORT_REFERENCE_NUMBER' => 'SCOTLAND-RRN-1',
            'BUILDING_REFERENCE_NUMBER' => 'SCOTLAND-BUILDING-1',
            'ADDRESS1' => '1 Scotland Street',
        ]);

        $this->get(route('epc.scotland.show', ['rrn' => 'SCOTLAND-RRN-1']))
            ->assertOk()
            ->assertSee('SCOTLAND-RRN-1')
            ->assertSee('1 Scotland Street');
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
