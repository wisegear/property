<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('land_registry')) {
            return;
        }

        DB::statement(<<<'SQL'
CREATE INDEX CONCURRENTLY IF NOT EXISTS land_registry_transaction_id_idx
ON land_registry ("TransactionID")
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('land_registry')) {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS land_registry_transaction_id_idx');
    }
};
