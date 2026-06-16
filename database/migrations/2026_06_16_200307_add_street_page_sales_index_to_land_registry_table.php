<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('land_registry')) {
            return;
        }

        if ($this->indexExists('land_registry', 'idx_land_registry_street_page_sales')) {
            return;
        }

        DB::statement(<<<'SQL'
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_land_registry_street_page_sales
ON land_registry (
  (UPPER(SPLIT_PART("Postcode", ' ', 1))),
  (TRIM("Street")),
  "Date" DESC,
  "Price" DESC
)
WHERE "PPDCategoryType" = 'A'
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('land_registry')) {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_land_registry_street_page_sales');
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
