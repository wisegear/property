<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('epc_certificates')) {
            return;
        }

        $addressColumn = $this->resolveColumn('epc_certificates', ['ADDRESS', 'address']);
        $postcodeColumn = $this->resolveColumn('epc_certificates', ['POSTCODE', 'postcode']);

        if ($addressColumn === null || $postcodeColumn === null) {
            return;
        }

        $quotedAddress = $this->quoteIdentifier($addressColumn);
        $quotedPostcode = $this->quoteIdentifier($postcodeColumn);

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_epc_cert_postcode ON epc_certificates ('.$quotedPostcode.')');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_epc_cert_address_trgm ON epc_certificates USING gin (UPPER(COALESCE('.$quotedAddress.", '')) gin_trgm_ops)");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_epc_cert_address_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_epc_cert_postcode');
    }

    private function resolveColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
