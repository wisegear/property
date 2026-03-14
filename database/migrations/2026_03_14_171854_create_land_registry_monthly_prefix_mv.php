<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP MATERIALIZED VIEW IF EXISTS land_registry_monthly_prefix_mv');
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW land_registry_monthly_prefix_mv AS
            SELECT
                DATE_TRUNC('month', "Date")::date AS month,
                LEFT(REPLACE("Postcode", ' ', ''), 3) AS postcode_prefix,
                COUNT(*) AS sales,
                AVG("Price") AS avg_price,
                SUM(CASE WHEN "NewBuild" = 'Y' AND "Price" IS NOT NULL AND "Price" > 0 THEN 1 ELSE 0 END) AS new_build_sales,
                SUM(CASE WHEN "NewBuild" = 'N' AND "Price" IS NOT NULL AND "Price" > 0 THEN 1 ELSE 0 END) AS existing_sales,
                SUM(CASE WHEN "Duration" = 'F' AND "Price" IS NOT NULL AND "Price" > 0 THEN 1 ELSE 0 END) AS freehold_sales,
                SUM(CASE WHEN "Duration" = 'L' AND "Price" IS NOT NULL AND "Price" > 0 THEN 1 ELSE 0 END) AS leasehold_sales
            FROM land_registry
            WHERE "PPDCategoryType" = 'A'
            GROUP BY month, postcode_prefix
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX land_registry_monthly_prefix_mv_idx
            ON land_registry_monthly_prefix_mv (postcode_prefix, month)
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP MATERIALIZED VIEW IF EXISTS land_registry_monthly_prefix_mv');
    }
};
