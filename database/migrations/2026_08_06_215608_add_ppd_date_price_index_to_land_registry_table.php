<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_land_registry_ppd_date_price
ON land_registry ("PPDCategoryType", "Date", "Price");
SQL);

            return;
        }

        Schema::table('land_registry', function (Blueprint $table): void {
            $table->index(
                ['PPDCategoryType', 'Date', 'Price'],
                'idx_land_registry_ppd_date_price'
            );
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
DROP INDEX CONCURRENTLY IF EXISTS idx_land_registry_ppd_date_price;
SQL);

            return;
        }

        Schema::table('land_registry', function (Blueprint $table): void {
            $table->dropIndex('idx_land_registry_ppd_date_price');
        });
    }
};
