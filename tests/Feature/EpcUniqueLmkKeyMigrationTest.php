<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EpcUniqueLmkKeyMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_can_run_when_the_unique_index_already_exists(): void
    {
        $migration = require database_path('migrations/2026_08_09_144521_deduplicate_and_restore_unique_lmk_key_on_epc_certificates_table.php');

        $migration->up();

        $this->assertTrue(Schema::hasIndex('epc_certificates', 'uq_epc_certificates_lmk_key', 'unique'));
    }

    public function test_migration_replaces_a_non_unique_index_after_deduplicating_rows(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This migration only runs on PostgreSQL.');
        }

        Schema::table('epc_certificates', function (Blueprint $table): void {
            $table->dropUnique('uq_epc_certificates_lmk_key');
            $table->index('LMK_KEY', 'uq_epc_certificates_lmk_key');
        });

        DB::table('epc_certificates')->insert([
            ['LMK_KEY' => 'duplicate-key'],
            ['LMK_KEY' => 'duplicate-key'],
        ]);

        $migration = require database_path('migrations/2026_08_09_144521_deduplicate_and_restore_unique_lmk_key_on_epc_certificates_table.php');

        $migration->up();

        $this->assertSame(1, DB::table('epc_certificates')->where('LMK_KEY', 'duplicate-key')->count());
        $this->assertTrue(Schema::hasIndex('epc_certificates', 'uq_epc_certificates_lmk_key', 'unique'));
    }
}
