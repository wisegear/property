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

        if ($this->indexExists('onspd_v2', 'idx_onspd_v2_outcode_lookup')) {
            return;
        }

        DB::statement(<<<'SQL'
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_onspd_v2_outcode_lookup
ON onspd_v2 ((UPPER(SPLIT_PART(pcds, ' ', 1))))
WHERE lat IS NOT NULL
  AND "long" IS NOT NULL
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('onspd_v2')) {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_onspd_v2_outcode_lookup');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select(
            'SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ? LIMIT 1',
            [$table, $indexName]
        );

        return $result !== [];
    }
};
