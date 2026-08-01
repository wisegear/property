<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('epc_certificates_scotland')) {
            return;
        }

        if (! $this->hasSingleColumnIndex('REPORT_REFERENCE_NUMBER')) {
            DB::statement(<<<'SQL'
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_epc_scotland_report_reference_number
ON epc_certificates_scotland ("REPORT_REFERENCE_NUMBER")
SQL);
        }

        if (! $this->hasSingleColumnIndex('POSTCODE')) {
            DB::statement(<<<'SQL'
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_epc_scotland_postcode
ON epc_certificates_scotland ("POSTCODE")
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('epc_certificates_scotland')) {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_epc_scotland_report_reference_number');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_epc_scotland_postcode');
    }

    private function hasSingleColumnIndex(string $column): bool
    {
        $result = DB::select(<<<'SQL'
SELECT 1
FROM pg_index i
JOIN pg_class table_class ON table_class.oid = i.indrelid
JOIN pg_namespace namespace ON namespace.oid = table_class.relnamespace
JOIN pg_attribute attribute
  ON attribute.attrelid = table_class.oid
 AND attribute.attnum = i.indkey[0]
WHERE namespace.nspname = current_schema()
  AND table_class.relname = 'epc_certificates_scotland'
  AND i.indnatts = 1
  AND attribute.attname = ?
LIMIT 1
SQL, [$column]);

        return $result !== [];
    }
};
