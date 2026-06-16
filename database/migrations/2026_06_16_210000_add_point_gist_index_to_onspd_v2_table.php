<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('onspd_v2')) {
            return;
        }

        DB::statement(<<<'SQL'
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_onspd_v2_point_gist
ON onspd_v2
USING gist (point("long", lat))
WHERE lat IS NOT NULL
  AND "long" IS NOT NULL
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('onspd_v2')) {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_onspd_v2_point_gist');
    }
};
