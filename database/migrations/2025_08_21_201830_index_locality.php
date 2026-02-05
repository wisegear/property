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
            $table->index('Locality', 'idx_locality');
            $table->index(['Locality', 'YearDate'], 'idx_locality_yeardate');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('land_registry', function (Blueprint $table) {
            $table->dropIndex('idx_locality');
            $table->dropIndex('idx_locality_yeardate');
        });
    }
};
