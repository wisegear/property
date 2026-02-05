<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('land_registry', function (Blueprint $table) {
            // Existing indexes
            if (! $this->indexExists('land_registry', 'idx_county_date')) {
                $table->index(['County', 'Date'], 'idx_county_date');
            }
            if (! $this->indexExists('land_registry', 'idx_postcode_date')) {
                $table->index(['Postcode', 'Date'], 'idx_postcode_date');
            }
            // Additional for Property Type queries at county level
            if (! $this->indexExists('land_registry', 'idx_county_propertytype')) {
                $table->index(['County', 'PropertyType'], 'idx_county_propertytype');
            }
            // Optional: If SAON is often null in property lookups, indexing PAON + Street + Postcode helps
            if (! $this->indexExists('land_registry', 'idx_paon_street_postcode')) {
                $table->index(['PAON', 'Street', 'Postcode'], 'idx_paon_street_postcode');
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('land_registry', function (Blueprint $table) {
            $table->dropIndex('idx_county_date');
            $table->dropIndex('idx_postcode_date');
            $table->dropIndex('idx_county_propertytype');
            $table->dropIndex('idx_paon_street_postcode');
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select(
            'SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ? LIMIT 1',
            [$table, $indexName]
        );

        return ! empty($result);
    }
};
