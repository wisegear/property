<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crime', function (Blueprint $table) {
            $table->index(['month', 'crime_type'], 'crime_month_crime_type_index');
            $table->index(['month', 'latitude', 'longitude'], 'crime_month_latitude_longitude_index');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("
                CREATE INDEX crime_area_month_crime_type_index
                ON crime (
                    (COALESCE(NULLIF(TRIM(falls_within), ''), NULLIF(TRIM(reported_by), ''), NULLIF(TRIM(lsoa_name), ''))),
                    month,
                    crime_type
                )
            ");
        } elseif ($driver === 'sqlite') {
            DB::statement("
                CREATE INDEX crime_area_month_crime_type_index
                ON crime (
                    COALESCE(NULLIF(TRIM(falls_within), ''), NULLIF(TRIM(reported_by), ''), NULLIF(TRIM(lsoa_name), '')),
                    month,
                    crime_type
                )
            ");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS crime_area_month_crime_type_index');
        }

        Schema::table('crime', function (Blueprint $table) {
            $table->dropIndex('crime_month_crime_type_index');
            $table->dropIndex('crime_month_latitude_longitude_index');
        });
    }
};
