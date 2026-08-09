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

        DB::statement(<<<'SQL'
            DELETE FROM epc_certificates duplicate
            USING epc_certificates original
            WHERE duplicate."LMK_KEY" = original."LMK_KEY"
              AND duplicate.ctid > original.ctid
            SQL);

        Schema::table('epc_certificates', function (Blueprint $table): void {
            $table->unique('LMK_KEY', 'uq_epc_certificates_lmk_key');
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
