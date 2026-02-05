<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Add generated normalized columns and composite index for sargable queries
        DB::statement(<<<'SQL'
            ALTER TABLE imd2019
            ADD COLUMN measurement_norm VARCHAR(16)
              GENERATED ALWAYS AS (LOWER(BTRIM("Measurement"))) STORED,
            ADD COLUMN iod_norm VARCHAR(160)
              GENERATED ALWAYS AS (LOWER(BTRIM("Indices_of_Deprivation"))) STORED
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX idx_imd_norm_combo
              ON imd2019 ("FeatureCode", measurement_norm, iod_norm);
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_imd_norm_combo');
        DB::statement('ALTER TABLE imd2019 DROP COLUMN IF EXISTS measurement_norm, DROP COLUMN IF EXISTS iod_norm');
    }
};
