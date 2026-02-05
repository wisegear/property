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

        if (! Schema::hasColumn('land_registry', 'YearDate')) {
            Schema::table('land_registry', function (Blueprint $table) {
                $table->year('YearDate')->nullable();
            });
        }

        $bounds = DB::selectOne('
            SELECT MIN(EXTRACT(YEAR FROM "Date")) AS miny, MAX(EXTRACT(YEAR FROM "Date")) AS maxy
            FROM land_registry
            WHERE "Date" IS NOT NULL
        ');

        if ($bounds && $bounds->miny !== null && $bounds->maxy !== null) {
            DB::statement('
                UPDATE land_registry
                SET "YearDate" = EXTRACT(YEAR FROM "Date")::int
                WHERE "YearDate" IS NULL
                  AND "Date" IS NOT NULL
            ');
        }

        Schema::table('land_registry', function (Blueprint $table) {
            $table->index('YearDate', 'idx_yeardate');
            $table->index(['County', 'YearDate'], 'idx_county_yeardate');
            $table->index(['Postcode', 'YearDate'], 'idx_postcode_yeardate');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('land_registry', function (Blueprint $table) {
            $table->dropIndex('idx_yeardate');
            $table->dropIndex('idx_county_yeardate');
            $table->dropIndex('idx_postcode_yeardate');
            $table->dropColumn('YearDate');
        });
    }
};
