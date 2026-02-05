<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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

        $current = $this->findColumn();
        if ($current === null || $current === 'BUILDING_REFERENCE_NUMBER') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE epc_certificates_scotland RENAME COLUMN %s TO %s',
            $this->quoteIdentifier($current),
            $this->quoteIdentifier('BUILDING_REFERENCE_NUMBER')
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $current = $this->findColumn();
        if ($current === null || $current !== 'BUILDING_REFERENCE_NUMBER') {
            return;
        }

        $bom = "\xEF\xBB\xBF";
        DB::statement(sprintf(
            'ALTER TABLE epc_certificates_scotland RENAME COLUMN %s TO %s',
            $this->quoteIdentifier('BUILDING_REFERENCE_NUMBER'),
            $this->quoteIdentifier($bom.'BUILDING_REFERENCE_NUMBER')
        ));
    }

    private function findColumn(): ?string
    {
        $rows = DB::select(<<<'SQL'
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'epc_certificates_scotland'
              AND column_name LIKE '%BUILDING_REFERENCE_NUMBER%'
        SQL);

        if (empty($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if ($row->column_name === 'BUILDING_REFERENCE_NUMBER') {
                return 'BUILDING_REFERENCE_NUMBER';
            }
        }

        return $rows[0]->column_name;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
