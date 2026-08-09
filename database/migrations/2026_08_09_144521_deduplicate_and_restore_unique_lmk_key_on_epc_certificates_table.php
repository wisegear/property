<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $indexName = 'uq_epc_certificates_lmk_key';

        if (Schema::hasIndex('epc_certificates', $indexName, 'unique')) {
            return;
        }

        DB::statement(<<<'SQL'
            DELETE FROM epc_certificates duplicate
            USING epc_certificates original
            WHERE duplicate."LMK_KEY" = original."LMK_KEY"
              AND duplicate.ctid > original.ctid
            SQL);

        if (Schema::hasIndex('epc_certificates', $indexName)) {
            Schema::table('epc_certificates', function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        }

        Schema::table('epc_certificates', function (Blueprint $table) use ($indexName): void {
            $table->unique('LMK_KEY', $indexName);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('epc_certificates', function (Blueprint $table): void {
            $table->dropUnique('uq_epc_certificates_lmk_key');
        });
    }
};
